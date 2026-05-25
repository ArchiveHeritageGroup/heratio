<?php
/**
 * Heratio - generate or rotate the Ed25519 signing key for the inference log.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Console\Commands;

use AhgAiCompliance\Services\KeyResolver;
use AhgInferenceReceipts\KeyPair;
use Illuminate\Console\Command;

final class InstallKeyCommand extends Command
{
    protected $signature = 'ai-compliance:install-key
        {--rotate : Generate a fresh keypair even if one already exists}
        {--force  : Overwrite without confirmation}';

    protected $description = 'Generate the Ed25519 signing keypair for the AI inference log';

    public function handle(KeyResolver $resolver): int
    {
        $secretPath = storage_path('keys/inference-signing.sk');
        $publicPath = storage_path('keys/inference-signing.pk');

        if (is_readable($secretPath) && !$this->option('rotate')) {
            $this->info('Keypair already exists at ' . $secretPath);
            $this->line('Use --rotate to generate a fresh one (old key is preserved in ai_inference_key for verifying old receipts).');
            return self::SUCCESS;
        }

        if (is_readable($secretPath) && !$this->option('force')) {
            if (!$this->confirm('Rotate the inference signing key? Old receipts remain verifiable, new ones use the new key.', false)) {
                $this->warn('Aborted.');
                return self::FAILURE;
            }
        }

        $kp = KeyPair::generate();
        $kp->saveTo($secretPath, $publicPath);
        $resolver->register($kp->kid(), $kp->publicKey(), active: true);

        $this->info('Signing keypair installed.');
        $this->line('  secret: ' . $secretPath . ' (0600)');
        $this->line('  public: ' . $publicPath . ' (0644)');
        $this->line('  kid:    ' . $kp->kid());
        $this->line('  alg:    ed25519');
        $this->line('');
        $this->line('Public key endpoint: /.well-known/ai-inference-pubkey');
        return self::SUCCESS;
    }
}
