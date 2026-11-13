<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\PasswordResetLinkNotification;

#[Fillable(['name', 'email', 'password', 'phone', 'address'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, \Laravel\Sanctum\HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function socialAccounts() { return $this->hasMany(SocialAccount::class); }
    public function pets() { return $this->hasMany(Pet::class); }
    public function shopOrders() { return $this->hasMany(ShopOrder::class); }
    public function pointTransactions() { return $this->hasMany(PointTransaction::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function hotels() { return $this->hasMany(Hotel::class, 'owner_id'); }
    public function favoriteHotels() { return $this->belongsToMany(Hotel::class, 'hotel_favorites')->withTimestamps(); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isOwner(): bool { return in_array($this->role, ['hotel_owner', 'admin'], true); }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PasswordResetLinkNotification($token));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'owner_quick_links' => 'array',
            'reward_points' => 'integer',
        ];
    }
}
