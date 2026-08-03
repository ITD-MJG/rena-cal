<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandardInstrumentCertificate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'calibration_date' => 'date',
        'correction_data' => 'array',
    ];

    public function worksheet()
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }
}
