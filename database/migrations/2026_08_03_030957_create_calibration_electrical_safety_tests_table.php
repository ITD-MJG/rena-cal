<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_electrical_safety_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')->constrained('calibration_worksheets')->cascadeOnDelete();
            $table->string('parameter_name');
            $table->string('measurement_method')->nullable(); // Langsung, Diferensial, Alternative
            $table->decimal('raw_value', 10, 5)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('certificate_correction', 10, 5)->nullable();
            $table->decimal('corrected_value', 10, 5)->nullable();
            $table->string('threshold_operator')->nullable(); // ≤, >
            $table->decimal('threshold_value', 10, 5)->nullable();
            $table->string('threshold_unit')->nullable();
            $table->enum('result', ['Memenuhi', 'Tidak Memenuhi'])->nullable();
            $table->integer('weight')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_electrical_safety_tests');
    }
};
