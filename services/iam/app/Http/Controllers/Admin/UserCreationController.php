<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersonType;
use App\Models\User;
use App\Services\UserCreationService;
use App\Services\PlatformAdministratorService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Services\SessionService;

class UserCreationController extends Controller
{

    public function show(string $user): JsonResponse
    {
        $user = User::query()
            ->with(['person.types', 'person.student'])
            ->findOrFail($user);

        $person = $user->person;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'person_id' => $user->person_id,
                'username' => $user->username,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'disabled_at' => $user->disabled_at,
                'deactivated_at' => $user->deactivated_at,
            ],
            'person' => $person ? [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'middle_name' => $person->middle_name,
                'last_name' => $person->last_name,
                'name_suffix' => $person->name_suffix,
                'gender' => $person->gender,
                'email' => $person->email,
                'display_name' => $person->display_name,
                'preferences' => $person->preferences ?? [],
                'types' => $person->types->pluck('type')->all(),
                'student_number' => $person->student?->student_number,
            ] : null,
        ]);
    }

        public function update(
        Request $request,
        string $user,
        PlatformAdministratorService $platformAdministrators
    ): JsonResponse {
        $data = $request->validate([
            'username' => 'sometimes|required|string|max:100',
            'first_name' => 'sometimes|required|string|max:100',
            'middle_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'name_suffix' => 'sometimes|nullable|string|max:30',
            'gender' => 'sometimes|nullable|string|max:32',
            'email' => 'sometimes|nullable|string|email|max:255',
            'types' => 'sometimes|required|array|min:1',
            'types.*' => 'required|string|in:'.implode(',', PersonType::TYPES),
            'student_number' => 'sometimes|nullable|string|max:64',
        ]);

        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $platformAdministrators->isDesignated($actor->id),
            403,
            'Only the Concord Administrator can update accounts.'
        );

        $target = User::query()
            ->with('person.types', 'person.student')
            ->findOrFail($user);

        $person = $target->person;

        if (array_key_exists('username', $data)) {
            $username = trim($data['username']);

            if (User::query()
                ->where('username', $username)
                ->where('id', '!=', $target->id)
                ->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'username' => ['This username is already taken.'],
                ]);
            }

            $data['username'] = $username;
        }

        $currentTypes = $person->types->pluck('type')->all();
        $newTypes = array_values(array_unique($data['types'] ?? $currentTypes));

        $student = $person->student;

        if (in_array(PersonType::STUDENT, $newTypes, true)) {
            $studentNumber = trim((string) (
                $data['student_number'] ?? $student?->student_number ?? ''
            ));

            if ($studentNumber === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'student_number' => ['A student number is required for a student.'],
                ]);
            }

            if (Student::query()
                ->where('student_number', $studentNumber)
                ->where('person_id', '!=', $person->id)
                ->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'student_number' => ['This student number is already registered.'],
                ]);
            }

            $data['student_number'] = $studentNumber;
        } elseif (array_key_exists('student_number', $data) && $student) {
            $studentNumber = trim((string) $data['student_number']);

            if ($studentNumber !== '' && Student::query()
                ->where('student_number', $studentNumber)
                ->where('person_id', '!=', $person->id)
                ->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'student_number' => ['This student number is already registered.'],
                ]);
            }

            $data['student_number'] = $studentNumber;
        }

        DB::transaction(function () use (
            $data,
            $target,
            $person,
            $newTypes,
            $actor,
            $request
        ): void {
            if (array_key_exists('username', $data)) {
                $target->username = $data['username'];
                $target->save();
            }

            $personFields = [
                'first_name',
                'middle_name',
                'last_name',
                'name_suffix',
                'gender',
                'email',
            ];

            $personUpdates = [];

            foreach ($personFields as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field];
                    $value = is_string($value) ? trim($value) : $value;

                    if (in_array($field, [
                        'middle_name',
                        'name_suffix',
                        'gender',
                        'email',
                    ], true) && $value === '') {
                        $value = null;
                    }

                    if ($field === 'email' && $value !== null) {
                        $value = strtolower($value);
                    }

                    $personUpdates[$field] = $value;
                }
            }

            if ($personUpdates !== []) {
                $person->update($personUpdates);
            }

            if (array_key_exists('types', $data)) {
                $person->types()
                    ->whereNotIn('type', $newTypes)
                    ->delete();

                foreach ($newTypes as $type) {
                    $person->types()->firstOrCreate(['type' => $type]);
                }
            }

            if (array_key_exists('student_number', $data)) {
                $studentNumber = trim((string) $data['student_number']);

                if ($studentNumber !== '') {
                    $person->student()->updateOrCreate(
                        ['person_id' => $person->id],
                        ['student_number' => $studentNumber]
                    );
                }
            }

            Audit::log(
                'identity.user_updated',
                $actor->id,
                $request,
                entityType: 'user',
                entityId: $target->id,
                meta: [
                    'target_user_id' => $target->id,
                    'changed_fields' => array_keys($data),
                ]
            );
        });

        $target->refresh();
        $person->refresh()->load(['types', 'student']);

        return response()->json([
            'message' => 'User updated.',
            'user' => [
                'id' => $target->id,
                'person_id' => $target->person_id,
                'username' => $target->username,
                'status' => $target->status,
            ],
            'person' => [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'middle_name' => $person->middle_name,
                'last_name' => $person->last_name,
                'name_suffix' => $person->name_suffix,
                'gender' => $person->gender,
                'email' => $person->email,
                'display_name' => $person->display_name,
                'types' => $person->types->pluck('type')->all(),
                'student_number' => $person->student?->student_number,
            ],
        ]);
    }

    public function resetPassword(
        Request $request,
        string $user,
        SessionService $sessions,
        PlatformAdministratorService $platformAdministrators
    ): JsonResponse {
        $data = $request->validate([
            'mode' => 'required|in:generate,custom',
            'password' => 'required_if:mode,custom|nullable|string|min:12',
        ]);

        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $platformAdministrators->isDesignated($actor->id),
            403,
            'Only the Concord Administrator can reset passwords.'
        );

        $target = User::query()->findOrFail($user);

        $temporaryPassword = $data['mode'] === 'generate'
            ? \Illuminate\Support\Str::password(16)
            : $data['password'];

        DB::transaction(function () use (
            $target,
            $temporaryPassword,
            $actor,
            $request,
            $sessions
        ): void {
            $target->password = $temporaryPassword;
            $target->must_change_password = true;
            $target->save();

            $sessions->revokeAllForUser(
                $target->id,
                'password_reset'
            );

            Audit::log(
                'identity.user_password_reset',
                $actor->id,
                $request,
                entityType: 'user',
                entityId: $target->id,
                meta: [
                    'target_user_id' => $target->id,
                    'reset_mode' => $request->input('mode'),
                    'must_change_password' => true,
                ]
            );
        });

        return response()->json([
            'message' => 'Password reset successfully.',
            'temporary_password' => $temporaryPassword,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,deactivated,disabled',
        ]);

        $users = User::query()
            ->with(['person.types', 'person.student'])
            ->when(
                ! empty(trim($filters['search'] ?? '')),
                function ($query) use ($filters): void {
                    $search = '%' . trim($filters['search']) . '%';

                    $query->where(function ($query) use ($search): void {
                        $query->where('username', 'like', $search)
                            ->orWhereHas('person', function ($personQuery) use ($search): void {
                                $personQuery->where('first_name', 'like', $search)
                                    ->orWhere('middle_name', 'like', $search)
                                    ->orWhere('last_name', 'like', $search)
                                    ->orWhere('name_suffix', 'like', $search);
                            });
                    });
                }
            )
            ->when(
                ! empty($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->orderBy('username')
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        $users->getCollection()->transform(function (User $user): array {
            $person = $user->person;

            return [
                'id' => $user->id,
                'person_id' => $user->person_id,
                'username' => $user->username,
                'status' => $user->status,
                'is_it_admin' => $user->is_it_admin,
                'created_at' => $user->created_at,
                'person' => $person ? [
                    'id' => $person->id,
                    'first_name' => $person->first_name,
                    'middle_name' => $person->middle_name,
                    'last_name' => $person->last_name,
                    'name_suffix' => $person->name_suffix,
                    'gender' => $person->gender,
                    'email' => $person->email,
                    'display_name' => $person->display_name,
                    'types' => $person->types->pluck('type')->all(),
                    'student_number' => $person->student?->student_number,
                ] : null,
            ];
        });

        return response()->json($users);
    }

    public function store(
        Request $request,
        UserCreationService $userCreation,
        PlatformAdministratorService $platformAdministrators
    ): JsonResponse {
        $data = $request->validate([
            'username' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'name_suffix' => 'nullable|string|max:30',
            'gender' => 'nullable|string|max:32',
            'email' => 'nullable|string|email|max:255',
            'types' => 'required|array|min:1',
            'types.*' => 'required|string|in:'.implode(',', PersonType::TYPES),
            'student_number' => 'nullable|string|max:64',
        ]);

        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $platformAdministrators->isDesignated($actor->id),
            403,
            'Only the Concord Administrator can create accounts.'
        );

        $result = $userCreation->create($data);
        $user = $result['user'];
        $person = $result['person'];

        Audit::log(
            'identity.user_created',
            $actor->id,
            $request,
            entityType: 'user',
            entityId: $user->id,
            meta: [
                'target_user_id' => $user->id,
                'person_id' => $person->id,
                'types' => $person->types->pluck('type')->all(),
                'created_by' => 'administrator',
            ]
        );

        return response()->json([
            'message' => 'Account created.',
            'person' => [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'middle_name' => $person->middle_name,
                'last_name' => $person->last_name,
                'name_suffix' => $person->name_suffix,
                'gender' => $person->gender,
                'email' => $person->email,
                'preferences' => $person->preferences ?? [],
                'types' => $person->types->pluck('type')->all(),
                'student_number' => $person->student?->student_number,
            ],
            'user' => [
                'id' => $user->id,
                'person_id' => $user->person_id,
                'username' => $user->username,
                'status' => $user->status,
            ],
            'initial_password' => $result['initial_password'],
        ], 201);
    }
}


