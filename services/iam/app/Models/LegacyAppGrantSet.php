<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegacyAppGrantSet extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Applications included in this Grant Set.
     */
    public function items(): HasMany
    {
        return $this->hasMany(LegacyAppGrantSetItem::class, 'grant_set_id');
    }
}