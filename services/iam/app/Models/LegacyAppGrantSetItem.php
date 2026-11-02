<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyAppGrantSetItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'grant_set_id',
        'legacy_app_id',
    ];

    /**
     * Grant Set containing this application.
     */
    public function grantSet(): BelongsTo
    {
        return $this->belongsTo(LegacyAppGrantSet::class, 'grant_set_id');
    }
}
