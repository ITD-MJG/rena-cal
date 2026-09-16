<?php

use App\Models\CalibrationPerformanceMeasurement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per measurement point. `point_label` distinguishes S1/S2/S3
        // sensors from plain repeats without a schema change per device type.
        Schema::create('calibration_performance_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CalibrationPerformanceMeasurement::class, 'measurement_id')
                ->constrained('calibration_performance_measurements')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->string('point_label');
            $table->decimal('value', 12, 5);
            $table->string('unit')->nullable();

            $table->timestamps();

            $table->index(['measurement_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_performance_readings');
    }
};
