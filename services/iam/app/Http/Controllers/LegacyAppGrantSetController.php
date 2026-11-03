<?php

namespace App\Http\Controllers;

use App\Models\LegacyAppGrantSet;
use App\Models\User;
use App\Services\LegacyAppGrantSetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyAppGrantSetController extends Controller
{
    public function index(
        Request $request,
        LegacyAppGrantSetService $grantSets,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        $includeArchived = $request->boolean('include_archived');

        return response()->json([
            'data' => $grantSets->list($actor, $includeArchived),
        ]);
    }

    public function show(
        Request $request,
        string $grantSet,
        LegacyAppGrantSetService $grantSets,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        return response()->json([
            'data' => $grantSets->find(
                $grantSet,
                $actor,
                $request->boolean('include_archived'),
            ),
        ]);
        
    }

    public function store(
        Request $request,
        LegacyAppGrantSetService $grantSets,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'legacy_app_ids' => ['present', 'array'],
            'legacy_app_ids.*' => ['required', 'ulid', 'distinct'],
        ]);

        $grantSet = $grantSets->create($data, $actor);

        return response()->json([
            'data' => $grantSet,
        ], 201);
    }

    public function update(
        Request $request,
        LegacyAppGrantSet $grantSet,
        LegacyAppGrantSetService $grantSets,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'legacy_app_ids' => ['sometimes', 'array'],
            'legacy_app_ids.*' => ['required', 'ulid', 'distinct'],
        ]);

        $updatedGrantSet = $grantSets->update(
            $grantSet,
            $data,
            $actor,
        );

        return response()->json([
            'data' => $updatedGrantSet,
        ]);
    }

    public function archive(
        Request $request,
        LegacyAppGrantSet $grantSet,
        LegacyAppGrantSetService $grantSets,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        $grantSets->archive($grantSet, $actor);

        return response()->json([
            'message' => 'Grant Set archived.',
        ]);
    }

    public function restore(
        Request $request,
        string $grantSet,
        LegacyAppGrantSetService $grantSets,
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        $restoredGrantSet = $grantSets->restore(
            $grantSet,
            $actor,
        );

        return response()->json([
            'data' => $restoredGrantSet,
        ]);
    }
}