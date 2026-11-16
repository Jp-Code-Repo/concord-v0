<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLegacyAppGrant;
use App\Services\LegacyAppGrantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyAppAccessController extends Controller
{


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

        $userIds = $users->getCollection()->pluck('id');

        $grants = UserLegacyAppGrant::query()
            ->whereIn('user_id', $userIds)
            ->whereNull('revoked_at')
            ->get(['user_id', 'legacy_app_id']);

        $apps = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->whereIn('id', $grants->pluck('legacy_app_id')->unique())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->keyBy('id');

        $grantsByUser = $grants->groupBy('user_id');

        $users->getCollection()->transform(function (User $user) use (
            $apps,
            $grantsByUser
        ): array {
            $person = $user->person;
            $applications = [];
            $inactiveAssignments = [];

            foreach ($grantsByUser->get($user->id, collect()) as $grant) {
                $app = $apps->get($grant->legacy_app_id);

                if ($app === null) {
                    continue;
                }

                $assignment = [
                    'id' => $app->id,
                    'name' => $app->name,
                ];

                if ($app->is_active) {
                    $applications[] = $assignment;
                } else {
                    $inactiveAssignments[] = $assignment;
                }
            }

            return [
                'id' => $user->id,
                'person_id' => $user->person_id,
                'username' => $user->username,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'person' => $person ? [
                    'id' => $person->id,
                    'first_name' => $person->first_name,
                    'middle_name' => $person->middle_name,
                    'last_name' => $person->last_name,
                    'name_suffix' => $person->name_suffix,
                    'display_name' => $person->display_name,
                    'types' => $person->types->pluck('type')->all(),
                    'student_number' => $person->student?->student_number,
                ] : null,
                'effective_application_count' => count($applications),
                'applications' => $applications,
                'inactive_assignments' => $inactiveAssignments,
            ];
        });

        return response()->json($users);
    }


    public function show(
        Request $request,
        string $user,
        LegacyAppGrantService $grants,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);
        $target = User::findOrFail($user);

        return response()->json([
            'data' => $grants->getUserAccess($target, $actor),
        ]);
    }

    /**
     * Grant an individual Legacy App to a user.
     */
    public function grant(
        Request $request,
        string $user,
        string $legacyApp,
        LegacyAppGrantService $grants,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);
        $target = User::findOrFail($user);

        $grant = $grants->grant(
            target: $target,
            legacyAppId: $legacyApp,
            actor: $actor,
        );

        return response()->json([
            'data' => [
                'id' => $grant->id,
                'user_id' => $grant->user_id,
                'legacy_app_id' => $grant->legacy_app_id,
                'granted_at' => $grant->granted_at,
                'granted_by' => $grant->granted_by,
                'revoked_at' => $grant->revoked_at,
                'revoked_by' => $grant->revoked_by,
            ],
        ], 201);
    }

    /**
     * Revoke a user's current individual Legacy App grant.
     */
    public function revoke(
        Request $request,
        string $user,
        string $legacyApp,
        LegacyAppGrantService $grants,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);
        $target = User::findOrFail($user);

        $grant = UserLegacyAppGrant::query()
            ->where('user_id', $target->id)
            ->where('legacy_app_id', $legacyApp)
            ->orderByDesc('granted_at')
            ->orderByDesc('id')
            ->firstOrFail();

        $revokedGrant = $grants->revoke(
            grant: $grant,
            actor: $actor,
        );

        return response()->json([
            'data' => [
                'id' => $revokedGrant->id,
                'user_id' => $revokedGrant->user_id,
                'legacy_app_id' => $revokedGrant->legacy_app_id,
                'granted_at' => $revokedGrant->granted_at,
                'granted_by' => $revokedGrant->granted_by,
                'revoked_at' => $revokedGrant->revoked_at,
                'revoked_by' => $revokedGrant->revoked_by,
            ],
        ]);
    }

    /**
     * Assign a Grant Set or individual applications to multiple users.
     */
    public function batchAssign(
        Request $request,
        LegacyAppGrantService $grants,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'ulid', 'distinct'],

            'method' => [
                'required',
                'string',
                'in:grant_set,individual_apps',
            ],

            'grant_set_id' => [
                'required_if:method,grant_set',
                'prohibited_unless:method,grant_set',
                'ulid',
            ],

            'legacy_app_ids' => [
                'required_if:method,individual_apps',
                'prohibited_unless:method,individual_apps',
                'array',
                'min:1',
            ],
            'legacy_app_ids.*' => [
                'required',
                'ulid',
                'distinct',
            ],
        ]);

        $result = $grants->batchAssign(
            userIds: $data['user_ids'],
            method: $data['method'],
            actor: $actor,
            grantSetId: $data['grant_set_id'] ?? null,
            legacyAppIds: $data['legacy_app_ids'] ?? [],
        );

        return response()->json([
            'data' => $result,
        ], 201);
    }
}
