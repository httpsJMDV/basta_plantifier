<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'username', 'bio', 'municipality', 'interests', 'purpose', 'experience', 'discovery_enabled', 'friend_request_privacy', 'profile_visibility', 'connections_visibility', 'activity_visibility'];

    protected $hidden = ['password', 'remember_token', 'google_id', 'totp_secret', 'recovery_codes', 'last_totp_counter'];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_media_id ? '/media/'.$this->avatar_media_id : null;
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'local_password_set_at' => 'datetime', 'email_verified_at' => 'datetime', 'onboarded_at' => 'datetime', 'suspended_at' => 'datetime', 'interests' => 'array', 'discovery_enabled' => 'boolean', 'totp_secret' => 'encrypted', 'recovery_codes' => 'encrypted:array'];
    }

    public function isAdmin(): bool
    {
        return DB::table('super_admin')->where('id', 1)->where('user_id', $this->id)->exists();
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function plants()
    {
        return $this->hasMany(PlantRecord::class);
    }
}
