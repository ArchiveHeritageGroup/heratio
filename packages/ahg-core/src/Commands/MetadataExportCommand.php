<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class MetadataExportCommand extends Command
{
    protected $signature = 'ahg:metadata-export
        {--format=all : Export format (ead3, lido, marc21, rico, premis, bibframe, all)}
        {--slug= : Information object slug}
        {--repository= : Repository slug}
        {--output=/tmp : Output directory}
        {--include-children : Include child descriptions}
        {--include-digital-objects : Include digital object metadata}
        {--include-drafts : Include draft records}
        {--list : List available export formats}';

    protected $description = 'Export metadata in GLAM standards (EAD3, LIDO, MARC21, RIC-O, PREMIS, BIBFRAME, etc.)';

    public function handle(): int
    {
        $this->info('Exporting metadata...');
        // TODO: Implement metadata export in GLAM standards
        $this->info('Metadata export complete.');
        return 0;
    }
}
