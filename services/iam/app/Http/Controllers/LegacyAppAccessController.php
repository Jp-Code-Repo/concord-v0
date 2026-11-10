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
}