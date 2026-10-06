<?php

use App\Support\Import\NameKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keys and bookkeeping for importing the directory from public registers
 * (ФЗОМ „Шифрарник на лекари“, Лекарска комора licence list).
 *
 * Everything added here is internal: the ФЗО facsimile number, the licence
 * number and the facility's tax number are matching keys, never part of an
 * API resource or an export.
 *
 * - doctors: licence (number, expiry, specialty as published, source, last
 *   checked), ФЗО facsimile, normalised name keys for matching, and the
 *   "seen / missing" counters of the last imports.
 * - facilities: ФЗО institution code, tax number (ЕДБ), ownership, and the
 *   same counters.
 * - doctor_facility / doctor_specialty: which source made the link, so an
 *   import only ever removes links it created itself, never a staff link.
 *   The contract's work unit and type are kept on the facility link.
 * - specialties: created_by_import marks catalogue rows the importer added,
 *   which bulk publishing may publish together with their first doctors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('licence_number', 16)->nullable()->unique();
            $table->date('licence_valid_until')->nullable();
            $table->string('licence_specialty_raw')->nullable();
            $table->string('licence_source', 32)->nullable();
            $table->timestamp('licence_checked_at')->nullable();
            $table->string('fzo_facsimile', 16)->nullable()->unique();
            $table->string('name_key')->nullable()->index();
            $table->string('name_key_sorted')->nullable()->index();
            $table->string('import_source', 32)->nullable();
            $table->timestamp('import_last_seen_at')->nullable();
            $table->unsignedSmallInteger('import_missing_runs')->default(0);
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->string('fzo_code', 16)->nullable()->unique();
            $table->string('tax_number', 16)->nullable()->index();
            $table->string('ownership', 16)->nullable();
            $table->string('import_source', 32)->nullable();
            $table->timestamp('import_last_seen_at')->nullable();
            $table->unsignedSmallInteger('import_missing_runs')->default(0);
        });

        Schema::table('doctor_facility', function (Blueprint $table) {
            $table->string('source', 32)->nullable();
            $table->string('work_unit')->nullable();
            $table->string('contract_type')->nullable();
        });

        Schema::table('doctor_specialty', function (Blueprint $table) {
            $table->string('source', 32)->nullable();
        });

        Schema::table('specialties', function (Blueprint $table) {
            $table->boolean('created_by_import')->default(false);
        });

        // Existing profiles get their name keys now, so the first import can
        // match staff-entered doctors by name instead of duplicating them.
        DB::table('doctors')->select(['id', 'full_name'])->orderBy('id')->chunk(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('doctors')->where('id', $row->id)->update([
                    'name_key' => NameKey::for((string) $row->full_name),
                    'name_key_sorted' => NameKey::sorted((string) $row->full_name),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('specialties', function (Blueprint $table) {
            $table->dropColumn('created_by_import');
        });

        Schema::table('doctor_specialty', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('doctor_facility', function (Blueprint $table) {
            $table->dropColumn(['source', 'work_unit', 'contract_type']);
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropUnique(['fzo_code']);
            $table->dropIndex(['tax_number']);
            $table->dropColumn(['fzo_code', 'tax_number', 'ownership', 'import_source', 'import_last_seen_at', 'import_missing_runs']);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropUnique(['licence_number']);
            $table->dropUnique(['fzo_facsimile']);
            $table->dropIndex(['name_key']);
            $table->dropIndex(['name_key_sorted']);
            $table->dropColumn([
                'licence_number', 'licence_valid_until', 'licence_specialty_raw', 'licence_source', 'licence_checked_at',
                'fzo_facsimile', 'name_key', 'name_key_sorted', 'import_source', 'import_last_seen_at', 'import_missing_runs',
            ]);
        });
    }
};
