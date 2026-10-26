<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    use HasUlids;

    protected $fillable = [
        'student_id',
        'school_year',
        'grade_level',
        'section',
        'status',
        'guardian_import_data',
    ];

    protected function casts(): array
    {
        return ['guardian_import_data' => 'array'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
