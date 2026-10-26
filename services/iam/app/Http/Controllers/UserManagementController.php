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
    public function deactivate(
        Request $request,
        string $user,
        SessionService $sessions,
        AccessService $access
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless($actor->is_it_admin, 403, 'Only the Concord Administrator can deactivate accounts.');

        $target = User::whereKey($user)->firstOrFail();
        abort_if($target->status === User::STATUS_DEACTIVATED, 409, 'This account is already deactivated.');
        abort_if($target->is_it_admin, 409, 'The Concord Administrator account cannot be deactivated through this endpoint.');

        DB::transaction(function () use ($target, $actor, $request, $sessions): void {
            $target->status = User::STATUS_DEACTIVATED;
            $target->deactivated_at = now();
            $target->save();
            $sessions->revokeAllForUser($target->id, 'account_deactivated');

            Audit::log(
                'identity.user_deactivated',
                $actor->id,
                $request,
                entityType: 'user',
                entityId: $target->id,
                meta: ['target_user_id' => $target->id],
            );
        });

        $access->forget($target->id);

        return response()->json(['message' => 'Account deactivated.']);
    }

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
            'Only the Concord Administrator can disable accounts.'
        );

        $target = User::whereKey($user)->firstOrFail();

        abort_if(
            $target->is_it_admin,
            409,
            'The Concord Administrator account cannot be disabled through this endpoint.'
        );

        abort_if(
            $target->status === User::STATUS_DISABLED,
            409,
            'This account is already disabled.'
        );

        DB::transaction(function () use (
            $target,
            $actor,
            $request,
            $sessions
        ): void {
            $target->status = User::STATUS_DISABLED;
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