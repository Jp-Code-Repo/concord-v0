<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserCreationService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserCreationController extends Controller
{
    public function store(
        Request $request,
        UserCreationService $userCreation
    ): JsonResponse {
        $data = $request->validate([
            'username' => 'required|string|max:100',
            'email' => 'required|string|email|max:255',
            'display_name' => 'required|string|max:255',
        ]);

        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $actor->is_it_admin,
            403,
            'Only IT Administrators can create accounts.'
        );

        $result = $userCreation->create($data);
        $user = $result['user'];

        Audit::log(
            'identity.user_created',
            $actor->id,
            $request,
            entityType: 'user',
            entityId: $user->id,
            meta: [
                'target_user_id' => $user->id,
                'created_by' => 'administrator',
            ]
        );

        return response()->json([
            'message' => 'Account created.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'status' => $user->status,
            ],
            'initial_password' => $result['initial_password'],
        ], 201);
    }
}
