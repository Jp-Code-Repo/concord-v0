<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AppCatalogController;
use App\Http\Controllers\GrantController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserCreationController;

// Final URLs are /api/iam/... (Laravel adds the /api prefix).
Route::prefix('iam')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware('session.auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Called by the edge's auth_request only.
        Route::get('verify', [AuthController::class, 'verify']);

        // Grant management.
        Route::post(
            'offices/{officeId}/memberships/{membershipId}/grants',
            [GrantController::class, 'store']
        );

        Route::post(
            'offices/{officeId}/memberships/{membershipId}/grants/{grantId}/revoke',
            [GrantController::class, 'revoke']
        );

        // Account management.
        Route::post(
            'users/{user}/disable',
            [UserManagementController::class, 'disable']
        );
    });

    // Platform catalog administration.
    Route::prefix('admin')
        ->middleware(['session.auth', 'it.admin'])
        ->group(function () {
            Route::get('apps', [AppCatalogController::class, 'index']);
            Route::post('apps', [AppCatalogController::class, 'store']);
            Route::patch('apps/{appId}', [AppCatalogController::class, 'update']);
            Route::post('users', [UserCreationController::class, 'store']);
        });

    // Self-test for edge authentication.
    Route::get('edge-check', function (Request $request) {
        $userId = $request->server('CONCORD_USER_ID');

        return $userId
            ? response()->json(['user_id' => $userId])
            : response()->json(['message' => 'No identity from the edge.'], 401);
    });
});
