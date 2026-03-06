<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateSlugsCommand extends Command
{
    protected $signature = 'heratio:slugs:generate';

    protected $description = 'Generate missing slugs for objects that don\'t have one';

    /**
     * Map of class names to the i18n table and title column used for slug generation.
     */
    private array $classSlugSources = [
        'QubitInformationObject' => ['table' => 'information_object_i18n', 'column' => 'title'],
        'QubitActor' => ['table' => 'actor_i18n', 'column' => 'authorized_form_of_name'],
        'QubitRepository' => ['table' => 'actor_i18n', 'column' => 'authorized_form_of_name'],
        'QubitTerm' => ['table' => 'term_i18n', 'column' => 'name'],
        'QubitAccession' => ['table' => 'accession', 'column' => 'identifier'],
        'QubitUser' => ['table' => 'user', 'column' => 'username'],
    ];

    public function handle(): int
    {
        $this->info('Scanning for objects without slugs...');

        // Find all objects that don't have a slug entry
        $objectsWithoutSlugs = DB::table('object')
            ->leftJoin('slug', 'object.id', '=', 'slug.object_id')
            ->whereNull('slug.id')
            ->select('object.id', 'object.class_name')
            ->get();

        if ($objectsWithoutSlugs->isEmpty()) {
            $this->info('All objects have slugs. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$objectsWithoutSlugs->count()} objects without slugs.");

        $generated = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($objectsWithoutSlugs->count());
        $bar->start();

        foreach ($objectsWithoutSlugs as $object) {
            $slug = $this->generateSlugForObject($object->id, $object->class_name);

            if ($slug) {
                DB::table('slug')->insert([
                    'object_id' => $object->id,
                    'slug' => $slug,
                ]);
                $generated++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Generated {$generated} slugs, skipped {$skipped} objects.");

        return self::SUCCESS;
    }

    /**
     * Generate a unique slug for a given object based on its class and title/name.
     */
    private function generateSlugForObject(int $objectId, string $className): ?string
    {
        $name = null;

        if (isset($this->classSlugSources[$className])) {
            $source = $this->classSlugSources[$className];
            $table = $source['table'];
            $column = $source['column'];

            // i18n tables use 'id' + 'culture', non-i18n tables just use 'id'
            if (str_ends_with($table, '_i18n')) {
                $row = DB::table($table)
                    ->where('id', $objectId)
                    ->where('culture', 'en')
                    ->first();
            } else {
                $row = DB::table($table)
                    ->where('id', $objectId)
                    ->first();
            }

            if ($row && ! empty($row->{$column})) {
                $name = $row->{$column};
            }
        }

        // Fallback: use class name + ID
        if (! $name) {
            $shortClass = str_replace('Qubit', '', $className);
            $name = Str::kebab($shortClass) . '-' . $objectId;
        }

        $baseSlug = Str::slug($name);

        if (empty($baseSlug)) {
            $baseSlug = 'item-' . $objectId;
        }

        // Ensure uniqueness
        $slug = $baseSlug;
        $counter = 2;

        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
