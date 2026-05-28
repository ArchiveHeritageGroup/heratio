<?php

/**
 * z3950Actions — Symfony 1.4 actions class for ahgLibraryPlugin
 *
 * Ported from: Heratio packages/ahg-z3950/src/Controllers/Z3950Controller.php
 *              Heratio packages/ahg-z3950/src/Controllers/SruController.php
 * Issue: atom-ahg-plugins#92
 *
 * Actions:
 *   /z3950               — dashboard
 *   /z3950/search        — search form
 *   /z3950/search/exec   — run search, store result set in session
 *   /z3950/result        — browse result set
 *   /z3950/import        — import single record
 *   /z3950/import/batch  — batch import
 *   /z3950/admin         — admin: manage targets, view logs
 *   /z3950/admin/targets — target CRUD
 *   /z3950/sru           — SRU 2.0 HTTP handler
 *
 * Service dependency is instantiated on first call (avoids constructor DI
 * compatibility issues across PSIS Doctrine versions).
 *
 * Copyright (C) 2026 Johan Pieterse — The Archive Heritage Group (Pty) Ltd
 * AGPL-3.0
 */

class z3950Actions extends sfActions
{
    /**
     * @var Z3950Service
     */
    protected Z3950Service $z3950Service;

    /**
     * Lazy-load the Z39.50 service to avoid constructor DI edge cases.
     */
    protected function getZ3950Service(): Z3950Service
    {
        if (! isset($this->z3950Service)) {
            $this->z3950Service = new Z3950Service();
        }
        return $this->z3950Service;
    }

    // ─── Dashboard ─────────────────────────────────────────────────────────

    public function executeIndex(sfWebRequest $request)
    {
        $conn = $this->getDb();

        $this->targets     = $conn->fetchAll("SELECT * FROM z3950_targets ORDER BY name LIMIT 5");
        $this->totalTargets = $conn->fetchColumn("SELECT COUNT(*) FROM z3950_targets");
        $this->totalSearches = $conn->fetchColumn("SELECT COUNT(*) FROM z3950_query_log");
        $this->totalImports = $conn->fetchColumn("SELECT COUNT(*) FROM z3950_import_log");
        $this->yazAvailable = extension_loaded('yaz');
    }

    // ─── Search ────────────────────────────────────────────────────────────

    /**
     * GET /z3950/search — render the Z39.50 search form.
     */
    public function executeSearch(sfWebRequest $request)
    {
        $this->targets = $this->getDb()->fetchAll(
            "SELECT * FROM z3950_targets WHERE active = 1 ORDER BY name"
        );
    }

    /**
     * POST /z3950/search/exec — run a Z39.50 search against the chosen target.
     *
     * Required params:
     *   target_id   — z3950_targets.id
     *   query       — CQL / PQF query string
     *
     * Optional:
     *   syntax       — USmarc | SUTRS | XML  (default: from target)
     *   element_set — F | B | S             (default: F)
     *   max_records — int 1-1000            (default: 100)
     */
    public function executeSearchExec(sfWebRequest $request)
    {
        $targetId = $request->getParameter('target_id');
        $query    = $request->getParameter('query');
        $maxRec   = (int) $request->getParameter('max_records', 100);

        $target = $this->getDb()->fetchOne(
            "SELECT * FROM z3950_targets WHERE id = ? AND active = 1",
            [$targetId]
        );

        if (! $target) {
            $this->getUser()->setFlash('error', 'Target not found or inactive.');
            return $this->redirect('/z3950/search');
        }

        if (! extension_loaded('yaz')) {
            $this->getUser()->setFlash('error',
                'The php-yaz extension is not installed. '
               .'Install with: pecl install yaz');
            return $this->redirect('/z3950/search');
        }

        $start     = microtime(true);
        $result    = $this->getZ3950Service()->search(
            $target['host'],
            (int) $target['port'],
            $target['database'],
            $query,
            $request->getParameter('syntax', $target['syntax'] ?: 'USmarc'),
            $request->getParameter('element_set', 'F'),
            $maxRec
        );
        $elapsed   = round((microtime(true) - $start) * 1000);

        // Log the query
        $this->getDb()->execute(
            "INSERT INTO z3950_query_log
             (target_id, query, syntax, result_count, elapsed_ms, error, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $target['id'],
                $query,
                $request->getParameter('syntax', 'USmarc'),
                $result['count'] ?? 0,
                $elapsed,
                $result['error'] ?? null,
            ]
        );

        if (! empty($result['error'])) {
            $this->getUser()->setFlash('error', "Search failed: {$result['error']}");
            return $this->redirect('/z3950/search');
        }

        // Store result set in session (30-minute TTL)
        $resultSet = bin2hex(random_bytes(16));
        $this->getUser()->setAttribute("z3950_rs_{$resultSet}", [
            'target_id'   => $target['id'],
            'records'     => $result['records'] ?? [],
            'syntax'      => $request->getParameter('syntax', $target['syntax'] ?: 'USmarc'),
            'element_set' => $request->getParameter('element_set', 'F'),
        ], 'symfony/user/sfUser');

        return $this->redirect('/z3950/result?resultSet=' . urlencode($resultSet));
    }

