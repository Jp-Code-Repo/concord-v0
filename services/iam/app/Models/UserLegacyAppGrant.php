<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserLegacyAppGrant extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'legacy_app_id',
        'granted_at',
        'granted_by',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * User receiving the application access.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User who granted the application access.
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * User who revoked the application access.
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Historical events belonging to this grant period.
     */
    public function events(): HasMany
    {
        return $this->hasMany(LegacyAppGrantEvent::class, 'grant_id');
    }

    /**
     * Determine whether this grant period is currently active.
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
