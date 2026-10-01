<?php

namespace Database\Factories;

use App\Models\Worksheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worksheet>
 */
class WorksheetFactory extends Factory
{
    protected $model = Worksheet::class;

    public function definition(): array
    {
        return [
            'source_workbook_path' => 'worksheets/'.$this->faker->uuid().'.xlsx',
            'original_filename' => 'Autoclave.xlsx',
            'status' => Worksheet::STATUS_DRAFT,
        ];
    }

    public function generated(): static
    {
        return $this->state(fn () => [
            'status' => Worksheet::STATUS_GENERATED,
            'certificate_pdf_path' => 'certificates/'.$this->faker->uuid().'.pdf',
        ]);
    }
}