    /**
     * GET /z3950/result — browse a result set.
     *
     * Pre-parses MARC records for the view to show field-level data.
     */
    public function executeResult(sfWebRequest $request)
    {
        $resultSet = $request->getParameter('resultSet');
        $data = $this->getUser()->getAttribute("z3950_rs_{$resultSet}", null, 'symfony/user/sfUser');

        if (! $data) {
            $this->getUser()->setFlash('error', 'Result set expired. Run a new search.');
            return $this->redirect('/z3950/search');
        }

        $parsed = [];
        $service = $this->getZ3950Service();
        foreach ($data['records'] as $idx => $record) {
            $parsed[$idx] = $service->parseMarcRecord($record);
        }

        $this->resultSet  = $resultSet;
        $this->targetId   = $data['target_id'];
        $this->records    = $data['records'];
        $this->parsed    = $parsed;
        $this->syntax    = $data['syntax'];
        $this->elementSet = $data['element_set'];
    }

    // ─── Import ────────────────────────────────────────────────────────────

    /**
     * GET /z3950/import — import a single record.
     */
    public function executeImport(sfWebRequest $request)
    {
        $resultSet    = $request->getParameter('resultSet');
        $recordNumber = (int) $request->getParameter('recordNumber', 0);

        $data = $this->getUser()->getAttribute("z3950_rs_{$resultSet}", null, 'symfony/user/sfUser');

        if (! $data || ! isset($data['records'][$recordNumber])) {
            $this->getUser()->setFlash('error', 'Record not in result set.');
            return $this->redirect('/z3950/search');
        }

        $marcRecord = $data['records'][$recordNumber];
        $stats = $this->getZ3950Service()->importMarc($marcRecord, $data['syntax']);

        $this->getDb()->execute(
            "INSERT INTO z3950_import_log
             (target_id, result_set, record_number, marc_content, works_created,
              instances_created, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['target_id'],
                $resultSet,
                $recordNumber,
                $marcRecord,
                $stats['works'] ?? 0,
                $stats['instances'] ?? 0,
            ]
        );

        $this->getUser()->setFlash('success', sprintf(
            'Imported: %d work(s), %d instance(s).',
            $stats['works'] ?? 0,
            $stats['instances'] ?? 0
        ));

        return $this->redirect('/z3950/result?resultSet=' . urlencode($resultSet));
    }

    /**
     * POST /z3950/import/batch — batch import selected records.
     *
     * Parameters:
     *   result_set      — session key
     *   record_numbers  — "all" or comma-separated 0-based indices
     */
    public function executeImportBatch(sfWebRequest $request)
    {
        $resultSet = $request->getParameter('result_set');
        $data = $this->getUser()->getAttribute("z3950_rs_{$resultSet}", null, 'symfony/user/sfUser');

        if (! $data) {
            $this->getUser()->setFlash('error', 'Result set expired.');
            return $this->redirect('/z3950/search');
        }

        $recordNumbers = $request->getParameter('record_numbers', '');
        $numbers = $recordNumbers === 'all'
            ? array_keys($data['records'])
            : array_map('intval', array_filter(explode(',', $recordNumbers)));

        $totalWorks = 0;
        $totalInstances = 0;
        $imported = 0;
        $errors = [];

        foreach ($numbers as $n) {
            if (! isset($data['records'][$n])) { continue; }

            try {
                $stats = $this->getZ3950Service()->importMarc(
                    $data['records'][$n],
                    $data['syntax']
                );

                $totalWorks     += $stats['works'] ?? 0;
                $totalInstances += $stats['instances'] ?? 0;
                $imported++;

                $this->getDb()->execute(
                    "INSERT INTO z3950_import_log
                     (target_id, result_set, record_number, marc_content,
                      works_created, instances_created, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $data['target_id'],
                        $resultSet,
                        $n,
                        $data['records'][$n],
                        $stats['works'] ?? 0,
                        $stats['instances'] ?? 0,
                    ]
                );
            } catch (Exception $e) {
                $errors[] = "Record {$n}: {$e->getMessage()}";
            }
        }

        $msg = "Batch imported {$imported} record(s). "
             . "Created {$totalWorks} work(s), {$totalInstances} instance(s).";
        if ($errors) {
            $msg .= '  Errors: ' . implode('; ', $errors);
        }

        $this->getUser()->setFlash(empty($errors) ? 'success' : 'warning', $msg);
        return $this->redirect('/z3950/search');
    }

