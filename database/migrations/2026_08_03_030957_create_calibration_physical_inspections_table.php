<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_physical_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')->constrained('calibration_worksheets')->cascadeOnDelete();
            $table->unsignedTinyInteger('parameter_index');
            $table->string('parameter_name');
            $table->text('description')->nullable();
            $table->boolean('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_physical_inspections');
    }
};
