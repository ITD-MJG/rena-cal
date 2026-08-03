<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationPhysicalInspection extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'result' => 'boolean',
    ];

    public function worksheet()
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }
}