    // ─── Admin ─────────────────────────────────────────────────────────────

    public function executeAdmin(sfWebRequest $request)
    {
        $conn = $this->getDb();

        $this->targets = $conn->fetchAll("SELECT * FROM z3950_targets ORDER BY name");
        $this->recentQueries = $conn->fetchAll(
            "SELECT ql.*, t.name AS target_name
             FROM z3950_query_log ql
             LEFT JOIN z3950_targets t ON t.id = ql.target_id
             ORDER BY ql.created_at DESC
             LIMIT 20"
        );
        $this->recentImports = $conn->fetchAll(
            "SELECT * FROM z3950_import_log ORDER BY created_at DESC LIMIT 10"
        );
    }

    /**
     * GET /z3950/admin/targets/new — create target form.
     */
    public function executeNewTarget(sfWebRequest $request)
    {
        // placeholder; template renders the shared target-form partial
        $this->target = null;
        $this->action = 'create';
    }

    /**
     * POST /z3950/admin/targets — create a new target.
     */
    public function executeCreateTarget(sfWebRequest $request)
    {
        $this->getDb()->execute(
            "INSERT INTO z3950_targets
             (name, host, port, database, syntax, element_set, charset, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $request->getParameter('name'),
                $request->getParameter('host'),
                (int) $request->getParameter('port', 210),
                $request->getParameter('database'),
                $request->getParameter('syntax', 'USmarc'),
                $request->getParameter('element_set', 'F'),
                $request->getParameter('charset', 'UTF-8'),
                $request->getParameter('active') ? 1 : 0,
            ]
        );

        $this->getUser()->setFlash('success',
            'Target "' . $request->getParameter('name') . '" added.');
        return $this->redirect('/z3950/admin');
    }

    /**
     * DELETE /z3950/admin/targets/:id — remove a target.
     */
    public function executeDeleteTarget(sfWebRequest $request)
    {
        $id = $request->getParameter('id');
        $this->getDb()->execute("DELETE FROM z3950_targets WHERE id = ?", [$id]);
        $this->getUser()->setFlash('success', 'Target removed.');
        return $this->redirect('/z3950/admin');
    }

    // ─── SRU 2.0 HTTP handler ──────────────────────────────────────────────

    /**
     * GET /z3950/sru — SRU 2.0 dispatcher.
     *
     * Routes:
     *   ?operation=explain              → explain XML
     *   ?operation=searchRetrieve&query=...&startRecord=&maximumRecords=&recordSchema=
     *                                                        → searchRetrieveResponse XML
     *   anything else                   → diagnostic XML
     */
    public function executeSru(sfWebRequest $request)
    {
        $operation    = $request->getParameter('operation', 'explain');
        $version      = $request->getParameter('version', '2.0');
        $recordPacking = $request->getParameter('recordPacking', 'xml');

        if ($operation === 'explain') {
            return $this->renderXml($this->sruExplain($version));
        }

        if ($operation === 'searchRetrieve') {
            $query          = $request->getParameter('query', '');
            $startRecord   = max(1, (int) $request->getParameter('startRecord', 1));
            $maximumRecords = max(1, min((int) $request->getParameter('maximumRecords', 10), 100));
            $recordSchema  = $request->getParameter('recordSchema', 'marcxml');

            if ($query === '') {
                return $this->renderXml(
                    $this->diagnostic('mandatoryParameterNotSupplied', 'query', $version)
                );
            }

            return $this->renderSruSearchRetrieve($query, $startRecord, $maximumRecords, $recordSchema, $recordPacking, $version);
        }

        return $this->renderXml(
            $this->diagnostic('unsupportedOperation', $operation, $version)
        );
    }

