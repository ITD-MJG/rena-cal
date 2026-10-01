<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function getRouteKeyName()
    {
        return 'deviceId';
    }

    protected $casts = [
        'deviceId' => 'string',
    ];

    public function setCertPasswordAttribute($value)
    {
        if (! $value) {
            $this->attributes['cert_password'] = null;

            return;
        }

        if (! preg_match('/^\$2y\$/', $value)) {
            $this->attributes['cert_password'] = bcrypt($value);
        } else {
            $this->attributes['cert_password'] = $value;
        }
    }

    /**
     * Generate a new UUID for the device ID when creating a new record
     */
    protected static function booted(): void
    {
        static::creating(function ($device) {
            if (! $device->deviceId) {
                $device->deviceId = (string) Str::orderedUuid();
            }
            // Make next calibrated date a year from calibration_date
            if ($device->calibration_date) {
                $device->next_calibration_date = Carbon::parse($device->calibration_date)->addYear();
            }
        });

        static::updating(function ($device) {
            // Update next calibrated date to be a year from calibration_date if calibration_date is changed
            if ($device->isDirty('calibration_date')) {
                $device->next_calibration_date = Carbon::parse($device->calibration_date)->addYear();
            }

            // Automatically attribute PIC if updated by a Technician
            $user = auth()->user();
            if ($user && $user->hasRole('Technician')) {
                $device->pic_id = $user->id;
            }
        });

        static::deleted(function ($device) {
            if ($device->barcode) {
                Storage::disk('public')->delete($device->barcode);
            }
        });
    }

    /**
     * A device belongs to a device name
     */
    public function deviceName()
    {
        return $this->belongsTo(DeviceName::class, 'device_name_id');
    }

    /**
     * A device belongs to a brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * A device belongs to a type (model)
     */
    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    /**
     * A device belongs to a user (PIC - Person In Charge)
     */
    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    /**
     * A device belongs to a customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * A device has many inventories
     */
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }
}
