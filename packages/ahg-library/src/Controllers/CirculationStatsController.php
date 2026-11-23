<?php

/**
 * CirculationStatsController - circulation statistics screen and exports (#1476).
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

namespace AhgLibrary\Controllers;

use AhgCore\Services\AclService;
use AhgCore\Support\DropdownOptions;
use AhgLibrary\Services\LibraryCirculationStatsService;
use AhgLibrary\Support\LibraryBranch;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CirculationStatsController extends Controller
{
    /** A wider range is a heavy query on the live loans table. */
    private const MAX_RANGE_DAYS = 1830;

    public function __construct(private LibraryCirculationStatsService $stats)
    {
    }

    public function index(Request $request)
    {
        [$report, $filters, $periods, $allowed] = $this->report($request);

        return view('ahg-library::reports.circulation', [
            'report' => $report,
            'filters' => $filters,
            'periods' => $periods,
            'branchOptions' => $allowed === null
                ? (LibraryBranch::available() ? LibraryBranch::options() : [])
                : array_intersect_key(LibraryBranch::options(), array_flip($allowed)),
            'allBranchesAllowed' => $allowed === null,
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        [$report] = $this->report($request);
        $name = sprintf('circulation-%s-%s', $report['period']['from'], $report['period']['to']);

        if ($format === 'csv') {
            return response($this->stats->toCsv($report), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $name . '.csv"',
            ]);
        }

        return response($this->stats->toPdf($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $name . '.pdf"',
        ]);
    }

    /** @return array{0:array, 1:array, 2:array, 3:?array} */
    private function report(Request $request): array
    {
        // The dropdown is editable; only codes the report can calculate are offered.
        $periods = array_intersect_key(
            DropdownOptions::resolve('library_report_period', []),
            array_flip(LibraryCirculationStatsService::PERIODS)
        );

        $validator = Validator::make($request->query(), [
            'period' => ['nullable', 'string', Rule::in(array_keys($periods))],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch' => ['nullable', 'integer', 'min:1'],
        ]);
        $validator->after(function ($v) use ($request) {
            $from = $request->query('from');
            $to = $request->query('to');
            if (in_array($request->query('period'), [null, '', 'custom'], true)
                && ! $v->errors()->hasAny(['from', 'to']) && is_string($from) && is_string($to)
                && CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > self::MAX_RANGE_DAYS) {
                $v->errors()->add('to', __('The report period cannot be longer than five years.'));
            }
        });
        $input = $validator->validate();

        $code = $input['period'] ?? (! empty($input['from']) && ! empty($input['to']) ? 'custom' : 'monthly');
        [$from, $to] = $this->stats->resolvePeriod($code, $input['from'] ?? null, $input['to'] ?? null);

        $allowed = $this->allowedBranches();
        $branch = isset($input['branch']) ? (int) $input['branch'] : null;
        if ($allowed === [] || ($branch !== null && $allowed !== null && ! in_array($branch, $allowed, true))) {
            abort(403, 'Insufficient permissions');
        }

        $scope = $branch !== null ? [$branch] : $allowed;

        return [
            $this->stats->build($from, $to, $scope),
            ['period' => $code, 'from' => $from, 'to' => $to, 'branch' => $branch],
            $periods,
            $allowed,
        ];
    }

    /**
     * Branches this operator may report on; null means no restriction. A
     * branch is a repository, so a repository deny rule applies here too, and
     * an operator an administrator has locked to a branch sees only that one,
     * and nothing at all if that branch is denied to them.
     *
     * @return int[]|null
     */
    private function allowedBranches(): ?array
    {
        if (! LibraryBranch::available()) {
            return null;
        }

        $userId = Auth::id();

        if (LibraryBranch::operatorLocked() && ($own = LibraryBranch::operatorBranchId()) !== null) {
            return $this->mayReportOn($userId, $own) ? [$own] : [];
        }

        if (AclService::repositoryRules($userId) === [] || AclService::isAdministrator()) {
            return null;
        }

        return array_values(array_filter(
            array_keys(LibraryBranch::options()),
            fn ($id) => $this->mayReportOn($userId, (int) $id)
        ));
    }

    /**
     * The screen needs the update right, and it reads the branch's data, so an
     * explicit read deny on the branch also removes it. Read is checked as a
     * deny only: staff commonly hold update with no read row of their own.
     */
    private function mayReportOn(?int $userId, int $branchId): bool
    {
        if (AclService::isAdministrator()) {
            return true;
        }

        return AclService::scopedDecision($userId, 'read', $branchId) !== false
            && AclService::hasPermission($userId, 'update', $branchId);
    }
}
