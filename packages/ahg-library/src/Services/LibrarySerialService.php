<?php

/**
 * LibrarySerialService - serial holdings + issue tracking
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

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the /library-manage/serials surface. Serial titles + their per-issue
 * holdings. Listing is enriched with issue_count so the index template renders
 * a usable count without per-row lookups.
 */
class LibrarySerialService
{
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_CEASED     = 'ceased';
    public const STATUS_SUSPENDED   = 'suspended';

    public const FREQUENCY_WEEKLY     = 'weekly';
    public const FREQUENCY_BIWEEKLY   = 'biweekly';
    public const FREQUENCY_MONTHLY    = 'monthly';
    public const FREQUENCY_BIMONTHLY  = 'bimonthly';
    public const FREQUENCY_QUARTERLY  = 'quarterly';
    public const FREQUENCY_SEMIANNUAL = 'semiannual';
    public const FREQUENCY_ANNUAL     = 'annual';
    public const FREQUENCY_IRREGULAR  = 'irregular';

    public const FREQUENCIES = [
        self::FREQUENCY_WEEKLY     => 'Weekly',
        self::FREQUENCY_BIWEEKLY   => 'Biweekly',
        self::FREQUENCY_MONTHLY    => 'Monthly',
        self::FREQUENCY_BIMONTHLY  => 'Bimonthly',
        self::FREQUENCY_QUARTERLY  => 'Quarterly',
        self::FREQUENCY_SEMIANNUAL => 'Semi-annual',
        self::FREQUENCY_ANNUAL     => 'Annual',
        self::FREQUENCY_IRREGULAR  => 'Irregular',
    ];

    private const MONTHS = [
        1  => 'Jan',  2  => 'Feb',  3  => 'Mar',  4  => 'Apr',
        5  => 'May',  6  => 'Jun',  7  => 'Jul',  8  => 'Aug',
        9  => 'Sep', 10  => 'Oct', 11  => 'Nov', 12  => 'Dec',
    ];

    // ── Core CRUD ────────────────────────────────────────────────────────

