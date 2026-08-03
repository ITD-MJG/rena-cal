<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationInstrument extends Model
{
    protected $guarded = ['id'];

    public function worksheet()
    {
        return $this->belongsTo(CalibrationWorksheet::class, 'worksheet_id');
    }
}
