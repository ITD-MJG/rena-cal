<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Worksheet extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_GENERATED = 'generated';

    public const CONCLUSION_LAIK = 'Laik Pakai';

    public const CONCLUSION_TIDAK_LAIK = 'Tidak Laik Pakai';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'calibration_date' => 'date',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
