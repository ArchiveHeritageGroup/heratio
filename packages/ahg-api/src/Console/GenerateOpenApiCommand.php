<?php

/**
 * GenerateOpenApiCommand
 *
 * Artisan: `php artisan api:generate-openapi`
 *
 * Writes the OpenAPI 3.1 spec to packages/ahg-api/resources/openapi/heratio.json.
 *
 * Issue #652 Phase 1.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgApi\Console;

use AhgApi\Services\OpenApiGenerator;
use Illuminate\Console\Command;

class GenerateOpenApiCommand extends Command
{
    protected $signature = 'api:generate-openapi
        {--output= : Path to write the spec to (defaults to packages/ahg-api/resources/openapi/heratio.json)}
        {--print : Echo the spec to stdout instead of writing}';

    protected $description = 'Generate the OpenAPI 3.1 spec for the Heratio REST API by reflecting Laravel routes.';

    public function handle(OpenApiGenerator $generator): int
    {
        $spec = $generator->generate();
        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('Failed to encode OpenAPI spec as JSON.');

            return self::FAILURE;
        }

        if ($this->option('print')) {
            $this->line($json);

            return self::SUCCESS;
        }

        $output = $this->option('output') ?: base_path('packages/ahg-api/resources/openapi/heratio.json');
        $dir = dirname($output);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($output, $json);

        $pathCount = is_array($spec['paths'] ?? null) ? count($spec['paths']) : 0;
        $this->info(sprintf('Wrote OpenAPI 3.1 spec to %s (%d paths).', $output, $pathCount));

        return self::SUCCESS;
    }
}
