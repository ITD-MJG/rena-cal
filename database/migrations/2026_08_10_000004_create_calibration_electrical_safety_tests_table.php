<?php

use App\Models\CalibrationWorksheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_electrical_safety_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CalibrationWorksheet::class, 'worksheet_id')
                ->constrained('calibration_worksheets')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('parameter_index');

            $table->string('parameter_name');
            // Langsung | Diferensial | Alternative
            $table->string('measurement_method')->nullable();

            // Tier 2: technician measurement
            $table->decimal('raw_value', 12, 5)->nullable();
            $table->string('unit')->nullable();

            // Derived via standard instrument regression
            $table->decimal('certificate_correction', 12, 5)->nullable();
            $table->decimal('corrected_value', 12, 5)->nullable();

            // Tier 1: seeded from template
            $table->string('threshold_operator')->nullable();
            $table->decimal('threshold_value', 12, 5)->nullable();
            $table->string('threshold_unit')->nullable();

            $table->enum('result', ['Memenuhi', 'Tidak Memenuhi'])->nullable();
            $table->unsignedInteger('weight')->default(0);

            $table->timestamps();

            $table->unique(['worksheet_id', 'parameter_index'], 'cest_worksheet_parameter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_electrical_safety_tests');
    }
};
