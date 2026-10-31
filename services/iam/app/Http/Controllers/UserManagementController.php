<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessService;
use App\Services\PlatformAdministratorService;
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
        AccessService $access,
        PlatformAdministratorService $platformAdministrators
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $platformAdministrators->isDesignated($actor->id),
            403,
            'Only the Concord Administrator can deactivate accounts.'
        );

        $target = User::whereKey($user)->firstOrFail();

        abort_if(
            $target->status === User::STATUS_DEACTIVATED,
            409,
            'This account is already deactivated.'
        );

        abort_if(
            $platformAdministrators->isDesignated($target->id),
            409,
            'The Concord Administrator account cannot be deactivated through this endpoint.'
        );

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
        AccessService $access,
        PlatformAdministratorService $platformAdministrators
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $platformAdministrators->isDesignated($actor->id),
            403,
            'Only the Concord Administrator can disable accounts.'
        );

        $target = User::whereKey($user)->firstOrFail();

        abort_if(
            $platformAdministrators->isDesignated($target->id),
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

    public function reactivate(
        Request $request,
        string $user,
        AccessService $access,
        PlatformAdministratorService $platformAdministrators
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $platformAdministrators->isDesignated($actor->id),
            403,
            'Only the Concord Administrator can reactivate accounts.'
        );

        $target = User::whereKey($user)->firstOrFail();

        abort_if(
            $target->status !== User::STATUS_DISABLED,
            409,
            'Only disabled accounts can be reactivated through this endpoint.'
        );

        DB::transaction(function () use ($target, $actor, $request): void {
            $target->status = User::STATUS_ACTIVE;
            $target->disabled_at = null;
            $target->save();

            Audit::log(
                'identity.user_reactivated',
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
            'message' => 'Account reactivated.',
        ]);
    }

    public function reinstate(
        Request $request,
        string $user,
        AccessService $access,
        PlatformAdministratorService $platformAdministrators
    ): JsonResponse {
        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);

        abort_unless(
            $platformAdministrators->isDesignated($actor->id),
            403,
            'Only the Concord Administrator can reinstate accounts.'
        );

        $target = User::whereKey($user)->firstOrFail();

        abort_if(
            $target->status !== User::STATUS_DEACTIVATED,
            409,
            'Only deactivated accounts can be reinstated.'
        );

        DB::transaction(function () use ($target, $actor, $request): void {
            $target->status = User::STATUS_ACTIVE;
            $target->deactivated_at = null;
            $target->save();

            Audit::log(
                'identity.user_reinstated',
                $actor->id,
                $request,
                entityType: 'user',
                entityId: $target->id,
                meta: [
                    'target_user_id' => $target->id,
                    'reinstated_by_administrator' => true,
                ]
            );
        });

        $access->forget($target->id);

        return response()->json([
            'message' => 'Account reinstated.',
        ]);
    }

}
