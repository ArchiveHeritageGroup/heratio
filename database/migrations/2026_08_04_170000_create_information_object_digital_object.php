<?php

/**
 * #1447 - allow more than one digital object attached directly to a single
 * archival description, without spawning a child description per image.
 *
 * Design (Johan): a link table. The existing single primary digital_object
 * (object_id = the IO) is left UNTOUCHED, so every current read that assumes
 * one master keeps working. Additional objects are ordinary digital_object rows
 * (each with its own thumbnail/reference derivatives via parent_id) whose
 * object_id is NULL - invisible to legacy object_id reads - and are attached to
 * the description ONLY through this table, with order, an optional primary flag,
 * a caption and a role (recto/verso/page/view...).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('information_object_digital_object')) {
            return;
        }

        DB::statement(
            'CREATE TABLE information_object_digital_object ('
            .' id INT AUTO_INCREMENT PRIMARY KEY,'
            .' information_object_id INT NOT NULL,'
            .' digital_object_id INT NOT NULL,'
            .' sort_order INT NOT NULL DEFAULT 0,'
            .' is_primary TINYINT(1) NOT NULL DEFAULT 0,'
            .' caption VARCHAR(255) NULL,'
            .' role VARCHAR(64) NULL,'
            .' created_by INT NULL,'
            .' created_at TIMESTAMP NULL DEFAULT NULL,'
            .' updated_at TIMESTAMP NULL DEFAULT NULL,'
            .' UNIQUE KEY uq_iodo (information_object_id, digital_object_id),'
            .' KEY idx_iodo_sort (information_object_id, sort_order)'
            .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('information_object_digital_object');
    }
};
