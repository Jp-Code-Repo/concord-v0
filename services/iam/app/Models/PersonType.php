<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonType extends Model
{
    use HasUlids;

    public const EMPLOYEE = 'employee';
    public const STUDENT = 'student';
    public const PARENT_GUARDIAN = 'parent_guardian';
    public const VISITOR = 'visitor';

    public const TYPES = [
        self::EMPLOYEE,
        self::STUDENT,
        self::PARENT_GUARDIAN,
        self::VISITOR,
    ];

    protected $fillable = ['person_id', 'type'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