    public function list(array $filters = []): array
    {
        if (!Schema::hasTable('library_serial')) {
            return [];
        }

        $q = DB::table('library_serial');

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['frequency'])) {
            $q->where('frequency', $filters['frequency']);
        }
        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('title', 'LIKE', $needle)
                    ->orWhere('issn', 'LIKE', $needle)
                    ->orWhere('publisher', 'LIKE', $needle);
            });
        }

        $rows = $q->orderBy('title')->get()->all();

        if ($rows && Schema::hasTable('library_serial_issue')) {
            $ids = array_map(static fn($r) => (int) $r->id, $rows);
            $counts = DB::table('library_serial_issue')
                ->select('serial_id', DB::raw('COUNT(*) as cnt'))
                ->whereIn('serial_id', $ids)
                ->groupBy('serial_id')
                ->pluck('cnt', 'serial_id')
                ->all();
            foreach ($rows as $r) {
                $r->issue_count = (int) ($counts[$r->id] ?? 0);
            }
        }

        return $rows;
    }

    public function get(int $id): ?object
    {
        if (!Schema::hasTable('library_serial')) {
            return null;
        }
        $row = DB::table('library_serial')->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        $row->issues = Schema::hasTable('library_serial_issue')
            ? DB::table('library_serial_issue')
                ->where('serial_id', $id)
                ->orderByDesc('issue_date')
                ->get()
                ->all()
            : [];
        return $row;
    }

    public function create(array $data): int
    {
        if (!Schema::hasTable('library_serial')) {
            return 0;
        }
        $now = now();
        $row = [
            'title'      => $data['title'] ?? '',
            'issn'       => $data['issn'] ?? '',
            'frequency'  => $data['frequency'] ?? '',
            'publisher'  => $data['publisher'] ?? '',
            'status'     => $data['status'] ?? self::STATUS_ACTIVE,
            'notes'      => $data['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        return (int) DB::table('library_serial')->insertGetId($row);
    }

    public function update(int $id, array $data): bool
    {
        if (!Schema::hasTable('library_serial')) {
            return false;
        }
        $payload = array_intersect_key($data, array_flip([
            'title', 'issn', 'frequency', 'publisher', 'status', 'notes',
        ]));
        if (!$payload) {
            return false;
        }
        $payload['updated_at'] = now();
        return DB::table('library_serial')->where('id', $id)->update($payload) > 0;
    }

    public function delete(int $id): bool
    {
        if (!Schema::hasTable('library_serial')) {
            return false;
        }
        if (Schema::hasTable('library_serial_issue')) {
            DB::table('library_serial_issue')->where('serial_id', $id)->delete();
        }
        return DB::table('library_serial')->where('id', $id)->delete() > 0;
    }

    public function addIssue(int $serialId, array $data): int
    {
        if (!Schema::hasTable('library_serial_issue')) {
            return 0;
        }
        $now = now();
        $row = [
            'serial_id'    => $serialId,
            'volume'       => $data['volume'] ?? '',
            'issue_number' => $data['issue_number'] ?? '',
            'issue_date'   => $data['issue_date'] ?? $now->toDateString(),
            'received_at'  => $data['received_at'] ?? null,
            'status'       => $data['status'] ?? 'received',
            'notes'        => $data['notes'] ?? null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        return (int) DB::table('library_serial_issue')->insertGetId($row);
    }

    // ── Prediction ────────────────────────────────────────────────────────

    /**
     * Predict the next expected issue date based on the last received issue
     * and the serial's frequency. Returns null if no usable issue exists.
     */
    public function predictNextIssue(int $serialId): ?\DateTime
    {
        $serial = $this->get($serialId);
        if (!$serial || empty($serial->issues)) {
            return null;
        }

        // Find latest issue with a valid received date
        $received = null;
        foreach ($serial->issues as $issue) {
            if (!empty($issue->received_at)) {
                $received = $issue;
                break;
            }
        }

        if (!$received || empty($received->issue_date)) {
            // Fall back to the most recent issue regardless of received_at
            $latest = $serial->issues[0] ?? null;
            if (!$latest || empty($latest->issue_date)) {
                return null;
            }
            $lastDate = new \DateTime($latest->issue_date);
        } else {
            $lastDate = new \DateTime($received->issue_date);
        }

        $frequency = $serial->frequency ?? self::FREQUENCY_IRREGULAR;

        return match ($frequency) {
            self::FREQUENCY_WEEKLY     => (clone $lastDate)->modify('+7 days'),
            self::FREQUENCY_BIWEEKLY   => (clone $lastDate)->modify('+14 days'),
            self::FREQUENCY_MONTHLY    => (clone $lastDate)->modify('first day of +1 month'),
            self::FREQUENCY_BIMONTHLY  => (clone $lastDate)->modify('first day of +2 months'),
            self::FREQUENCY_QUARTERLY  => (clone $lastDate)->modify('first day of +3 months'),
            self::FREQUENCY_SEMIANNUAL => (clone $lastDate)->modify('first day of +6 months'),
            self::FREQUENCY_ANNUAL     => (clone $lastDate)->modify('first day of +1 year'),
            default                    => (clone $lastDate)->modify('first day of +3 months'),
        };
    }

    /**
     * Return the expected interval in days for a given frequency.
     */
    private function intervalDays(string $frequency): int
    {
        return match ($frequency) {
            self::FREQUENCY_WEEKLY     => 7,
            self::FREQUENCY_BIWEEKLY   => 14,
            self::FREQUENCY_MONTHLY    => 30,
            self::FREQUENCY_BIMONTHLY  => 60,
            self::FREQUENCY_QUARTERLY  => 90,
            self::FREQUENCY_SEMIANNUAL => 180,
            self::FREQUENCY_ANNUAL     => 365,
            default                    => 90,
        };
    }

    /**
     * Produce a list of upcoming predicted issues for the next $months months.
     *
     * @return array<array{volume:string,issue_number:string,expected_date:string,days_until:int}>
     */
    public function getExpectedIssues(int $serialId, int $months = 6): array
    {
        $serial = $this->get($serialId);
        if (!$serial) {
            return [];
        }

        $frequency = $serial->frequency ?? self::FREQUENCY_IRREGULAR;
        $intervalDays = $this->intervalDays($frequency);
        $issues = $serial->issues ?? [];

        // Determine starting point: last received issue or last issue with a date
        $startDate = null;
        foreach ($issues as $issue) {
            if (!empty($issue->issue_date)) {
                $startDate = new \DateTime($issue->issue_date);
                break;
            }
        }

        if (!$startDate) {
            return [];
        }

        // Advance from the last known issue by one full interval
        $nextDate = match ($frequency) {
            self::FREQUENCY_WEEKLY     => (clone $startDate)->modify('+7 days'),
            self::FREQUENCY_BIWEEKLY   => (clone $startDate)->modify('+14 days'),
            self::FREQUENCY_MONTHLY    => (clone $startDate)->modify('first day of +1 month'),
            self::FREQUENCY_BIMONTHLY  => (clone $startDate)->modify('first day of +2 months'),
            self::FREQUENCY_QUARTERLY  => (clone $startDate)->modify('first day of +3 months'),
            self::FREQUENCY_SEMIANNUAL => (clone $startDate)->modify('first day of +6 months'),
            self::FREQUENCY_ANNUAL     => (clone $startDate)->modify('first day of +1 year'),
            default                    => (clone $startDate)->modify('first day of +3 months'),
        };

        $now = new \DateTime('today');
        $limitDate = (clone $now)->modify("+{$months} months");
        $result = [];

        // Detect running volume/issue numbers from existing issues
        $volumes = array_column($issues, 'volume');
        $issueNums = array_column($issues, 'issue_number');
        $lastVol = $volumes[0] ?? '';
        $lastNum = $issueNums[0] ?? '';

        $count = 0;
        while ($nextDate <= $limitDate && $count < $months * 4) {
            $daysUntil = $now->diff($nextDate)->days;
            $vol = $lastVol;
            $num = $lastNum;

            // Try to increment issue number
            $numInt = is_numeric($lastNum) ? (int) $lastNum + 1 : $count + 1;
            $num = (string) $numInt;

            $result[] = [
                'volume'        => $vol,
                'issue_number'  => $num,
                'expected_date' => $nextDate->format('Y-m-d'),
                'days_until'    => $daysUntil,
            ];

            // Advance to next expected date
            $nextDate = match ($frequency) {
                self::FREQUENCY_WEEKLY     => (clone $nextDate)->modify('+7 days'),
                self::FREQUENCY_BIWEEKLY   => (clone $nextDate)->modify('+14 days'),
                self::FREQUENCY_MONTHLY    => (clone $nextDate)->modify('first day of +1 month'),
                self::FREQUENCY_BIMONTHLY  => (clone $nextDate)->modify('first day of +2 months'),
                self::FREQUENCY_QUARTERLY  => (clone $nextDate)->modify('first day of +3 months'),
                self::FREQUENCY_SEMIANNUAL => (clone $nextDate)->modify('first day of +6 months'),
                self::FREQUENCY_ANNUAL     => (clone $nextDate)->modify('first day of +1 year'),
                default                    => (clone $nextDate)->modify('first day of +3 months'),
            };

            $count++;
        }

        return $result;
    }

    // ── Subscription ──────────────────────────────────────────────────────

    /**
     * Ensure the subscription table exists and return the row for a serial.
     */
    public function getSubscriptionData(int $serialId): ?object
    {
        $this->ensureSubscriptionTable();
        return DB::table('library_serial_subscription')
            ->where('serial_id', $serialId)
            ->first();
    }

    /**
     * Save (upsert) subscription data for a serial.
     */
    public function saveSubscription(int $serialId, array $data): bool
    {
        $this->ensureSubscriptionTable();

        $allowed = [
            'subscription_start', 'subscription_end', 'subscription_cost',
            'notification_email', 'auto_claim_max', 'notes',
        ];
        $payload = array_intersect_key($data, array_flip($allowed));
        $payload['updated_at'] = now();

        $exists = DB::table('library_serial_subscription')
            ->where('serial_id', $serialId)
            ->exists();

        if ($exists) {
            return DB::table('library_serial_subscription')
                ->where('serial_id', $serialId)
                ->update($payload) > 0;
        }

        $payload['serial_id'] = $serialId;
        $payload['created_at'] = now();
        DB::table('library_serial_subscription')->insert($payload);

        return true;
    }

    private function ensureSubscriptionTable(): void
    {
        if (Schema::hasTable('library_serial_subscription')) {
            return;
        }

        DB::statement("
            CREATE TABLE IF NOT EXISTS library_serial_subscription (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                serial_id BIGINT NOT NULL,
                subscription_start DATE NULL,
                subscription_end DATE NULL,
                subscription_cost DECIMAL(10,2) NULL,
                notification_email VARCHAR(255) NULL,
                auto_claim_max TINYINT UNSIGNED DEFAULT 3,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY serial_id_unique (serial_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ── Overdue / claims ──────────────────────────────────────────────────

    /**
     * Return serials that have an overdue expected issue (1.5x interval past
     * the predicted date) while their subscription is still active.
     *
     * @return array<array{serial:object,predicted_date:string,days_late:int,subscription_end:string|null}>
     */
    public function listOverdueClaims(): array
    {
        $this->ensureSubscriptionTable();

        if (!Schema::hasTable('library_serial')) {
            return [];
        }

        $today = now()->toDateString();
        $actives = DB::table('library_serial')
            ->where('status', self::STATUS_ACTIVE)
            ->get()
            ->all();

        $claims = [];

        foreach ($actives as $serial) {
            $subscription = $this->getSubscriptionData((int) $serial->id);

            // Skip if subscription has expired
            if (!$subscription || ($subscription->subscription_end ?? null) < $today) {
                continue;
            }

            $frequency = $serial->frequency ?? self::FREQUENCY_IRREGULAR;
            $intervalDays = $this->intervalDays($frequency);
            // overdue threshold = 1.5x the expected interval
            $thresholdDays = (int) floor($intervalDays * 1.5);

            $predicted = $this->predictNextIssue((int) $serial->id);
            if (!$predicted) {
                continue; // cannot predict without any issues
            }

            $predictedStr = $predicted->format('Y-m-d');
            $expectedDeadline = (clone $predicted)->modify("+{$thresholdDays} days");
            $todayDt = new \DateTime('today');

            if ($todayDt > $expectedDeadline) {
                $daysLate = (int) $todayDt->diff($expectedDeadline)->days;
                $claims[] = [
                    'serial'          => $serial,
                    'predicted_date'  => $predictedStr,
                    'days_late'       => $daysLate,
                    'subscription_end'=> $subscription->subscription_end ?? null,
                ];
            }
        }

        return $claims;
    }

    // ── Lifespan ─────────────────────────────────────────────────────────

    /**
     * Calculate the lifespan / active years of a serial.
     */
    public function calculateLifespan(int $serialId): ?array
    {
        if (!Schema::hasTable('library_serial_issue')) {
            return null;
        }

        $issues = DB::table('library_serial_issue')
            ->where('serial_id', $serialId)
            ->whereNotNull('issue_date')
            ->orderBy('issue_date')
            ->get()
            ->all();

        if (!$issues) {
            return null;
        }

        $years = [];
        foreach ($issues as $issue) {
            if ($issue->issue_date) {
                $y = (int) substr($issue->issue_date, 0, 4);
                $years[$y] = true;
            }
        }

        if (!$years) {
            return null;
        }

        ksort($years);
        $yearKeys = array_keys($years);
        $startYear = $yearKeys[0];
        $endYear = end($yearKeys);
        $activeYears = count($years);
        $totalIssues = count($issues);

        return [
            'start_year'   => $startYear,
            'end_year'     => $endYear,
            'total_issues' => $totalIssues,
            'active_years' => $activeYears,
            'status'       => $activeYears > 0 ? 'active' : 'unknown',
        ];
    }

    // ── Issue history ────────────────────────────────────────────────────

    /**
     * Return issue history, optionally filtered by year, with gap analysis.
     *
     * @return array<array{issue:object,gap_days:int|null}>
     */
    public function getIssueHistory(int $serialId, ?int $year = null): array
    {
        if (!Schema::hasTable('library_serial_issue')) {
            return [];
        }

        $q = DB::table('library_serial_issue')
            ->where('serial_id', $serialId)
            ->orderBy('issue_date');

        if ($year !== null) {
            $q->whereRaw('YEAR(issue_date) = ?', [$year]);
        }

        $issues = $q->get()->all();

        if (!$issues) {
            return [];
        }

        $result = [];
        $prevDate = null;

        foreach ($issues as $issue) {
            $gapDays = null;
            if ($prevDate !== null && $issue->issue_date) {
                try {
                    $gapDays = (int) (new \DateTime($prevDate))->diff(new \DateTime($issue->issue_date))->days;
                } catch (\Exception) {
                    $gapDays = null;
                }
            }

            $result[] = [
                'issue'    => $issue,
                'gap_days' => $gapDays,
            ];

            if ($issue->issue_date) {
                $prevDate = $issue->issue_date;
            }
        }

        return $result;
    }

    // ── Coverage ─────────────────────────────────────────────────────────

    /**
     * Return coverage statistics for a serial.
     */
    public function getCoverageStats(int $serialId): array
    {
        if (!Schema::hasTable('library_serial_issue')) {
            return [
                'total_expected_years' => 0,
                'active_years'         => 0,
                'complete_pct'         => 0,
                'missing_count'        => 0,
                'received_count'       => 0,
                'claimed_count'        => 0,
                'total_count'          => 0,
            ];
        }

        $serial = $this->get($serialId);
        $issues = $serial->issues ?? [];

        $receivedCount = 0;
        $claimedCount  = 0;
        $years = [];

        foreach ($issues as $issue) {
            if ($issue->status === 'received') {
                $receivedCount++;
            }
            if ($issue->status === 'claimed') {
                $claimedCount++;
            }
            if ($issue->issue_date) {
                $y = (int) substr($issue->issue_date, 0, 4);
                $years[$y] = true;
            }
        }

        $activeYears = count($years);
        $frequency = $serial->frequency ?? self::FREQUENCY_IRREGULAR;
        $issuesPerYear = match ($frequency) {
            self::FREQUENCY_WEEKLY     => 52,
            self::FREQUENCY_BIWEEKLY   => 26,
            self::FREQUENCY_MONTHLY    => 12,
            self::FREQUENCY_BIMONTHLY  => 6,
            self::FREQUENCY_QUARTERLY  => 4,
            self::FREQUENCY_SEMIANNUAL => 2,
            self::FREQUENCY_ANNUAL     => 1,
            default                    => 6,
        };

        $expectedCount = $activeYears * $issuesPerYear;
        $totalCount = count($issues);
        $missingCount = max(0, $expectedCount - $receivedCount);
        $completePct = $expectedCount > 0 ? round(($totalCount / $expectedCount) * 100, 1) : 0;

        return [
            'total_expected_years' => $expectedCount > 0 ? $activeYears : 0,
            'active_years'         => $activeYears,
            'complete_pct'         => $completePct,
            'missing_count'       => $missingCount,
            'received_count'      => $receivedCount,
            'claimed_count'       => $claimedCount,
            'total_count'         => $totalCount,
        ];
    }

    // ── Enrichment ───────────────────────────────────────────────────────

    /**
     * Attach subscription fields to a serial object.
     */
    public function enrichWithSubscriptionInfo(object $serial): object
    {
        $sub = $this->getSubscriptionData((int) $serial->id);
        $serial->subscription_start  = $sub->subscription_start  ?? null;
        $serial->subscription_end    = $sub->subscription_end    ?? null;
        $serial->subscription_cost   = $sub->subscription_cost   ?? null;
        $serial->notification_email = $sub->notification_email ?? null;
        $serial->auto_claim_max     = $sub->auto_claim_max     ?? null;
        $serial->subscription_notes = $sub->notes               ?? null;
        return $serial;
    }

    // ── Clone ────────────────────────────────────────────────────────────

    /**
     * Clone a serial (title + issues with received_at cleared) and return
     * the new serial ID.
     */
    public function cloneSerial(int $serialId, array $overrides = []): int
    {
        $original = $this->get($serialId);
        if (!$original) {
            return 0;
        }

        $newData = [
            'title'     => ($original->title ?? '') . ' (clone)',
            'issn'      => $original->issn ?? '',
            'frequency' => $original->frequency ?? '',
            'publisher' => $original->publisher ?? '',
            'status'    => self::STATUS_ACTIVE,
            'notes'     => $original->notes ?? '',
        ];
        $newData = array_merge($newData, $overrides);

        $newId = $this->create($newData);
        if ($newId <= 0) {
            return 0;
        }

        // Copy issues without received_at
        if (Schema::hasTable('library_serial_issue') && !empty($original->issues)) {
            $now = now();
            foreach ($original->issues as $issue) {
                DB::table('library_serial_issue')->insert([
                    'serial_id'    => $newId,
                    'volume'       => $issue->volume ?? '',
                    'issue_number' => $issue->issue_number ?? '',
                    'issue_date'   => $issue->issue_date ?? null,
                    'received_at'  => null,
                    'status'       => $issue->status ?? 'missing',
                    'notes'        => $issue->notes ?? null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }

        return $newId;
    }

    // ── Batch status sync ────────────────────────────────────────────────

    /**
     * Scan active serials and mark those that have gone past 1.5x their
     * expected interval with no new issues as 'ceased'.
     *
     * @return int Number of serials updated
     */
    public function syncSerialsStatus(): int
    {
        if (!Schema::hasTable('library_serial')) {
            return 0;
        }

        $actives = DB::table('library_serial')
            ->where('status', self::STATUS_ACTIVE)
            ->get()
            ->all();

        $updated = 0;

        foreach ($actives as $serial) {
            $frequency = $serial->frequency ?? self::FREQUENCY_IRREGULAR;
            $intervalDays = $this->intervalDays($frequency);
            $thresholdDays = (int) floor($intervalDays * 1.5);

            $predicted = $this->predictNextIssue((int) $serial->id);
            if (!$predicted) {
                continue;
            }

            $deadline = (clone $predicted)->modify("+{$thresholdDays} days");
            if (now() > $deadline) {
                // Verify there really are no issues received after the predicted date
                $recentIssue = DB::table('library_serial_issue')
                    ->where('serial_id', $serial->id)
                    ->whereNotNull('received_at')
                    ->where('received_at', '>=', $predicted->format('Y-m-d'))
                    ->exists();

                if (!$recentIssue) {
                    DB::table('library_serial')
                        ->where('id', $serial->id)
                        ->update(['status' => self::STATUS_CEASED, 'updated_at' => now()]);
                    $updated++;
                }
            }
        }

        return $updated;
    }
}