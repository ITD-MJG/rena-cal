<?php

use App\Models\CalibrationWorksheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standard instruments used for the calibration. Also carries the
        // certificate regression data formerly held by
        // standard_instrument_certificates (that table is folded in here).
        Schema::create('calibration_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CalibrationWorksheet::class, 'worksheet_id')
                ->constrained('calibration_worksheets')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('traceability')->nullable();

            // Which uncertainty budget / correction source this instrument
            // feeds: data_logger, stopwatch, thermohygrometer, esa. Null for
            // instruments that are recorded but not consumed by the engine.
            $table->string('role')->nullable();

            // Certificate identity
            $table->string('cert_number')->nullable();
            $table->date('calibration_date')->nullable();
            $table->string('certificate_path')->nullable();

            // Regression output consumed as a unit: {slope, intercept, u, points:[{std,reading}]}
            $table->json('correction_data')->nullable();

            $table->timestamps();

            $table->index(['worksheet_id', 'sort_order']);
            $table->index(['worksheet_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_instruments');
    }
};
