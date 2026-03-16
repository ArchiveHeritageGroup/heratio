<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    /**
     * Show the XML import form (migrated from AtoM object/importSelectSuccess.php).
     */
    public function xml(string $slug = null)
    {
        $resource = null;
        if ($slug) {
            $resource = $this->getIO($slug);
        }

        return view('ahg-io-manage::import.select', [
            'type' => 'xml',
            'resource' => $resource,
            'title' => 'Import XML',
        ]);
    }

    /**
     * Show the CSV import form.
     */
    public function csv(string $slug = null)
    {
        $resource = null;
        if ($slug) {
            $resource = $this->getIO($slug);
        }

        return view('ahg-io-manage::import.select', [
            'type' => 'csv',
            'resource' => $resource,
            'title' => 'Import CSV',
        ]);
    }

    /**
     * Process the import upload.
     */
    public function process(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
            'importType' => 'required|in:xml,csv',
            'objectType' => 'required|string',
            'updateType' => 'required|string',
        ]);

        $type = $request->input('importType');
        $objectType = $request->input('objectType');
        $updateType = $request->input('updateType');
        $file = $request->file('file');

        // Store the uploaded file
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('imports', $filename, 'local');

        // Queue the import job
        $slug = $request->input('slug');
        ImportJob::dispatch($path, $type, $objectType, $updateType, $slug);

        return redirect()
            ->route($slug ? 'informationobject.show' : 'informationobject.browse', $slug ? ['slug' => $slug] : [])
            ->with('success', "Import queued: {$objectType} ({$type}) — file: {$file->getClientOriginalName()}. Processing will begin shortly.");
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
}
