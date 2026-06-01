<?php

namespace AtomFramework\Extensions\Grap\Database\Migrations;

use AtomFramework\Core\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Psr\Log\LoggerInterface;

/**
 * Create GRAP 103 Heritage Asset tables
 * 
 * @package AtomFramework\Extensions\Grap
 * @author Johan Pieterse - The Archive and Heritage Group
 */
class CreateGrapTables
{
    private DatabaseManager $db;
    private LoggerInterface $logger;

    public function __construct(DatabaseManager $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $schema = $this->db->getSchemaBuilder();

        // Create grap_heritage_asset table
        if (!$schema->hasTable('grap_heritage_asset')) {
            $this->logger->info('Creating grap_heritage_asset table');
            
            $schema->create('grap_heritage_asset', function (Blueprint $table) {
                $table->id();
                $table->integer('object_id')->unsigned()->unique();
                $table->integer('repository_id')->unsigned()->nullable();
                
                // Asset identification
                $table->string('asset_number', 50)->nullable()->index();
                $table->char(81)->charset('latin1')->collation('latin1_general_ci')->default('not_assessed');
                $table->string('asset_class', 100)->nullable()->index();
                $table->string('asset_subclass', 100)->nullable();
                
                // Acquisition details
                $table->date('acquisition_date')->nullable();
                $table->string('acquisition_method', 100)->nullable();
                $table->string('donor_source', 255)->nullable();
                
                // Financial data
                $table->decimal('cost_of_acquisition', 15, 2)->nullable();
                $table->decimal('current_carrying_amount', 15, 2)->nullable();
                $table->decimal('impairment_loss', 15, 2)->nullable();
                $table->decimal('accumulated_depreciation', 15, 2)->default(0);
                $table->decimal('residual_value', 15, 2)->nullable();
                
                // Valuation
                $table->char(69)->charset('latin1')->collation('latin1_general_ci')->nullable();
                $table->date('valuation_date')->nullable();
                $table->string('valuer', 255)->nullable();
                $table->string('valuation_method', 100)->nullable();
                
                // Physical attributes
                $table->string('physical_location', 255)->nullable();
                $table->text('condition_description')->nullable();
                
                // Insurance
                $table->decimal('insurance_value', 15, 2)->nullable();
                $table->string('insurance_policy', 100)->nullable();
                $table->date('insurance_expiry')->nullable();
                
                // Compliance
                $table->integer('compliance_score')->nullable();
                $table->text('compliance_notes')->nullable();
                $table->date('last_compliance_check')->nullable();
                
                // Notes
                $table->text('notes')->nullable();
                
                // Audit
                $table->integer('created_by')->unsigned()->nullable();
                $table->integer('updated_by')->unsigned()->nullable();
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('object_id')
                      ->references('id')
                      ->on('information_object')
                      ->onDelete('cascade');
                
                // Indexes
                $table->index('recognition_status', 'idx_grap_recognition');
                $table->index('asset_class', 'idx_grap_class');
                $table->index('valuation_date', 'idx_grap_valuation');
                $table->index('insurance_expiry', 'idx_grap_insurance');
            });
        }

        // Create grap_transaction_log table
        if (!$schema->hasTable('grap_transaction_log')) {
            $this->logger->info('Creating grap_transaction_log table');
            
            $schema->create('grap_transaction_log', function (Blueprint $table) {
                $table->id();
                $table->integer('asset_id')->unsigned();
                $table->char(112)->charset('latin1')->collation('latin1_general_ci');
                $table->decimal('amount', 15, 2)->nullable();
                $table->decimal('previous_value', 15, 2)->nullable();
                $table->decimal('new_value', 15, 2)->nullable();
                $table->date('transaction_date');
                $table->string('reference', 100)->nullable();
                $table->text('description')->nullable();
                $table->integer('user_id')->unsigned()->nullable();
                $table->timestamps();
                
                $table->foreign('asset_id')
                      ->references('id')
                      ->on('grap_heritage_asset')
                      ->onDelete('cascade');
                      
                $table->index(['asset_id', 'transaction_date'], 'idx_grap_tx_asset_date');
            });
        }

        // Create grap_financial_year_snapshot table
        if (!$schema->hasTable('grap_financial_year_snapshot')) {
            $this->logger->info('Creating grap_financial_year_snapshot table');
            
            $schema->create('grap_financial_year_snapshot', function (Blueprint $table) {
                $table->id();
                $table->integer('repository_id')->unsigned()->nullable();
                $table->string('financial_year', 10); // e.g., "2024/25"
                $table->date('snapshot_date');
                
                // Summary figures
                $table->integer('total_assets')->default(0);
                $table->integer('recognized_assets')->default(0);
                $table->decimal('total_carrying_amount', 18, 2)->default(0);
                $table->decimal('total_impairment', 18, 2)->default(0);
                
                // By class breakdown (JSON)
                $table->json('by_class_breakdown')->nullable();
                
                // Compliance
                $table->decimal('compliance_percentage', 5, 2)->nullable();
                
                $table->integer('created_by')->unsigned()->nullable();
                $table->timestamps();
                
                $table->unique(['repository_id', 'financial_year'], 'idx_grap_fy_repo');
            });
        }

        // Create grap_compliance_check table
        if (!$schema->hasTable('grap_compliance_check')) {
            $this->logger->info('Creating grap_compliance_check table');
            
            $schema->create('grap_compliance_check', function (Blueprint $table) {
                $table->id();
                $table->integer('asset_id')->unsigned();
                $table->date('check_date');
                $table->char(46)->charset('latin1')->collation('latin1_general_ci');
                $table->integer('score')->nullable();
                $table->json('results')->nullable(); // Detailed results
                $table->json('issues')->nullable(); // Issues found
                $table->json('recommendations')->nullable();
                $table->integer('checked_by')->unsigned()->nullable();
                $table->timestamps();
                
                $table->foreign('asset_id')
                      ->references('id')
                      ->on('grap_heritage_asset')
                      ->onDelete('cascade');
                      
                $table->index(['asset_id', 'check_date'], 'idx_grap_check_date');
            });
        }

        $this->logger->info('GRAP tables created successfully');
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $schema = $this->db->getSchemaBuilder();

        $this->logger->info('Rolling back GRAP tables');

        $schema->dropIfExists('grap_compliance_check');
        $schema->dropIfExists('grap_financial_year_snapshot');
        $schema->dropIfExists('grap_transaction_log');
        $schema->dropIfExists('grap_heritage_asset');

        $this->logger->info('GRAP tables dropped successfully');
    }

    /**
     * Check if migration has been run.
     */
    public function hasRun(): bool
    {
        $schema = $this->db->getSchemaBuilder();
        return $schema->hasTable('grap_heritage_asset');
    }
}
