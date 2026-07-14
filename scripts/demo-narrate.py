#!/usr/bin/env python3
"""
demo-narrate.py - synthesise the voiceover for the demo videos and mux it on.

For each Playwright 'demo' recording (test-results/**/video.webm) that has a
matching narration manifest (test-results/narration/<name>.json), this:
  1. synthesises each cue line as audio, in the operator's cloned voice
     (F5-TTS f5:johan-c2 at F5_TTS_BASE_URL), falling back to Piper if F5 is
     unavailable, so the pipeline is testable before the GPU node is healthy;
  2. delays each clip to its cue timestamp and mixes them into one track;
  3. muxes that track onto the silent video -> test-results/demo-videos/<name>.mp4

Engine: DEMO_TTS=f5|piper|auto (default auto - use F5 if it answers, else Piper).
"""
import json, os, glob, re, subprocess, sys, tempfile, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "test-results", "demo-videos")
F5_URL = os.environ.get("F5_TTS_BASE_URL", "http://192.168.0.76:7860").rstrip("/")
F5_VOICE = os.environ.get("DEMO_F5_VOICE", "johan-c2")
PIPER_BIN = "/opt/piper-tts/.venv/bin/piper"
PIPER_MODEL = "/opt/piper-tts/voices/en_GB-alba-medium.onnx"


def f5_synth(text, out_wav):
    body = json.dumps({"voice_id": F5_VOICE, "text": text}).encode()
    req = urllib.request.Request(f"{F5_URL}/tts", data=body,
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=180) as r:
        data = r.read()
    if not data:
        raise RuntimeError("empty audio")
    open(out_wav, "wb").write(data)


def piper_synth(text, out_wav):
    subprocess.run([PIPER_BIN, "-m", PIPER_MODEL, "-f", out_wav],
                   input=text.encode(), check=True,
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def pick_engine():
    want = os.environ.get("DEMO_TTS", "auto")
    if want in ("f5", "piper"):
        return want
    try:
        with tempfile.NamedTemporaryFile(suffix=".wav", delete=True) as t:
            f5_synth("test", t.name)
        return "f5"
    except Exception as e:
        print(f"  F5-TTS unavailable ({e}); falling back to Piper placeholder.")
        return "piper"


def main():
    os.makedirs(OUT, exist_ok=True)
    engine = pick_engine()
    print(f"TTS engine: {engine}" + (f" (voice f5:{F5_VOICE})" if engine == "f5" else " (placeholder)"))
    synth = f5_synth if engine == "f5" else piper_synth

    videos = glob.glob(os.path.join(ROOT, "test-results", "**", "video.webm"), recursive=True)
    if not videos:
        print("  no video.webm found - run the demo project first"); sys.exit(1)

    for webm in videos:
        name = re.sub(r"\..*$", "", os.path.basename(os.path.dirname(webm)))
        manifest = os.path.join(ROOT, "test-results", "narration", f"{name}.json")
        if not os.path.exists(manifest):
            print(f"  {name}: no narration manifest, skipping"); continue
        cues = json.load(open(manifest))["cues"]
        with tempfile.TemporaryDirectory() as tmp:
            inputs, filt, labels = ["-i", webm], [], ""
            for i, c in enumerate(cues):
                wav = os.path.join(tmp, f"c{i}.wav")
                try:
                    synth(c["text"], wav)
                except Exception as e:
                    print(f"    cue {i} synth failed: {e}"); continue
                inputs += ["-i", wav]
                ai = len(inputs) // 2 - 1  # ffmpeg input index of this clip
                ms = int(float(c["t"]) * 1000)
                filt.append(f"[{ai}]adelay={ms}:all=1[a{i}]")
                labels += f"[a{i}]"
            if not labels:
                print(f"  {name}: no audio produced"); continue
            ncl = labels.count("[a")
            fc = ";".join(filt) + f";{labels}amix=inputs={ncl}:normalize=0:dropout_transition=0,apad[aout]"
            mp4 = os.path.join(OUT, f"{name}.mp4")
            cmd = ["ffmpeg", "-nostdin", "-y", "-loglevel", "error", *inputs,
                   "-filter_complex", fc, "-map", "0:v", "-map", "[aout]",
                   "-c:v", "libx264", "-pix_fmt", "yuv420p", "-crf", "22",
                   "-preset", "medium", "-c:a", "aac", "-shortest",
                   "-movflags", "+faststart", mp4]
            subprocess.run(cmd, check=True)
            sz = subprocess.check_output(["du", "-h", mp4]).split()[0].decode()
            print(f"  {mp4}  ({ncl} cues, {sz})")
    print(f"Done. Narrated mp4s in {OUT}")


if __name__ == "__main__":
    main()