    private function renderSruSearchRetrieve(
        string  $query,
        int     $startRecord,
        int     $maximumRecords,
        string  $recordSchema,
        string  $recordPacking,
        string  $version
    ): sfView {
        $this->setLayout(false);
        $this->setTemplate('');

        $service = new SruService();
        $result  = $service->searchRetrieve($query, $startRecord, $maximumRecords, $recordSchema);

        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<searchRetrieveResponse xmlns="http://docs.oasis-open.org/ns/search-ws/sruResponse">';
        $body .= '<version>' . htmlspecialchars($version) . '</version>';
        $body .= '<numberOfRecords>' . (int) $result['count'] . '</numberOfRecords>';

        if (! empty($result['diagnostic'])) {
            $body .= '<diagnostics><diagnostic><uri>info:srw/diagnostic/1/queryFeatureUnsupported</uri>';
            $body .= '<details>' . htmlspecialchars($result['diagnostic']) . '</details>';
            $body .= '</diagnostic></diagnostics>';
        } else {
            $body .= '<records>';
            $position = $startRecord;
            foreach ($result['records'] as $marc) {
                $data = $recordPacking === 'string'
                    ? '<recordData>' . htmlspecialchars($marc, ENT_XML1) . '</recordData>'
                    : '<recordData>' . $marc . '</recordData>';

                $body .= '<record>'
                    . '<recordSchema>' . htmlspecialchars($recordSchema, ENT_XML1) . '</recordSchema>'
                    . '<recordPacking>' . htmlspecialchars($recordPacking, ENT_XML1) . '</recordPacking>'
                    . $data
                    . '<recordPosition>' . $position . '</recordPosition>'
                    . '</record>';
                $position++;
            }
            $body .= '</records>';
        }

        $body .= '<echoedSearchRetrieveRequest>';
        $body .= '<version>' . htmlspecialchars($version) . '</version>';
        $body .= '<query>' . htmlspecialchars($query, ENT_XML1) . '</query>';
        $body .= '<startRecord>' . $startRecord . '</startRecord>';
        $body .= '<maximumRecords>' . $maximumRecords . '</maximumRecords>';
        $body .= '</echoedSearchRetrieveRequest>';
        $body .= '</searchRetrieveResponse>';

        $this->getResponse()->setHttpHeader('Content-Type', 'application/xml; charset=UTF-8');
        return $this->renderText($body);
    }

    private function sruExplain(string $version): string
    {
        $service = new SruService();
        $body    = '<?xml version="1.0" encoding="UTF-8"?>';
        $body   .= '<explainResponse xmlns="http://docs.oasis-open.org/ns/search-ws/sruResponse">';
        $body   .= '<version>' . htmlspecialchars($version) . '</version>';
        $body   .= '<record>';
        $body   .= '<recordSchema>http://explain.z3950.org/dtd/2.0/</recordSchema>';
        $body   .= '<recordPacking>xml</recordPacking>';
        $body   .= '<recordData>' . $service->explain() . '</recordData>';
        $body   .= '</record>';
        $body   .= '</explainResponse>';
        return $body;
    }

    private function diagnostic(string $code, string $details, string $version): string
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<searchRetrieveResponse xmlns="http://docs.oasis-open.org/ns/search-ws/sruResponse">';
        $body .= '<version>' . htmlspecialchars($version) . '</version>';
        $body .= '<numberOfRecords>0</numberOfRecords>';
        $body .= '<diagnostics><diagnostic xmlns="http://docs.oasis-open.org/ns/search-ws/diagnostic">';
        $body .= '<uri>info:srw/diagnostic/1/' . htmlspecialchars($code, ENT_XML1) . '</uri>';
        $body .= '<details>' . htmlspecialchars($details, ENT_XML1) . '</details>';
        $body .= '</diagnostic></diagnostics>';
        $body .= '</searchRetrieveResponse>';
        return $body;
    }

    private function renderXml(string $body): sfView
    {
        $this->setLayout(false);
        $this->setTemplate('');
        $this->getResponse()->setHttpHeader('Content-Type', 'application/xml; charset=UTF-8');
        return $this->renderText($body);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    protected function getDb(): PDO
    {
        return Doctrine_Manager::connection()->getDbh();
    }
}
