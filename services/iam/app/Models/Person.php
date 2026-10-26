<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasUlids;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'name_suffix',
        'gender',
        'email',
        'preferences',
    ];

    protected function casts(): array
    {
        return ['preferences' => 'array'];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function types(): HasMany
    {
        return $this->hasMany(PersonType::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->name_suffix,
        ])));
    }
}
