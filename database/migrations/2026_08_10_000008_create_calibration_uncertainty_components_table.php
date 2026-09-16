<?php

use App\Models\CalibrationUncertaintyBudget;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ported from the KETIDAKPASTIAN sheet: each row is one uncertainty
        // component {name, unit, distribution, U, divisor, v, c}.
        Schema::create('calibration_uncertainty_components', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CalibrationUncertaintyBudget::class, 'budget_id')
                ->constrained('calibration_uncertainty_budgets')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->string('component_name');
            $table->string('symbol')->nullable();
            $table->string('unit')->nullable();

            // normal | segi4
            $table->string('distribution');
            $table->decimal('u_value', 16, 10)->nullable();
            $table->decimal('divisor', 16, 10)->nullable();
            $table->decimal('degrees_of_freedom', 16, 5)->nullable();
            $table->decimal('sensitivity_coefficient', 16, 10)->nullable();

            $table->decimal('u_contribution', 16, 10)->nullable();
            $table->decimal('u_contribution_sq', 16, 10)->nullable();
            $table->decimal('u_contribution_4th_over_v', 16, 10)->nullable();

            // derived | certificate | constant | device_spec
            $table->string('source_type');
            $table->string('source_ref')->nullable();

            $table->timestamps();

            $table->index(['budget_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_uncertainty_components');
    }
};
