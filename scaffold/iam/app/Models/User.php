<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Identity only. Accounts are never deleted: status goes 'active' -> 'disabled'.
 */
class User extends Model
{
    use HasUlids;

    protected $fillable = [
        'username', 'email', 'display_name', 'password',
        'status', 'is_it_admin', 'disabled_at',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_it_admin' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }
}
