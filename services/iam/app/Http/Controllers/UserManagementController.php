<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessService;
use App\Services\SessionService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    public function disable(
        Request $request,
        string $user,
        SessionService $sessions,
        AccessService $access
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $actor->is_it_admin,
            403,
            'Only IT Administrators can disable accounts.'
        );

        $target = User::whereKey($user)->firstOrFail();

        abort_if(
            $target->status === 'disabled',
            409,
            'This account is already disabled.'
        );

        DB::transaction(function () use (
            $target,
            $actor,
            $request,
            $sessions
        ): void {
            $target->status = 'disabled';
            $target->disabled_at = now();
            $target->save();

            $sessions->revokeAllForUser(
                $target->id,
                'account_disabled'
            );

            Audit::log(
                'identity.user_disabled',
                $actor->id,
                $request,
                entityType: 'user',
                entityId: $target->id,
                meta: [
                    'target_user_id' => $target->id,
                ]
            );
        });

        $access->forget($target->id);

        return response()->json([
            'message' => 'Account disabled.',
        ]);
    }
}