<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyAppGrantSetApplicationEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'grant_set_id',
        'user_id',
        'actor_id',
        'legacy_app_ids',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'legacy_app_ids' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Grant Set used for this application event.
     */
    public function grantSet(): BelongsTo
    {
        return $this->belongsTo(
            LegacyAppGrantSet::class,
            'grant_set_id'
        );
    }

    /**
     * User who received the Grant Set.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Administrator who performed the application.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException(
                'Grant Set application events are immutable and cannot be updated.'
            );
        });

        static::deleting(function (): never {
            throw new \LogicException(
                'Grant Set application events are immutable and cannot be deleted.'
            );
        });
    }
}