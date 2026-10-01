<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasFactory, HasRoles, Notifiable;

    public const DEFAULT_PASSWORD = 'Calibration2025!';

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Set password automatically upon User creation
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->userId)) {
                $user->userId = (string) Str::orderedUuid();
            }

            if (empty($user->password)) {
                $user->password = self::DEFAULT_PASSWORD;
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * A user belongs to a customer (for Hospital User role)
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * A user has many devices as PIC
     */
    public function devicePic()
    {
        return $this->hasMany(Device::class, 'pic_id');
    }

    /**
     * Create a temporary signed URL that logs the user in on visit.
     */
    public function createLoginUrl(): string
    {
        return URL::temporarySignedRoute(
            'customers.admin.login',
            now()->addMinutes(config('auth.passwords.users.expire')),
            ['user' => $this->getKey()],
        );
    }
}
