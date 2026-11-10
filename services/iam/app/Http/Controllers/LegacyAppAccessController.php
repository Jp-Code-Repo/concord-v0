<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyAppGrantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyAppAccessController extends Controller
{
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