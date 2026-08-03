<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_performance_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')->constrained('calibration_worksheets')->cascadeOnDelete();
            $table->string('parameter_name');
            $table->decimal('setting_value', 10, 2)->nullable();
            $table->string('setting_unit')->nullable();
            $table->decimal('measurement_1', 10, 5)->nullable();
            $table->decimal('measurement_2', 10, 5)->nullable();
            $table->decimal('measurement_3', 10, 5)->nullable();
            $table->decimal('mean', 10, 5)->nullable();
            $table->decimal('std_dev', 10, 8)->nullable();
            $table->decimal('corrected_mean', 10, 5)->nullable();
            $table->decimal('correction', 10, 5)->nullable();
            $table->decimal('uncertainty_u95', 10, 10)->nullable();
            $table->decimal('total_correction_u95', 10, 10)->nullable();
            $table->string('allowed_deviation')->nullable();
            $table->decimal('tolerance', 10, 2)->nullable();
            $table->enum('result', ['Lulus', 'Tidak Lulus'])->nullable();
            $table->integer('weight')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_performance_measurements');
    }
};
