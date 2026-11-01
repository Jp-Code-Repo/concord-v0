<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyAppGrantEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    public const EVENT_GRANTED = 'granted';
    public const EVENT_REVOKED = 'revoked';

    public const SOURCE_INDIVIDUAL = 'individual';
    public const SOURCE_GRANT_SET = 'grant_set';
    public const SOURCE_BATCH = 'batch';

    protected $fillable = [
        'grant_id',
        'event_type',
        'actor_id',
        'source',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Grant period associated with this event.
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(UserLegacyAppGrant::class, 'grant_id');
    }

    /**
     * User who performed the event.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException(
                'Legacy app grant events are immutable and cannot be updated.'
            );
        });

        static::deleting(function (): never {
            throw new \LogicException(
                'Legacy app grant events are immutable and cannot be deleted.'
            );
        });
    }

}
