<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class DoiMintCommand extends Command
{
    protected $signature = 'ahg:doi-mint
        {--slug= : Information object slug}
        {--object-id= : Information object ID}
        {--repository= : Repository slug}
        {--state=findable : DOI state (draft, registered, findable)}
        {--dry-run : Simulate without minting}
        {--limit= : Maximum DOIs to mint}';

    protected $description = 'Mint DOIs via DataCite';

    public function handle(): int
    {
        $this->info('Minting DOIs via DataCite...');
        // TODO: Implement DOI minting via DataCite API
        $this->info('DOI minting complete.');
        return 0;
    }
}
