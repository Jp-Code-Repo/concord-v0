<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentController extends Controller
{
    public function index(string $studentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);

        return response()->json([
            'student_id' => $student->id,
            'student_number' => $student->student_number,
            'enrollments' => $student->enrollments()
                ->orderByDesc('school_year')
                ->get(),
        ]);
    }

    public function store(Request $request, string $studentId): JsonResponse
    {
        $data = $request->validate([
            'school_year' => 'required|string|max:20',
            'grade_level' => 'required|string|max:40',
            'section' => 'required|string|max:100',
            'status' => 'required|string|max:32',
            'guardian_import_data' => 'sometimes|array',
        ]);

        $student = Student::query()->findOrFail($studentId);

        if ($student->enrollments()->where('school_year', $data['school_year'])->exists()) {
            throw ValidationException::withMessages([
                'school_year' => ['An enrollment record already exists for this student and school year.'],
            ]);
        }

        $enrollment = $student->enrollments()->create($data);

        return response()->json([
            'message' => 'Student enrollment recorded.',
            'enrollment' => $enrollment,
        ], 201);
    }
}
