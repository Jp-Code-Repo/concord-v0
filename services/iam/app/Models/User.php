<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Login account. Person identity and personal preferences live on Person.
 */
class User extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DEACTIVATED = 'deactivated';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'person_id',
        'username',
        'password',
        'status',
        'is_it_admin',
        'administrator_key',
        'disabled_at',
        'deactivated_at',
        'settings',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_it_admin' => 'boolean',
            'disabled_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getEmailAttribute(): ?string
    {
        return $this->person?->email;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->person?->display_name ?? '';
    }
}
