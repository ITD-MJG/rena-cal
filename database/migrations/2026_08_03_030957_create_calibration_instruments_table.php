<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')->constrained('calibration_worksheets')->cascadeOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('traceability')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_instruments');
    }
};
