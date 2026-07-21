<?php

/**
 * Archaeology collections management tables (#1422).
 *
 * Generalised from the NMMZ Zimbabwe module (nmmz_archaeological_site /
 * nmmz_antiquity), which modelled the same domain but hard-coded Zimbabwean
 * administrative geography and stored period, material and method as free text.
 *
 * Both tables extend `information_object` on the same pattern as `library_item`:
 * the descriptive record, hierarchy, titles, ACL and ICIP protocols live on the
 * core object, and only domain-specific fields live here. Titles are NOT
 * duplicated - they come from information_object_i18n.
 *
 * The instance this serves (Wits) catalogues to ISAD(G), so the archaeological
 * hierarchy rides the existing archival hierarchy:
 *   Fonds/Collection = project or accession
 *   Series           = site
 *   Item             = individual find
 * with Sub-series/File left free for area/trench/context when excavation
 * recording is added later.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archaeology_site')) {
            Schema::create('archaeology_site', function (Blueprint $table) {
                $table->id();

                // Descriptive record. Title, dates, scope and ACL live there.
                $table->unsignedInteger('information_object_id')->nullable()->index();

                // Institutional number, plus the national register number. In
                // South Africa sites carry a SAHRA/ASAPA identifier distinct
                // from the holding institution's own numbering.
                $table->string('site_number', 100)->unique();
                $table->string('national_site_number', 100)->nullable()->index();

                // Taxonomy term references, not free text. This is the main
                // correction to the NMMZ model: 'period' as an uncontrolled
                // varchar cannot be browsed, faceted or reconciled. Terms also
                // carry ICIP protocols (term_protocol) and Getty/AAT links,
                // which matters for sensitive categories such as burial sites.
                $table->unsignedInteger('site_type_id')->nullable()->index();
                $table->unsignedInteger('period_id')->nullable()->index();

                // Location. Region/locality replace NMMZ's province/district so
                // the module is not tied to one country's administrative tiers.
                $table->string('region', 150)->nullable()->index();
                $table->string('locality', 255)->nullable();
                $table->text('location_description')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->unsignedSmallInteger('elevation_m')->nullable();
                $table->unsignedInteger('spatial_accuracy_m')->nullable()
                    ->comment('Radius of positional uncertainty; blank means unrecorded, not exact');
                $table->decimal('area_sqm', 12, 2)->nullable();

                // Dating. Kept as strings because archaeological dates are
                // rarely calendar dates ("c. 1200 AD", "2500 BP", "MIS 5").
                $table->string('date_earliest', 50)->nullable();
                $table->string('date_latest', 50)->nullable();
                $table->text('dating_note')->nullable();

                // Investigation history.
                $table->date('discovery_date')->nullable();
                $table->string('discovered_by', 255)->nullable();
                $table->boolean('excavated')->default(false)->index();
                $table->string('excavation_years', 100)->nullable();
                $table->string('excavator', 255)->nullable();
                $table->string('excavation_institution', 255)->nullable();
                $table->string('permit_number', 100)->nullable()
                    ->comment('Excavation/collection permit, e.g. SAHRA under the NHRA');

                // Management.
                $table->unsignedInteger('protection_status_id')->nullable()->index();
                $table->text('threats')->nullable();
                $table->string('research_potential', 30)->nullable()->default('medium');
                $table->text('publications')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('active')->index();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('archaeology_object')) {
            Schema::create('archaeology_object', function (Blueprint $table) {
                $table->id();

                $table->unsignedInteger('information_object_id')->nullable()->index();
                $table->string('accession_number', 100)->unique();

                // The link NMMZ lacked: finds recorded a find_location string
                // with no relationship to the site record, so a site's
                // assemblage could not be listed.
                $table->unsignedBigInteger('site_id')->nullable()->index();

                // Typology, all term-backed. Per-material attributes
                // (bead diameter and perforation, glass series, ceramic fabric)
                // belong in custom_field_definition rather than columns here.
                $table->unsignedInteger('object_type_id')->nullable()->index();
                $table->unsignedInteger('material_id')->nullable()->index();
                $table->unsignedInteger('technique_id')->nullable()->index();
                $table->unsignedInteger('period_id')->nullable()->index();

                // Recovery.
                $table->unsignedInteger('recovery_method_id')->nullable()->index();
                $table->string('context_reference', 100)->nullable()
                    ->comment('Stratigraphic unit/context; free text until excavation recording lands');
                $table->string('excavation_reference', 100)->nullable();
                $table->date('find_date')->nullable();
                $table->string('find_location', 255)->nullable();
                $table->string('finder', 255)->nullable();

                // Dating.
                $table->string('date_earliest', 50)->nullable();
                $table->string('date_latest', 50)->nullable();
                $table->unsignedInteger('dating_method_id')->nullable()->index();
                $table->text('dating_note')->nullable();

                // Quantification. A count lets a bulk assemblage ("312 potsherds
                // from context 4") be one record rather than 312, which is how
                // excavated material is actually catalogued.
                $table->unsignedInteger('item_count')->default(1);
                $table->decimal('weight_g', 12, 3)->nullable();
                $table->decimal('length_mm', 10, 2)->nullable();
                $table->decimal('width_mm', 10, 2)->nullable();
                $table->decimal('thickness_mm', 10, 2)->nullable();
                $table->decimal('diameter_mm', 10, 2)->nullable();
                $table->string('dimensions_note', 255)->nullable();

                // Custody.
                $table->unsignedInteger('condition_id')->nullable()->index();
                $table->unsignedInteger('repository_id')->nullable()->index();
                $table->string('storage_location', 255)->nullable();
                $table->text('provenance')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('active')->index();

                $table->timestamps();

                $table->foreign('site_id')
                    ->references('id')->on('archaeology_site')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archaeology_object');
        Schema::dropIfExists('archaeology_site');
    }
};
