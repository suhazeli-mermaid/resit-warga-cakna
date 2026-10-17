<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // penderma, ajk, admin
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Semakan peranan automatik
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->email === 'wargacaknatrg@gmail.com';
    }

    public function isAjk(): bool
    {
        return $this->isAdmin() || $this->role === 'ajk';
    }
}