<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessService;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GrantController extends Controller
{
    public function store(
        Request $request,
        string $office,
        string $membership,
        AccessService $access
    ): JsonResponse {
        $data = $request->validate([
            'block_id' => ['required', 'string', 'size:26'],
        ]);

        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);
        $officeRow = DB::table('offices')
            ->where('id', $office)
            ->first();

        abort_unless($officeRow, 404, 'Office not found.');

        abort_unless(
            $actor->is_it_admin || $officeRow->head_user_id === $actor->id,
            403,
            'You cannot manage access for this office.'
        );

        $membershipRow = DB::table('memberships')
            ->where('id', $membership)
            ->where('office_id', $office)
            ->whereNull('ended_at')
            ->first();

        abort_unless(
            $membershipRow,
            404,
            'Active membership not found in this office.'
        );

        $blockInOffice = DB::table('blocks')
            ->join('app_groups', 'app_groups.id', '=', 'blocks.group_id')
            ->join(
                'office_apps',
                'office_apps.app_id',
                '=',
                'app_groups.app_id'
            )
            ->where('office_apps.office_id', $office)
            ->where('blocks.id', $data['block_id'])
            ->exists();

        abort_unless(
            $blockInOffice,
            422,
            'The Block is not assigned to this office.'
        );

        $delegation = DB::table('office_head_blocks')
            ->where('office_id', $office)
            ->where('block_id', $data['block_id'])
            ->first();

        abort_unless(
            $delegation && $delegation->can_delegate,
            403,
            'This Block is not configured for delegation.'
        );

        $duplicate = DB::table('grants')
            ->where('membership_id', $membership)
            ->where('block_id', $data['block_id'])
            ->whereNull('revoked_at')
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'An active grant already exists for this Block.',
            ], 409);
        }

        $grantId = (string) Str::ulid();

        try {
            DB::transaction(function () use (
                $grantId,
                $membership,
                $data,
                $actor,
                $request,
                $office
            ): void {
                DB::table('grants')->insert([
                    'id' => $grantId,
                    'membership_id' => $membership,
                    'block_id' => $data['block_id'],
                    'granted_by' => $actor->id,
                    'granted_at' => now(),
                    'revoked_by' => null,
                    'revoked_at' => null,
                    'revoke_reason' => null,
                ]);

                Audit::log(
                    'access.grant_created',
                    $actor->id,
                    $request,
                    $office,
                    'grant',
                    $grantId,
                    [
                        'membership_id' => $membership,
                        'block_id' => $data['block_id'],
                    ]
                );
            });
        } catch (QueryException $exception) {
            $isActiveGrantConflict =
                ($exception->errorInfo[1] ?? null) === 1062
                && str_contains(
                    $exception->getMessage(),
                    'grants_active_membership_block_unique'
                );

            if ($isActiveGrantConflict) {
                return response()->json([
                    'message' => 'An active grant already exists for this Block.',
                ], 409);
            }

            throw $exception;
        }

        $access->forget($membershipRow->user_id);

        return response()->json([
            'grant' => DB::table('grants')
                ->where('id', $grantId)
                ->first(),
        ], 201);
    }

    public function revoke(
        Request $request,
        string $office,
        string $membership,
        string $grant,
        AccessService $access
    ): JsonResponse {
        $data = $request->validate([
            'reason' => [
                'sometimes',
                'nullable',
                'string',
                'in:head,left_office,account_disabled,it_override',
            ],
        ]);

        $auth = $request->attributes->get('auth');
        $actor = User::findOrFail($auth['user_id']);
        $officeRow = DB::table('offices')
            ->where('id', $office)
            ->first();

        abort_unless($officeRow, 404, 'Office not found.');

        abort_unless(
            $actor->is_it_admin || $officeRow->head_user_id === $actor->id,
            403,
            'You cannot manage access for this office.'
        );

        $membershipRow = DB::table('memberships')
            ->where('id', $membership)
            ->where('office_id', $office)
            ->first();

        abort_unless(
            $membershipRow,
            404,
            'Membership not found in this office.'
        );

        $grantRow = DB::table('grants')
            ->where('id', $grant)
            ->where('membership_id', $membership)
            ->first();

        abort_unless(
            $grantRow,
            404,
            'Grant not found for this membership.'
        );

        $blockInOffice = DB::table('blocks')
            ->join('app_groups', 'app_groups.id', '=', 'blocks.group_id')
            ->join(
                'office_apps',
                'office_apps.app_id',
                '=',
                'app_groups.app_id'
            )
            ->where('office_apps.office_id', $office)
            ->where('blocks.id', $grantRow->block_id)
            ->exists();

        abort_unless(
            $blockInOffice,
            404,
            'Grant does not belong to this office.'
        );

        if ($grantRow->revoked_at !== null) {
            return response()->json([
                'message' => 'Grant has already been revoked.',
            ], 409);
        }

        $reason = $data['reason']
            ?? ($actor->is_it_admin ? 'it_override' : 'head');

        DB::transaction(function () use (
            $grant,
            $actor,
            $request,
            $office,
            $grantRow,
            $reason
        ): void {
            $updated = DB::table('grants')
                ->where('id', $grant)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_by' => $actor->id,
                    'revoked_at' => now(),
                    'revoke_reason' => $reason,
                ]);

            if ($updated !== 1) {
                abort(409, 'Grant has already been revoked.');
            }

            Audit::log(
                'access.grant_revoked',
                $actor->id,
                $request,
                $office,
                'grant',
                $grant,
                [
                    'membership_id' => $grantRow->membership_id,
                    'block_id' => $grantRow->block_id,
                    'reason' => $reason,
                ]
            );
        });

        $access->forget($membershipRow->user_id);

        return response()->json([
            'message' => 'Grant revoked.',
        ]);
    }
}
