<?php

use App\Models\Device;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_worksheets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Device::class)->constrained('devices');
            $table->foreignIdFor(Service::class)->nullable()->constrained('services');

            // Tier 1 resolution: which regulation revision produced this worksheet.
            $table->string('template_key');
            $table->string('template_version');
            $table->string('engine_version');

            // LEMBAR KERJA administration
            $table->string('calibration_room')->nullable();
            $table->date('received_date')->nullable();
            $table->date('calibration_date')->nullable();
            $table->string('technician_name')->nullable();
            $table->foreignIdFor(User::class, 'technician_id')->nullable()->constrained('users');
            $table->string('work_method')->nullable();

            // LEMBAR KERJA instrument specification (LK!F11, F12, K12)
            $table->decimal('device_resolution', 10, 5)->nullable();
            $table->decimal('range_min', 10, 5)->nullable();
            $table->decimal('range_max', 10, 5)->nullable();
            $table->string('range_unit')->nullable();

            // LEMBAR KERJA environment
            $table->decimal('temperature_start', 4, 1)->nullable();
            $table->decimal('temperature_end', 4, 1)->nullable();
            $table->decimal('humidity_start', 5, 1)->nullable();
            $table->decimal('humidity_end', 5, 1)->nullable();
            $table->decimal('main_voltage', 5, 1)->nullable();

            // Tier 3 overrides: {key: {value, note}}
            $table->json('overrides')->nullable();

            // Resolved Tier 1 + Tier 3 values actually used for this revision.
            $table->json('calculation_snapshot')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->decimal('conclusion_threshold', 5, 2)->nullable();
            $table->enum('conclusion', ['Laik Pakai', 'Tidak Laik Pakai'])->nullable();

            // Lifecycle
            $table->enum('status', ['draft', 'exported', 'submitted', 'verified', 'locked'])
                ->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('locked_at')->nullable();
            $table->string('source_workbook_path')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('certificate_pdf_path')->nullable();

            $table->timestamps();

            $table->index(['device_id', 'calibration_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_worksheets');
    }
};
