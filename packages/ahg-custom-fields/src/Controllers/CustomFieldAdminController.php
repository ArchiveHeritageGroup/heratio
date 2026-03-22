<?php

namespace AhgCustomFields\Controllers;

use AhgCustomFields\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomFieldAdminController extends Controller
{
    public function __construct(
        protected CustomFieldService $service
    ) {}

    /**
     * List all custom field definitions.
     */
    public function index()
    {
        $definitions = $this->service->getDefinitions();

        return view('ahg-custom-fields::admin.index', compact('definitions'));
    }

    /**
     * Edit/create a custom field definition.
     */
    public function edit(?int $id = null)
    {
        $definition = $id ? $this->service->getDefinition($id) : null;
        $entityTypes = $this->service->getEntityTypes();
        $fieldTypes = $this->service->getFieldTypes();

        return view('ahg-custom-fields::admin.edit', compact('definition', 'entityTypes', 'fieldTypes'));
    }
}
