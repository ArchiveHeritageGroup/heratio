<?php

/**
 * SolutionsController - the buyer pages on heratio.org (H03): one page per
 * kind of institution plus migration from AtoM. The pages are views in the
 * repository, not database content, so they are versioned with the code they
 * describe and the nightly demo reset cannot change them.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Http\Controllers;

use App\Support\CapabilityMatrix;
use App\Support\DemoTour;

class SolutionsController extends Controller
{
    /** slug => [title, one-line summary]; the order is the order on the index. */
    public const PAGES = [
        'archives' => ['Heratio for archives', 'Arrangement and description, authority records, preservation and public access.'],
        'museums' => ['Heratio for museums', 'Object cataloguing, collections procedures, condition and loans, 3D and exhibitions.'],
        'libraries' => ['Heratio for libraries', 'Cataloguing, copy cataloguing, circulation and acquisitions.'],
        'integrated-glam' => ['Integrated GLAM', 'Archives, museum, library and gallery collections in one system.'],
        'migrating-from-atom' => ['Migrating from AtoM', 'Run Heratio over an existing AtoM database, or move into a fresh install.'],
    ];

    public function index()
    {
        return view('solutions.index', ['pages' => self::PAGES]);
    }

    public function show(string $page)
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        return view('solutions.'.$page, [
            'page' => $page, 'meta' => self::PAGES[$page], 'pages' => self::PAGES,
            'capabilities' => CapabilityMatrix::releasedFor($page),
        ]);
    }

    /** H06: the guided demo tour, on the demo hosts only. */
    public function tour()
    {
        abort_unless(DemoTour::enabled(), 404);

        return view('solutions.tour', ['steps' => DemoTour::steps()]);
    }

    /**
     * H07: install, upgrade, demonstration and security documentation, every link
     * pinned to the git tag of the running release so the text matches the code.
     */
    public function documentation()
    {
        $version = json_decode((string) @file_get_contents(base_path('version.json')), true)['version'] ?? '';
        $ref = $version !== '' ? 'v'.$version : 'main';
        $doc = fn (string $path) => 'https://github.com/ArchiveHeritageGroup/heratio/blob/'.$ref.'/'.$path;

        return view('solutions.documentation', [
            'version' => $version,
            'releaseUrl' => $version !== '' ? 'https://github.com/ArchiveHeritageGroup/heratio/releases/tag/'.$ref : 'https://github.com/ArchiveHeritageGroup/heratio/releases',
            'sections' => [
                ['Install', [
                    ['Docker quickstart', $doc('README.md').'#3a-client-install-prebuilt-image-no-source', 'One command pulls the image for this release with MySQL and Elasticsearch and starts it.'],
                    ['Standalone install on Linux', $doc('docs/standalone-install-howto.md'), 'A fresh server with PHP 8.3, MySQL 8 and Elasticsearch 8, set up by bin/install.'],
                    ['Over an existing AtoM database', $doc('docs/overlay-install-howto.md'), 'Heratio adds its own tables beside AtoM\'s and leaves every AtoM row in place.'],
                    ['Requirements', $doc('README.md').'#requirements', 'Server software and versions.'],
                ]],
                ['Upgrade', [
                    ['Upgrading Heratio', $doc('docs/upgrade.md'), 'Back up, move to a newer release tag or image, and let the schema catch up.'],
                ]],
                ['Security', [
                    ['Security policy', $doc('SECURITY.md'), 'How to report a vulnerability, which versions get fixes, and what Heratio protects.'],
                    ['Operator security runbook', $doc('docs/security.md'), 'Checks before going public, routine tasks, incident response and ports.'],
                ]],
            ],
            'tour' => DemoTour::enabled(),
        ]);
    }

    /** H04: every capability with its status and a proof link. */
    public function capabilities()
    {
        return view('solutions.capabilities', ['rows' => CapabilityMatrix::rows(), 'pages' => self::PAGES]);
    }
}
