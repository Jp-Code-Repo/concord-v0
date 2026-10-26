<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersonType;
use App\Models\User;
use App\Services\UserCreationService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserCreationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['person.types', 'person.student'])
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

    public function store(Request $request, UserCreationService $userCreation): JsonResponse
    {
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
            $actor->is_it_admin,
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
