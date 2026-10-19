<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AppCatalogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Final URLs are /api/iam/... (Laravel adds the /api prefix).
Route::prefix('iam')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('session.auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        // Called by the edge's auth_request only (the edge hides it from the outside).
        Route::get('verify', [AuthController::class, 'verify']);
    });

    // Platform catalog administration is restricted to active IT administrators.
    Route::prefix('admin')->middleware(['session.auth', 'it.admin'])->group(function () {
        Route::get('apps', [AppCatalogController::class, 'index']);
        Route::post('apps', [AppCatalogController::class, 'store']);
        Route::patch('apps/{appId}', [AppCatalogController::class, 'update']);
    });

    // Self-test for edge authentication: only answers when the edge vouched for the caller.
    Route::get('edge-check', function (Request $request) {
        $userId = $request->server('CONCORD_USER_ID');

        return $userId
            ? response()->json(['user_id' => $userId])
            : response()->json(['message' => 'No identity from the edge.'], 401);
    });
});
