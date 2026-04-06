<?php

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FilePlanImportService
{
    private FilePlanService $filePlanService;

    public function __construct(FilePlanService $filePlanService)
    {
        $this->filePlanService = $filePlanService;
    }

    /**
     * Import file plan nodes from a spreadsheet (Excel/CSV).
     */
    public function importFromSpreadsheet(
        string $filePath,
        array $columnMapping,
        ?string $department,
        ?string $agencyCode,
        int $userId
    ): array {
        $sessionId = DB::table('rm_fileplan_import_session')->insertGetId([
            'source_type' => 'spreadsheet',
            'source_filename' => basename($filePath),
            'department' => $department,
            'agency_code' => $agencyCode,
            'status' => 'processing',
            'imported_by' => $userId,
            'column_mapping_json' => json_encode($columnMapping),
            'created_at' => now(),
        ]);

        $errors = [];
        $importedCount = 0;

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            // Remove header row
            $headers = array_shift($rows);

            // Collect all codes to detect separator
            $allCodes = [];
            foreach ($rows as $row) {
                $codeCol = $columnMapping['code'] ?? null;
                if ($codeCol && !empty($row[$codeCol])) {
                    $allCodes[] = trim((string) $row[$codeCol]);
                }
            }

            $separator = $this->detectSeparator($allCodes);

            // Sort rows by code length to ensure parents created before children
            $dataRows = [];
            foreach ($rows as $rowIdx => $row) {
                $codeCol = $columnMapping['code'] ?? null;
                $titleCol = $columnMapping['title'] ?? null;
                $descCol = $columnMapping['description'] ?? null;
                $retentionCol = $columnMapping['retention_period'] ?? null;
                $disposalCol = $columnMapping['disposal_action'] ?? null;

                $code = $codeCol ? trim((string) ($row[$codeCol] ?? '')) : '';
                $title = $titleCol ? trim((string) ($row[$titleCol] ?? '')) : '';

                if (empty($code) && empty($title)) {
                    continue; // Skip empty rows
                }

                if (empty($code)) {
                    $errors[] = "Row " . ($rowIdx + 2) . ": Missing code";
                    continue;
                }

                if (empty($title)) {
                    $title = $code; // Use code as title fallback
                }

                $dataRows[] = [
                    'row_num' => $rowIdx + 2,
                    'code' => $code,
                    'title' => $title,
                    'description' => $descCol ? trim((string) ($row[$descCol] ?? '')) : null,
                    'retention_period' => $retentionCol ? trim((string) ($row[$retentionCol] ?? '')) : null,
                    'disposal_action' => $disposalCol ? trim((string) ($row[$disposalCol] ?? '')) : null,
                ];
            }

            // Sort by code segment count (parents first)
            usort($dataRows, function ($a, $b) use ($separator) {
                $aDepth = substr_count($a['code'], $separator);
                $bDepth = substr_count($b['code'], $separator);
                if ($aDepth === $bDepth) {
                    return strcmp($a['code'], $b['code']);
                }
                return $aDepth - $bDepth;
            });

            // Track created nodes by code
            $codeToId = [];

            // Pre-load existing nodes by code
            $existingNodes = DB::table('rm_fileplan_node')
                ->pluck('id', 'code')
                ->toArray();
            $codeToId = $existingNodes;

            foreach ($dataRows as $dataRow) {
                $code = $dataRow['code'];
                $parentCode = $this->getParentCode($code, $separator);
                $parentId = null;

                if ($parentCode !== null) {
                    if (isset($codeToId[$parentCode])) {
                        $parentId = $codeToId[$parentCode];
                    } else {
                        // Create placeholder parent nodes up the chain
                        $ancestorCodes = $this->parseCodeHierarchy($code, $separator);
                        array_pop($ancestorCodes); // Remove the current code

                        foreach ($ancestorCodes as $ancestorCode) {
                            if (!isset($codeToId[$ancestorCode])) {
                                $ancestorParentCode = $this->getParentCode($ancestorCode, $separator);
                                $ancestorParentId = $ancestorParentCode ? ($codeToId[$ancestorParentCode] ?? null) : null;

                                $segments = explode($separator, $ancestorCode);
                                $depth = count($segments) - 1;

                                $ancestorId = DB::table('rm_fileplan_node')->insertGetId([
                                    'parent_id' => $ancestorParentId,
                                    'node_type' => $depth === 0 ? 'plan' : 'series',
                                    'code' => $ancestorCode,
                                    'title' => end($segments),
                                    'status' => 'active',
                                    'source_department' => $department,
                                    'source_agency_code' => $agencyCode,
                                    'import_session_id' => $sessionId,
                                    'depth' => $depth,
                                    'created_by' => $userId,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                $codeToId[$ancestorCode] = $ancestorId;
                                $importedCount++;
                            }
                        }

                        $parentId = $parentCode ? ($codeToId[$parentCode] ?? null) : null;
                    }
                }

                // Skip if code already exists
                if (isset($codeToId[$code])) {
                    // Update existing node with imported data
                    DB::table('rm_fileplan_node')
                        ->where('id', $codeToId[$code])
                        ->update([
                            'title' => $dataRow['title'],
                            'description' => $dataRow['description'],
                            'retention_period' => $dataRow['retention_period'],
                            'disposal_action' => $dataRow['disposal_action'],
                            'import_session_id' => $sessionId,
                            'updated_at' => now(),
                        ]);
                    continue;
                }

                $segments = explode($separator, $code);
                $depth = count($segments) - 1;
                $nodeType = $this->inferNodeType($depth);

                $nodeId = DB::table('rm_fileplan_node')->insertGetId([
                    'parent_id' => $parentId,
                    'node_type' => $nodeType,
                    'code' => $code,
                    'title' => $dataRow['title'],
                    'description' => $dataRow['description'],
                    'retention_period' => $dataRow['retention_period'],
                    'disposal_action' => $dataRow['disposal_action'],
                    'status' => 'active',
                    'source_department' => $department,
                    'source_agency_code' => $agencyCode,
                    'import_session_id' => $sessionId,
                    'depth' => $depth,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $codeToId[$code] = $nodeId;
                $importedCount++;
            }

            // Rebuild nested set after all inserts
            $this->filePlanService->rebuildNestedSet();

            // Update session
            DB::table('rm_fileplan_import_session')
                ->where('id', $sessionId)
                ->update([
                    'status' => 'completed',
                    'total_nodes' => count($dataRows),
                    'imported_nodes' => $importedCount,
                    'errors_json' => !empty($errors) ? json_encode($errors) : null,
                    'completed_at' => now(),
                ]);
        } catch (\Exception $e) {
            $errors[] = 'Import failed: ' . $e->getMessage();

            DB::table('rm_fileplan_import_session')
                ->where('id', $sessionId)
                ->update([
                    'status' => 'failed',
                    'errors_json' => json_encode($errors),
                    'completed_at' => now(),
                ]);
        }

        return [
            'session_id' => $sessionId,
            'total' => count($dataRows ?? []),
            'imported' => $importedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Import file plan from a directory structure.
     */
    public function importFromDirectory(
        string $directoryPath,
        ?string $department,
        ?string $agencyCode,
        int $userId
    ): array {
        $sessionId = DB::table('rm_fileplan_import_session')->insertGetId([
            'source_type' => 'directory',
            'source_filename' => basename($directoryPath),
            'department' => $department,
            'agency_code' => $agencyCode,
            'status' => 'processing',
            'imported_by' => $userId,
            'created_at' => now(),
        ]);

        $errors = [];
        $importedCount = 0;

        try {
            if (!is_dir($directoryPath)) {
                throw new \RuntimeException("Directory not found: {$directoryPath}");
            }

            $nodes = $this->scanDirectory($directoryPath, null, 0, $sessionId, $department, $agencyCode, $userId, $importedCount);

            $this->filePlanService->rebuildNestedSet();

            DB::table('rm_fileplan_import_session')
                ->where('id', $sessionId)
                ->update([
                    'status' => 'completed',
                    'total_nodes' => $importedCount,
                    'imported_nodes' => $importedCount,
                    'completed_at' => now(),
                ]);
        } catch (\Exception $e) {
            $errors[] = 'Import failed: ' . $e->getMessage();

            DB::table('rm_fileplan_import_session')
                ->where('id', $sessionId)
                ->update([
                    'status' => 'failed',
                    'errors_json' => json_encode($errors),
                    'completed_at' => now(),
                ]);
        }

        return [
            'session_id' => $sessionId,
            'total' => $importedCount,
            'imported' => $importedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Recursively scan a directory and create file plan nodes.
     */
    private function scanDirectory(
        string $path,
        ?int $parentId,
        int $depth,
        int $sessionId,
        ?string $department,
        ?string $agencyCode,
        int $userId,
        int &$importedCount,
        string $codePrefix = ''
    ): void {
        $entries = scandir($path);
        $position = 0;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $path . '/' . $entry;

            if (!is_dir($fullPath)) {
                continue;
            }

            $position++;
            $parsed = $this->parseFolderName($entry);
            $code = $parsed['code'] ?: ($codePrefix ? $codePrefix . '/' . $position : (string) $position);
            $title = $parsed['title'] ?: $entry;

            // Count files in this folder
            $fileCount = count(array_filter(scandir($fullPath), function ($f) use ($fullPath) {
                return $f !== '.' && $f !== '..' && is_file($fullPath . '/' . $f);
            }));

            $nodeId = DB::table('rm_fileplan_node')->insertGetId([
                'parent_id' => $parentId,
                'node_type' => $this->inferNodeType($depth),
                'code' => $code,
                'title' => $title,
                'description' => $fileCount > 0 ? "Contains {$fileCount} file(s)" : null,
                'status' => 'active',
                'source_department' => $department,
                'source_agency_code' => $agencyCode,
                'import_session_id' => $sessionId,
                'depth' => $depth,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $importedCount++;

            // Recurse into subdirectories
            $this->scanDirectory(
                $fullPath,
                $nodeId,
                $depth + 1,
                $sessionId,
                $department,
                $agencyCode,
                $userId,
                $importedCount,
                $code
            );
        }
    }

    /**
     * Parse a folder name into code and title.
     */
    private function parseFolderName(string $name): array
    {
        // Pattern: "code - title"
        if (str_contains($name, ' - ')) {
            $parts = explode(' - ', $name, 2);
            return ['code' => trim($parts[0]), 'title' => trim($parts[1])];
        }

        // Pattern: starts with digits/separators then space
        if (preg_match('/^(\d[\d\/\-\.]*)\s+(.+)$/', $name, $matches)) {
            return ['code' => trim($matches[1]), 'title' => trim($matches[2])];
        }

        return ['code' => '', 'title' => $name];
    }

    /**
     * Import file plan from XML.
     */
    public function importFromXml(
        string $filePath,
        string $format,
        ?string $department,
        ?string $agencyCode,
        int $userId
    ): array {
        $sessionId = DB::table('rm_fileplan_import_session')->insertGetId([
            'source_type' => 'xml',
            'source_filename' => basename($filePath),
            'department' => $department,
            'agency_code' => $agencyCode,
            'status' => 'processing',
            'imported_by' => $userId,
            'created_at' => now(),
        ]);

        $errors = [];
        $importedCount = 0;

        try {
            $dom = new \DOMDocument();
            $dom->load($filePath);

            if ($format === 'ead') {
                $this->importEadNodes($dom, $sessionId, $department, $agencyCode, $userId, $importedCount, $errors);
            } else {
                $this->importGenericXmlNodes($dom, $sessionId, $department, $agencyCode, $userId, $importedCount, $errors);
            }

            $this->filePlanService->rebuildNestedSet();

            DB::table('rm_fileplan_import_session')
                ->where('id', $sessionId)
                ->update([
                    'status' => 'completed',
                    'total_nodes' => $importedCount,
                    'imported_nodes' => $importedCount,
                    'completed_at' => now(),
                ]);
        } catch (\Exception $e) {
            $errors[] = 'XML import failed: ' . $e->getMessage();

            DB::table('rm_fileplan_import_session')
                ->where('id', $sessionId)
                ->update([
                    'status' => 'failed',
                    'errors_json' => json_encode($errors),
                    'completed_at' => now(),
                ]);
        }

        return [
            'session_id' => $sessionId,
            'total' => $importedCount,
            'imported' => $importedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Import EAD XML (mapping <c> hierarchy).
     */
    private function importEadNodes(
        \DOMDocument $dom,
        int $sessionId,
        ?string $department,
        ?string $agencyCode,
        int $userId,
        int &$importedCount,
        array &$errors,
        ?\DOMElement $parentElement = null,
        ?int $parentId = null,
        int $depth = 0
    ): void {
        $elements = [];

        if ($parentElement === null) {
            // Start from archdesc/dsc or find <c> elements at top level
            $dscList = $dom->getElementsByTagName('dsc');
            if ($dscList->length > 0) {
                $parentElement = $dscList->item(0);
            } else {
                $parentElement = $dom->documentElement;
            }
        }

        foreach ($parentElement->childNodes as $child) {
            if ($child instanceof \DOMElement && preg_match('/^c\d*$/', $child->tagName)) {
                $elements[] = $child;
            }
        }

        foreach ($elements as $element) {
            $code = '';
            $title = '';
            $description = '';

            // Extract unitid
            $unitids = $element->getElementsByTagName('unitid');
            if ($unitids->length > 0) {
                $code = trim($unitids->item(0)->textContent);
            }

            // Extract unittitle
            $unittitles = $element->getElementsByTagName('unittitle');
            if ($unittitles->length > 0) {
                $title = trim($unittitles->item(0)->textContent);
            }

            // Extract scopecontent
            $scopes = $element->getElementsByTagName('scopecontent');
            if ($scopes->length > 0) {
                $description = trim($scopes->item(0)->textContent);
            }

            if (empty($code)) {
                $code = 'ead-' . ($importedCount + 1);
            }
            if (empty($title)) {
                $title = $code;
            }

            $nodeId = DB::table('rm_fileplan_node')->insertGetId([
                'parent_id' => $parentId,
                'node_type' => $this->inferNodeType($depth),
                'code' => $code,
                'title' => $title,
                'description' => $description ?: null,
                'status' => 'active',
                'source_department' => $department,
                'source_agency_code' => $agencyCode,
                'import_session_id' => $sessionId,
                'depth' => $depth,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $importedCount++;

            // Recurse into child <c> elements
            $this->importEadNodes($dom, $sessionId, $department, $agencyCode, $userId, $importedCount, $errors, $element, $nodeId, $depth + 1);
        }
    }

    /**
     * Import generic XML (look for <node>, <item>, <class> elements).
     */
    private function importGenericXmlNodes(
        \DOMDocument $dom,
        int $sessionId,
        ?string $department,
        ?string $agencyCode,
        int $userId,
        int &$importedCount,
        array &$errors,
        ?\DOMElement $parentElement = null,
        ?int $parentId = null,
        int $depth = 0
    ): void {
        if ($parentElement === null) {
            $parentElement = $dom->documentElement;
        }

        $targetTags = ['node', 'item', 'class', 'series', 'record', 'category', 'entry'];

        foreach ($parentElement->childNodes as $child) {
            if (!($child instanceof \DOMElement)) {
                continue;
            }

            if (!in_array(strtolower($child->tagName), $targetTags)) {
                // Recurse into non-target elements to find nested targets
                $this->importGenericXmlNodes($dom, $sessionId, $department, $agencyCode, $userId, $importedCount, $errors, $child, $parentId, $depth);
                continue;
            }

            $code = $child->getAttribute('code') ?: $child->getAttribute('id') ?: $child->getAttribute('ref') ?: '';
            $title = $child->getAttribute('title') ?: $child->getAttribute('name') ?: '';
            $description = '';

            // Try to get text content from child elements
            foreach ($child->childNodes as $subChild) {
                if ($subChild instanceof \DOMElement) {
                    $tagLower = strtolower($subChild->tagName);
                    if (in_array($tagLower, ['code', 'ref', 'number'])) {
                        $code = $code ?: trim($subChild->textContent);
                    } elseif (in_array($tagLower, ['title', 'name'])) {
                        $title = $title ?: trim($subChild->textContent);
                    } elseif (in_array($tagLower, ['description', 'desc', 'scope'])) {
                        $description = trim($subChild->textContent);
                    }
                }
            }

            if (empty($code)) {
                $code = 'xml-' . ($importedCount + 1);
            }
            if (empty($title)) {
                $title = $code;
            }

            $nodeId = DB::table('rm_fileplan_node')->insertGetId([
                'parent_id' => $parentId,
                'node_type' => $this->inferNodeType($depth),
                'code' => $code,
                'title' => $title,
                'description' => $description ?: null,
                'status' => 'active',
                'source_department' => $department,
                'source_agency_code' => $agencyCode,
                'import_session_id' => $sessionId,
                'depth' => $depth,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $importedCount++;

            // Recurse into children
            $this->importGenericXmlNodes($dom, $sessionId, $department, $agencyCode, $userId, $importedCount, $errors, $child, $nodeId, $depth + 1);
        }
    }

    /**
     * Parse code into hierarchy of ancestor codes.
     * "1/2/3" => ['1', '1/2', '1/2/3']
     */
    public function parseCodeHierarchy(string $code, string $separator = '/'): array
    {
        $segments = explode($separator, $code);
        $result = [];
        $current = '';

        foreach ($segments as $i => $segment) {
            $current = $i === 0 ? $segment : $current . $separator . $segment;
            $result[] = $current;
        }

        return $result;
    }

    /**
     * Get the parent code from a hierarchical code.
     */
    private function getParentCode(string $code, string $separator): ?string
    {
        $segments = explode($separator, $code);
        if (count($segments) <= 1) {
            return null;
        }
        array_pop($segments);
        return implode($separator, $segments);
    }

    /**
     * Detect the most common separator in a set of codes.
     */
    public function detectSeparator(array $codes): string
    {
        $separators = ['/' => 0, '-' => 0, '.' => 0];

        foreach ($codes as $code) {
            foreach ($separators as $sep => &$count) {
                $count += substr_count((string) $code, $sep);
            }
        }

        arsort($separators);
        $best = array_key_first($separators);

        // Default to '/' if no separators found
        return $separators[$best] > 0 ? $best : '/';
    }

    /**
     * Auto-detect column mapping from header names.
     */
    public function detectColumnMapping(array $headers): array
    {
        $mapping = [];
        $patterns = [
            'code' => ['code', 'ref', 'reference', 'number', 'classification', 'class', 'no', 'num'],
            'title' => ['title', 'name', 'series', 'heading', 'subject'],
            'description' => ['desc', 'description', 'scope', 'note', 'notes', 'content'],
            'retention_period' => ['retention', 'period', 'keep', 'duration', 'years'],
            'disposal_action' => ['disposal', 'action', 'dispose', 'disposition', 'fate'],
        ];

        foreach ($headers as $colLetter => $header) {
            if (empty($header)) {
                continue;
            }
            $headerLower = strtolower(trim((string) $header));

            foreach ($patterns as $field => $keywords) {
                if (isset($mapping[$field])) {
                    continue;
                }
                foreach ($keywords as $keyword) {
                    if (str_contains($headerLower, $keyword)) {
                        $mapping[$field] = $colLetter;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Validate import data before committing.
     */
    public function validateImport(array $rows, array $columnMapping): array
    {
        $errors = [];
        $codes = [];
        $codeCol = $columnMapping['code'] ?? null;
        $titleCol = $columnMapping['title'] ?? null;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // Account for header + 0-based index
            $code = $codeCol ? trim((string) ($row[$codeCol] ?? '')) : '';
            $title = $titleCol ? trim((string) ($row[$titleCol] ?? '')) : '';

            if (empty($code) && empty($title)) {
                continue; // Skip empty rows
            }

            if (empty($code)) {
                $errors[] = "Row {$rowNum}: Missing code/reference number";
            }

            if (empty($title)) {
                $errors[] = "Row {$rowNum}: Missing title";
            }

            if (!empty($code)) {
                if (in_array($code, $codes)) {
                    $errors[] = "Row {$rowNum}: Duplicate code '{$code}'";
                }
                $codes[] = $code;
            }
        }

        // Check parent codes exist
        if (!empty($codes)) {
            $separator = $this->detectSeparator($codes);
            foreach ($codes as $code) {
                $parentCode = $this->getParentCode($code, $separator);
                if ($parentCode !== null && !in_array($parentCode, $codes)) {
                    // Check if parent exists in DB
                    $existing = DB::table('rm_fileplan_node')->where('code', $parentCode)->exists();
                    if (!$existing) {
                        $errors[] = "Code '{$code}': parent code '{$parentCode}' not found (will be auto-created as placeholder)";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Link information_object records to file plan nodes based on matching identifiers.
     */
    public function linkRecordsToFilePlan(int $importSessionId): int
    {
        $nodes = DB::table('rm_fileplan_node')
            ->where('import_session_id', $importSessionId)
            ->get();

        $linkedCount = 0;

        foreach ($nodes as $node) {
            // Find IOs whose identifier starts with the node's code
            $matchingIos = DB::table('information_object')
                ->where('identifier', 'LIKE', $node->code . '%')
                ->pluck('id');

            if ($matchingIos->isEmpty()) {
                continue;
            }

            // If rm_record_disposal_class exists and node has a disposal class, link them
            if (Schema::hasTable('rm_record_disposal_class') && $node->disposal_class_id) {
                foreach ($matchingIos as $ioId) {
                    $exists = DB::table('rm_record_disposal_class')
                        ->where('information_object_id', $ioId)
                        ->where('disposal_class_id', $node->disposal_class_id)
                        ->exists();

                    if (!$exists) {
                        DB::table('rm_record_disposal_class')->insert([
                            'information_object_id' => $ioId,
                            'disposal_class_id' => $node->disposal_class_id,
                            'created_at' => now(),
                        ]);
                        $linkedCount++;
                    }
                }
            } else {
                $linkedCount += $matchingIos->count();
            }
        }

        // Update session
        DB::table('rm_fileplan_import_session')
            ->where('id', $importSessionId)
            ->update(['linked_records' => $linkedCount]);

        return $linkedCount;
    }

    /**
     * Get a single import session.
     */
    public function getImportSession(int $id): ?object
    {
        return DB::table('rm_fileplan_import_session')
            ->where('id', $id)
            ->first();
    }

    /**
     * Get paginated import sessions.
     */
    public function getImportSessions(int $page = 1, int $perPage = 25): array
    {
        $query = DB::table('rm_fileplan_import_session')
            ->orderByDesc('created_at');

        $total = $query->count();

        $data = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Infer node type based on depth level.
     */
    private function inferNodeType(int $depth): string
    {
        return match (true) {
            $depth === 0 => 'plan',
            $depth === 1 => 'series',
            $depth === 2 => 'sub_series',
            $depth === 3 => 'file_group',
            default => 'volume',
        };
    }
}
