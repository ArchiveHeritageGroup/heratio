<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\FindingAidJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FindingAidController extends Controller
{
    /**
     * Generate a finding aid (PDF) for an information object.
     * Migrated from AtoM InformationObjectGenerateFindingAidAction.
     */
    public function generate(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Check if finding aid already exists
        $existingPath = $this->getFindingAidPath($io->id);
        if ($existingPath && file_exists($existingPath)) {
            return redirect()->route('informationobject.show', $slug)
                ->with('info', 'A finding aid already exists for this description.');
        }

        // Dispatch the finding aid generation job
        FindingAidJob::dispatch($io->id);

        return redirect()->route('informationobject.show', $slug)
            ->with('success', 'Finding aid generation queued for: ' . ($io->title ?? $slug));
    }

    /**
     * Show the finding aid upload form.
     */
    public function uploadForm(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::findingaid.upload', [
            'io' => $io,
        ]);
    }

    /**
     * Process finding aid upload.
     * Migrated from AtoM InformationObjectUploadFindingAidAction.
     */
    public function upload(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,rtf|max:20480',
        ]);

        $file = $request->file('file');
        $filename = 'finding-aid-' . $io->id . '.' . $file->getClientOriginalExtension();

        // Store in downloads directory (matches AtoM convention)
        $downloadsDir = public_path('downloads');
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0755, true);
        }

        $file->move($downloadsDir, $filename);

        return redirect()->route('informationobject.show', $slug)
            ->with('success', 'Finding aid uploaded successfully.');
    }

    /**
     * Download an existing finding aid.
     */
    public function download(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $path = $this->getFindingAidPath($io->id);
        if (!$path || !file_exists($path)) {
            return redirect()->route('informationobject.show', $slug)
                ->with('error', 'No finding aid exists for this description.');
        }

        return response()->download($path);
    }

    private function getIO(string $slug): ?object
    {
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            return null;
        }

        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $slugRow->object_id)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }

    private function getFindingAidPath(int $objectId): ?string
    {
        $downloadsDir = public_path('downloads');
        foreach (['pdf', 'rtf'] as $ext) {
            $path = $downloadsDir . '/finding-aid-' . $objectId . '.' . $ext;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
