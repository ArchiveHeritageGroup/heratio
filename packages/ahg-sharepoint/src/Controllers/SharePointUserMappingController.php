<?php

namespace AhgSharePoint\Controllers;

use AhgSharePoint\Repositories\SharePointUserMappingRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Admin UI for sharepoint_user_mapping rows.
 *
 * @phase 2.B
 */
class SharePointUserMappingController extends Controller
{
    public function __construct(private SharePointUserMappingRepository $mappings)
    {
    }

    public function index()
    {
        return view('ahg-sharepoint::user-mappings', ['mappings' => $this->mappings->all()]);
    }

    public function edit(Request $request, int $id)
    {
        $mapping = DB::table('sharepoint_user_mapping')->where('id', $id)->first();
        if ($request->isMethod('POST') && $request->input('form_action') === 'delete') {
            $this->mappings->delete($id);
            return redirect()->route('sharepoint.user-mappings');
        }
        return view('ahg-sharepoint::user-mapping-edit', ['mapping' => $mapping]);
    }
}
