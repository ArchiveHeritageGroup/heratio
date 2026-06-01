<?php

namespace AtomFramework\Extensions\Spectrum\Database\Migrations;

use AtomFramework\Core\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Psr\Log\LoggerInterface;

/**
 * Create Spectrum tables - Events, Loans, Labels
 * 
 * @package AtomFramework\Extensions\Spectrum
 * @author Johan Pieterse - The Archive and Heritage Group
 */
class CreateSpectrumTables
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

        // Create spectrum_event table
        if (!$schema->hasTable('spectrum_event')) {
            $this->logger->info('Creating spectrum_event table');
            
            $schema->create('spectrum_event', function (Blueprint $table) {
                $table->id();
                $table->integer('object_id')->unsigned();
                $table->string('procedure_id', 50);
                $table->string('event_type', 50);
                $table->string('status_from', 50)->nullable();
                $table->string('status_to', 50)->nullable();
                $table->integer('user_id')->unsigned()->nullable();
                $table->integer('assigned_to_id')->unsigned()->nullable();
                $table->date('due_date')->nullable();
                $table->date('completed_date')->nullable();
                $table->string('location', 255)->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                $table->index(['object_id', 'procedure_id'], 'idx_spectrum_object_procedure');
                $table->index('created_at', 'idx_spectrum_created');
                $table->index('user_id', 'idx_spectrum_user');
                $table->index('status_to', 'idx_spectrum_status');
                $table->index('due_date', 'idx_spectrum_due');
                
                $table->foreign('object_id')
                      ->references('id')
                      ->on('information_object')
                      ->onDelete('cascade');
            });
        }

        // Create spectrum_loan table
        if (!$schema->hasTable('spectrum_loan')) {
            $this->logger->info('Creating spectrum_loan table');
            
            $schema->create('spectrum_loan', function (Blueprint $table) {
                $table->id();
                $table->string('loan_number', 50)->unique();
                $table->char(41)->charset('latin1')->collation('latin1_general_ci');
                $table->integer('borrower_id')->unsigned()->nullable(); // actor_id
                $table->integer('lender_id')->unsigned()->nullable(); // actor_id
                $table->string('contact_name', 255)->nullable();
                $table->string('contact_email', 255)->nullable();
                $table->string('contact_phone', 50)->nullable();
                $table->text('contact_address')->nullable();
                $table->string('purpose', 255)->nullable();
                $table->text('conditions')->nullable();
                $table->date('request_date')->nullable();
                $table->date('approval_date')->nullable();
                $table->date('loan_start_date')->nullable();
                $table->date('loan_end_date')->nullable();
                $table->date('actual_return_date')->nullable();
                $table->char(77)->charset('latin1')->collation('latin1_general_ci')->default('requested');
                $table->decimal('insurance_value', 15, 2)->nullable();
                $table->string('insurance_policy', 100)->nullable();
                $table->integer('approved_by')->unsigned()->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                
                $table->index('loan_number', 'idx_loan_number');
                $table->index('status', 'idx_loan_status');
                $table->index('loan_end_date', 'idx_loan_end');
            });
        }

        // Create spectrum_loan_item table (objects on loan)
        if (!$schema->hasTable('spectrum_loan_item')) {
            $this->logger->info('Creating spectrum_loan_item table');
            
            $schema->create('spectrum_loan_item', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('spectrum_loan')->onDelete('cascade');
                $table->integer('object_id')->unsigned();
                $table->text('condition_on_departure')->nullable();
                $table->text('condition_on_return')->nullable();
                $table->json('departure_photos')->nullable();
                $table->json('return_photos')->nullable();
                $table->decimal('item_value', 15, 2)->nullable();
                $table->text('special_requirements')->nullable();
                $table->timestamps();
                
                $table->foreign('object_id')
                      ->references('id')
                      ->on('information_object')
                      ->onDelete('cascade');
                      
                $table->index(['loan_id', 'object_id'], 'idx_loan_item');
            });
        }

        // Create spectrum_label table
        if (!$schema->hasTable('spectrum_label')) {
            $this->logger->info('Creating spectrum_label table');
            
            $schema->create('spectrum_label', function (Blueprint $table) {
                $table->id();
                $table->integer('object_id')->unsigned();
                $table->char(70)->charset('latin1')->collation('latin1_general_ci');
                $table->string('template', 100)->default('standard');
                $table->json('label_data')->nullable();
                $table->string('file_path', 500)->nullable();
                $table->integer('generated_by')->unsigned()->nullable();
                $table->timestamps();
                
                $table->foreign('object_id')
                      ->references('id')
                      ->on('information_object')
                      ->onDelete('cascade');
                      
                $table->index(['object_id', 'label_type'], 'idx_label_object_type');
            });
        }

        $this->logger->info('Spectrum tables created successfully');
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $schema = $this->db->getSchemaBuilder();

        $this->logger->info('Rolling back Spectrum tables');

        $schema->dropIfExists('spectrum_label');
        $schema->dropIfExists('spectrum_loan_item');
        $schema->dropIfExists('spectrum_loan');
        $schema->dropIfExists('spectrum_event');

        $this->logger->info('Spectrum tables dropped successfully');
    }

    /**
     * Check if migration has been run.
     */
    public function hasRun(): bool
    {
        $schema = $this->db->getSchemaBuilder();
        return $schema->hasTable('spectrum_event');
    }
}
