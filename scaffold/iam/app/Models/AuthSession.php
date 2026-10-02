<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuthSession extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['user_id', 'token_hash', 'ip_address', 'user_agent', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
