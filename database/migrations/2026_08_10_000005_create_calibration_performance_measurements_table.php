<?php

use App\Models\CalibrationWorksheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_performance_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CalibrationWorksheet::class, 'worksheet_id')
                ->constrained('calibration_worksheets')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('parameter_index');

            $table->string('parameter_name');
            $table->decimal('setting_value', 12, 5)->nullable();
            $table->string('setting_unit')->nullable();

            // Readings live in calibration_performance_readings.

            $table->decimal('mean', 16, 10)->nullable();
            $table->decimal('std_dev', 16, 10)->nullable();
            $table->decimal('corrected_mean', 16, 10)->nullable();
            $table->decimal('correction', 16, 10)->nullable();
            $table->decimal('uncertainty_u95', 16, 10)->nullable();
            $table->decimal('total_correction_u95', 16, 10)->nullable();

            // Tier 1: seeded from template
            $table->string('allowed_deviation')->nullable();
            $table->decimal('tolerance', 12, 5)->nullable();

            $table->enum('result', ['Lulus', 'Tidak Lulus'])->nullable();
            $table->unsignedInteger('weight')->default(0);

            $table->timestamps();

            $table->unique(['worksheet_id', 'parameter_index'], 'cpm_worksheet_parameter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_performance_measurements');
    }
};
