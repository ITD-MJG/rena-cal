<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_worksheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');
            $table->foreignId('service_id')->nullable()->constrained('services');
            $table->string('calibration_room')->nullable();
            $table->date('received_date')->nullable();
            $table->date('calibration_date')->nullable();
            $table->string('technician_name')->nullable();
            $table->string('work_method')->nullable();
            $table->decimal('temperature_start', 4, 1)->nullable();
            $table->decimal('temperature_end', 4, 1)->nullable();
            $table->decimal('humidity_start', 5, 1)->nullable();
            $table->decimal('humidity_end', 5, 1)->nullable();
            $table->decimal('main_voltage', 5, 1)->nullable();
            $table->enum('conclusion', ['Laik Pakai', 'Tidak Laik Pakai'])->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->string('certificate_pdf_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_worksheets');
    }
};
