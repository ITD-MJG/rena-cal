<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_instrument_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')->constrained('calibration_worksheets')->cascadeOnDelete();
            $table->string('instrument_name');
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('calibration_date')->nullable();
            $table->string('traceability')->nullable();
            $table->string('cert_number')->nullable();
            $table->json('correction_data')->nullable(); // per-point corrections
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_instrument_certificates');
    }
};
