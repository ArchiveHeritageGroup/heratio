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
# Use the 'johan' voice (neutral reference). The 'johan-c2' reference audio says
# "...the glam and dam sector on the left hand side..." which F5 bleeds into cues
# that are semantically close (browse/facets), so it is avoided for demos.
F5_VOICE = os.environ.get("DEMO_F5_VOICE", "johan-demo")
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


def trim_silence(wav):
    """Strip leading/trailing silence (F5-TTS clips start with a blank gap)."""
    tmp = wav + ".trim.wav"
    sr = ("silenceremove=start_periods=1:start_duration=0:start_threshold=-45dB:"
          "detection=peak,areverse,"
          "silenceremove=start_periods=1:start_duration=0:start_threshold=-45dB:"
          "detection=peak,areverse")
    r = subprocess.run(["ffmpeg", "-nostdin", "-y", "-loglevel", "error",
                        "-i", wav, "-af", sr, tmp],
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    if r.returncode == 0 and os.path.getsize(tmp) > 0:
        os.replace(tmp, wav)
    elif os.path.exists(tmp):
        os.remove(tmp)


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


LOGO = os.path.join(ROOT, "public/vendor/ahg-theme-b5/images/heratio_logo.png")
PRESENTER = os.environ.get("DEMO_PRESENTER", "Presented by: Dr. Johan Pieterse")


def make_splash(display, out, tmp):
    """Render the intro splash: logo + scenario title + presenter, 4.5s silent."""
    tf = os.path.join(tmp, "title.txt"); open(tf, "w").write(display)
    pf = os.path.join(tmp, "pres.txt"); open(pf, "w").write(PRESENTER)
    fc = ("[0:v]scale=-1:300[lg];"
          "[1:v][lg]overlay=(W-w)/2:(H-h)/2-160[bg];"
          f"[bg]drawtext=textfile={tf}:fontcolor=white:fontsize=58:x=(w-tw)/2:y=h/2+70[t1];"
          f"[t1]drawtext=textfile={pf}:fontcolor=0xC9D6E3:fontsize=40:x=(w-tw)/2:y=h/2+160[out]")
    subprocess.run(["ffmpeg", "-nostdin", "-y", "-loglevel", "error",
                    "-loop", "1", "-t", "4.5", "-i", LOGO,
                    "-f", "lavfi", "-t", "4.5", "-i", "color=c=0x0F1E33:s=1920x1080",
                    "-f", "lavfi", "-t", "4.5", "-i", "anullsrc=r=44100:cl=stereo",
                    "-filter_complex", fc, "-map", "[out]", "-map", "2:a",
                    "-r", "30", "-pix_fmt", "yuv420p", "-c:v", "libx264",
                    "-c:a", "aac", "-ar", "44100", "-shortest", out], check=True)


def concat_av(splash, body, out):
    """Prepend the splash before the narrated body (normalise + re-encode)."""
    fc = ("[0:v]scale=1920:1080,setsar=1,fps=30[v0];[0:a]aresample=44100[a0];"
          "[1:v]scale=1920:1080,setsar=1,fps=30[v1];[1:a]aresample=44100[a1];"
          "[v0][a0][v1][a1]concat=n=2:v=1:a=1[v][a]")
    subprocess.run(["ffmpeg", "-nostdin", "-y", "-loglevel", "error",
                    "-i", splash, "-i", body, "-filter_complex", fc,
                    "-map", "[v]", "-map", "[a]", "-c:v", "libx264",
                    "-pix_fmt", "yuv420p", "-crf", "22", "-preset", "medium",
                    "-c:a", "aac", "-ar", "44100", "-movflags", "+faststart", out], check=True)


def main():
    os.makedirs(OUT, exist_ok=True)
    engine = pick_engine()
    print(f"TTS engine: {engine}" + (f" (voice f5:{F5_VOICE})" if engine == "f5" else " (placeholder)"))
    synth = f5_synth if engine == "f5" else piper_synth

    videos = glob.glob(os.path.join(ROOT, "test-results", "**", "video.webm"), recursive=True)
    if not videos:
        print("  no video.webm found - run the demo project first"); sys.exit(1)

    narr_dir = os.path.join(ROOT, "test-results", "narration")
    manifests = {f[:-5]: os.path.join(narr_dir, f)
                 for f in (os.listdir(narr_dir) if os.path.isdir(narr_dir) else [])
                 if f.endswith(".json")}
    for webm in videos:
        # Playwright truncates long result-dir names, so match the manifest by
        # the spec-name prefix (strip the -<hash>... / .demo suffix) rather than
        # an exact derive.
        basename = os.path.basename(os.path.dirname(webm))
        dir_key = re.split(r"\.demo|-[0-9a-f]{5,}", basename)[0].rstrip("-.")
        name = next((m for m in manifests
                     if m == dir_key or m.startswith(dir_key) or dir_key.startswith(m)), None)
        if not name:
            print(f"  {basename}: no narration manifest, skipping"); continue
        manifest = manifests[name]
        mdata = json.load(open(manifest))
        cues = mdata["cues"]
        display = mdata.get("displayName") or name
        with tempfile.TemporaryDirectory() as tmp:
            inputs, filt, labels = ["-i", webm], [], ""
            for i, c in enumerate(cues):
                wav = os.path.join(tmp, f"c{i}.wav")
                try:
                    synth(c["text"], wav)
                    trim_silence(wav)
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
            body = os.path.join(tmp, "body.mp4")
            subprocess.run(["ffmpeg", "-nostdin", "-y", "-loglevel", "error", *inputs,
                            "-filter_complex", fc, "-map", "0:v", "-map", "[aout]",
                            "-c:v", "libx264", "-pix_fmt", "yuv420p", "-crf", "22",
                            "-preset", "medium", "-c:a", "aac", "-shortest",
                            "-movflags", "+faststart", body], check=True)
            # optional narration-only .wav (set DEMO_WAV=1); mp4 is the default output
            wav_note = ""
            if os.environ.get("DEMO_WAV"):
                wav = os.path.join(OUT, f"{display}.wav")
                subprocess.run(["ffmpeg", "-nostdin", "-y", "-loglevel", "error",
                                "-i", body, "-vn", "-acodec", "pcm_s16le", wav], check=True)
                wav_note = f"  + {os.path.basename(wav)}"
            # prepend the AHG/Heratio splash (logo + presenter)
            splash = os.path.join(tmp, "splash.mp4")
            make_splash(display, splash, tmp)
            mp4 = os.path.join(OUT, f"{display}.mp4")
            concat_av(splash, body, mp4)
            sz = subprocess.check_output(["du", "-h", mp4]).split()[0].decode()
            print(f"  {mp4}  ({ncl} cues + splash, {sz}){wav_note}")
    print(f"Done. Narrated mp4s in {OUT}")


if __name__ == "__main__":
    main()
