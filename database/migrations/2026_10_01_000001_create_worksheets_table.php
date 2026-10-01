<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worksheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('source_workbook_path');
            $table->string('original_filename');
            $table->json('payload')->nullable();
            $table->string('cert_number')->nullable();
            $table->string('order_number')->nullable();
            $table->enum('status', ['draft', 'generated'])->default('draft');
            $table->string('certificate_pdf_path')->nullable();

            // Denormalised from payload so the resource table can sort and
            // search without unpacking the JSON.
            $table->string('device_serial')->nullable();
            $table->string('customer_name')->nullable();
            $table->date('calibration_date')->nullable();
            $table->enum('conclusion', ['Laik Pakai', 'Tidak Laik Pakai'])->nullable();

            $table->timestamps();

            $table->index(['device_id', 'calibration_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worksheets');
    }
};
