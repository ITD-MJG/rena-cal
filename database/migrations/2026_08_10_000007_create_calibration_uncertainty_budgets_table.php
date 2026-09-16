<?php

use App\Models\CalibrationWorksheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per KETIDAKPASTIAN group (e.g. temperature_121, temperature_134, time).
        Schema::create('calibration_uncertainty_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CalibrationWorksheet::class, 'worksheet_id')
                ->constrained('calibration_worksheets')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->string('key');
            $table->string('label');
            $table->string('unit')->nullable();

            $table->decimal('uc', 16, 10)->nullable();
            $table->decimal('veff', 16, 10)->nullable();
            $table->decimal('coverage_factor', 16, 10)->nullable();
            $table->decimal('expanded_uncertainty', 16, 10)->nullable();

            $table->timestamps();

            $table->unique(['worksheet_id', 'key'], 'cub_worksheet_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_uncertainty_budgets');
    }
};
