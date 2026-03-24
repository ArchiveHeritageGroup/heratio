<?php

namespace AhgCore\Services;

use AhgCore\Models\ClipboardSave;
use AhgCore\Models\ClipboardSaveItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ClipboardService
{
    /**
     * Entity type to class name mapping (matches AtoM convention).
     */
    protected static array $typeMap = [
        'informationObject' => 'QubitInformationObject',
        'actor'             => 'QubitActor',
        'repository'        => 'QubitRepository',
        'accession'         => 'QubitAccession',
    ];

    /**
     * Get all clipboard items from session.
     * Clipboard is localStorage-based on the frontend, but we keep a
     * server-side session mirror for operations like CSV export.
     */
    public function getItems(?int $userId): array
    {
        $items = Session::get('clipboard_items', []);

        // If session is empty but user has a saved clipboard in DB, auto-load it
        if ($userId && empty(array_filter($items))) {
            $latestSave = \Illuminate\Support\Facades\DB::table('clipboard_save')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->first();

            if ($latestSave) {
                $savedItems = \Illuminate\Support\Facades\DB::table('clipboard_save_item')
                    ->where('save_id', $latestSave->id)
                    ->get();

                $items = [
                    'informationObject' => [],
                    'actor'             => [],
                    'repository'        => [],
                ];

                foreach ($savedItems as $si) {
                    $type = lcfirst(str_replace('Qubit', '', $si->item_class_name));
                    if (isset($items[$type])) {
                        $items[$type][] = $si->slug;
                    }
                }

                // Cache in session so we don't hit DB every request
                Session::put('clipboard_items', $items);
            }
        }

        return $items;
    }

    /**
     * Add an item to the session clipboard.
     */
    public function addItem(string $slug, string $type, ?int $userId): bool
    {
        $items = Session::get('clipboard_items', []);

        if (!isset($items[$type])) {
            $items[$type] = [];
        }

        if (!in_array($slug, $items[$type])) {
            $items[$type][] = $slug;
        }

        Session::put('clipboard_items', $items);

        return true;
    }

    /**
     * Remove an item from the session clipboard.
     */
    public function removeItem(string $slug, string $type, ?int $userId): bool
    {
        $items = Session::get('clipboard_items', []);

        if (isset($items[$type])) {
            $items[$type] = array_values(array_diff($items[$type], [$slug]));
        }

        Session::put('clipboard_items', $items);

        return true;
    }

    /**
     * Clear all items from the session clipboard.
     */
    public function clearAll(?int $userId, ?string $type = null): bool
    {
        if ($type) {
            $items = Session::get('clipboard_items', []);
            $items[$type] = [];
            Session::put('clipboard_items', $items);
        } else {
            Session::put('clipboard_items', []);
        }

        return true;
    }

    /**
     * Count total items on the clipboard.
     */
    public function count(?int $userId): int
    {
        $items = Session::get('clipboard_items', []);
        $total = 0;

        foreach ($items as $type => $slugs) {
            $total += count($slugs);
        }

        return $total;
    }

    /**
     * Resolve slugs to display details (name, slug, type, link).
     */
    public function getItemDetails(array $allSlugs, string $culture = 'en'): array
    {
        $results = [];

        foreach ($allSlugs as $type => $slugs) {
            if (empty($slugs)) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($slugs), '?'));

            if ($type === 'informationObject') {
                $rows = DB::select("
                    SELECT s.slug, i18n.title AS name, 'informationObject' AS type
                    FROM slug s
                    JOIN information_object_i18n i18n ON i18n.id = s.object_id AND i18n.culture = ?
                    WHERE s.slug IN ({$placeholders})
                ", array_merge([$culture], $slugs));
            } elseif ($type === 'actor') {
                $rows = DB::select("
                    SELECT s.slug, i18n.authorized_form_of_name AS name, 'actor' AS type
                    FROM slug s
                    JOIN actor_i18n i18n ON i18n.id = s.object_id AND i18n.culture = ?
                    WHERE s.slug IN ({$placeholders})
                ", array_merge([$culture], $slugs));
            } elseif ($type === 'repository') {
                $rows = DB::select("
                    SELECT s.slug, i18n.authorized_form_of_name AS name, 'repository' AS type
                    FROM slug s
                    JOIN actor_i18n i18n ON i18n.id = s.object_id AND i18n.culture = ?
                    WHERE s.slug IN ({$placeholders})
                ", array_merge([$culture], $slugs));
            } elseif ($type === 'accession') {
                $rows = DB::select("
                    SELECT s.slug, ai.identifier AS name, 'accession' AS type
                    FROM slug s
                    JOIN accession ai ON ai.id = s.object_id
                    WHERE s.slug IN ({$placeholders})
                ", $slugs);
            } else {
                continue;
            }

            foreach ($rows as $row) {
                $row->name = $row->name ?? '(Untitled)';
                $results[] = $row;
            }
        }

        return $results;
    }

    /**
     * Save clipboard to database with a unique numeric password.
     */
    public function save(array $allSlugs, ?int $userId): array
    {
        // Validate that we have items
        $totalItems = 0;
        foreach ($allSlugs as $type => $slugs) {
            $totalItems += count($slugs);
        }

        if ($totalItems === 0) {
            return ['error' => 'No items in clipboard to save.'];
        }

        // Validate slugs exist in DB
        $validatedSlugs = $this->validateSlugs($allSlugs);
        if (empty($validatedSlugs)) {
            return ['error' => 'No valid items found.'];
        }

        // Generate unique password
        $password = $this->getUniquePassword();
        if (!$password) {
            return ['error' => 'Clipboard ID generation failure. Please try again.'];
        }

        // Create clipboard save record
        $save = ClipboardSave::create([
            'user_id'    => $userId,
            'password'   => $password,
            'created_at' => now(),
        ]);

        // Create clipboard save items
        $itemsCount = 0;
        foreach ($validatedSlugs as $type => $slugs) {
            foreach ($slugs as $slug) {
                ClipboardSaveItem::create([
                    'save_id'         => $save->id,
                    'item_class_name' => self::$typeMap[$type] ?? 'QubitInformationObject',
                    'slug'            => $slug,
                ]);
                $itemsCount++;
            }
        }

        return [
            'success'  => true,
            'password' => $password,
            'count'    => $itemsCount,
            'message'  => "Clipboard saved with {$itemsCount} item(s). Clipboard ID is <b>{$password}</b>. Please write this number down. When you want to reload this clipboard in the future, open the Clipboard menu, select Load clipboard, and enter this number in the Clipboard ID field.",
        ];
    }

    /**
     * Load saved clipboard by password.
     */
    public function load(string $password, string $mode = 'merge'): array
    {
        $clipboardSave = ClipboardSave::where('password', $password)->first();

        if (!$clipboardSave) {
            return ['error' => 'Clipboard ID not found.'];
        }

        $items = ClipboardSaveItem::where('save_id', $clipboardSave->id)->get();

        $clipboard = [
            'informationObject' => [],
            'actor'             => [],
            'repository'        => [],
        ];

        $addedCount = 0;

        foreach ($items as $item) {
            // Verify the slug still exists
            $exists = DB::selectOne("SELECT COUNT(*) AS cnt FROM slug WHERE slug = ?", [$item->slug]);

            if ($exists && $exists->cnt > 0) {
                $type = lcfirst(str_replace('Qubit', '', $item->item_class_name));

                if (isset($clipboard[$type])) {
                    $clipboard[$type][] = $item->slug;
                    $addedCount++;
                }
            }
        }

        $actionDesc = ($mode === 'replace') ? 'added' : 'merged with current clipboard';

        return [
            'success'   => true,
            'clipboard' => $clipboard,
            'count'     => $addedCount,
            'message'   => "Clipboard {$password} loaded, {$addedCount} records {$actionDesc}.",
        ];
    }

    /**
     * Export clipboard items as CSV.
     */
    public function exportCsv(array $allSlugs, string $culture = 'en'): string
    {
        $items = $this->getItemDetails($allSlugs, $culture);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Name', 'Type', 'Slug', 'URL']);

        foreach ($items as $item) {
            $url = '';
            if ($item->type === 'informationObject') {
                $url = url("/{$item->slug}");
            } elseif ($item->type === 'actor') {
                $url = url("/actor/{$item->slug}");
            } elseif ($item->type === 'repository') {
                $url = url("/repository/{$item->slug}");
            } elseif ($item->type === 'accession') {
                $url = url("/accession/{$item->slug}");
            }

            fputcsv($output, [
                $item->name,
                $item->type,
                $item->slug,
                $url,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Sync session clipboard from frontend localStorage data.
     */
    public function syncFromClient(array $items): void
    {
        Session::put('clipboard_items', $items);
    }

    /**
     * Validate that slugs exist in the database.
     */
    protected function validateSlugs(array $allSlugs): array
    {
        $validated = [];

        foreach ($allSlugs as $type => $slugs) {
            if (empty($slugs) || !isset(self::$typeMap[$type])) {
                continue;
            }

            $className = self::$typeMap[$type];
            $validated[$type] = [];

            foreach ($slugs as $slug) {
                $count = DB::selectOne("
                    SELECT COUNT(s.id) AS cnt
                    FROM slug s
                    JOIN object o ON s.object_id = o.id
                    WHERE s.slug = ? AND o.class_name = ?
                ", [$slug, $className]);

                if ($count && $count->cnt > 0) {
                    $validated[$type][] = $slug;
                }
            }
        }

        // Check we have at least one validated slug
        $hasItems = false;
        foreach ($validated as $slugs) {
            if (!empty($slugs)) {
                $hasItems = true;
                break;
            }
        }

        return $hasItems ? $validated : [];
    }

    /**
     * Generate a unique 7-digit numeric password.
     */
    protected function getUniquePassword(): ?string
    {
        for ($i = 0; $i < 100; $i++) {
            $password = str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);

            $exists = ClipboardSave::where('password', $password)->exists();
            if (!$exists) {
                return $password;
            }
        }

        return null;
    }
}
