<?php

namespace App\Models;

use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool 
    { 
        return in_array($this->role, ['admin', 'staff'], true);
    }
    
    // Orders (registered users only)
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Addresses (saved addresses)
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function savedAddresses()
    {
        return $this->hasMany(SavedAddress::class);
    }

    // Reviews
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Cart
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }
}