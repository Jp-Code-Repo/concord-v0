<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\StudentEnrollmentController;
use App\Http\Controllers\Admin\UserCreationController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegacyAppController;
use App\Http\Controllers\LegacyAppGrantSetController;
use App\Http\Controllers\LegacyAppAccessController;


Route::prefix('iam')->group(function () {

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware('session.auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('me/password', [AuthController::class, 'changePassword']);
        Route::patch('me/preferences', [AuthController::class, 'updatePreferences']);
        Route::patch('me/settings', [AuthController::class, 'updateSettings']);
        Route::get('verify', [AuthController::class, 'verify']);
        Route::post('users/{user}/deactivate', [UserManagementController::class, 'deactivate']);
        Route::post('users/{user}/disable', [UserManagementController::class, 'disable']);

        Route::get('legacy-apps', [LegacyAppController::class, 'index']);
    });

    Route::prefix('admin')->middleware(['session.auth', 'it.admin'])->group(function () {
        Route::get('users', [UserCreationController::class, 'index']);
        Route::get('users/{user}', [UserCreationController::class, 'show']);
        Route::post('users', [UserCreationController::class, 'store']);
        Route::patch('users/{user}', [UserCreationController::class, 'update']);
        Route::post('users/{user}/password', [UserCreationController::class, 'resetPassword']);
        Route::post('users/{user}/reactivate', [UserManagementController::class, 'reactivate']);
        Route::post('users/{user}/reinstate', [UserManagementController::class, 'reinstate']);
        Route::post('users/{user}/disable', [UserManagementController::class, 'disable']);
        Route::post('users/{user}/deactivate', [UserManagementController::class, 'deactivate']);

        Route::get('students/{studentId}/enrollments', [StudentEnrollmentController::class, 'index']);
        Route::post('students/{studentId}/enrollments', [StudentEnrollmentController::class, 'store']);
        
         // Legacy App Grant Sets
        Route::get('legacy-apps/grant-sets', [LegacyAppGrantSetController::class, 'index']);
        Route::post('legacy-apps/grant-sets', [LegacyAppGrantSetController::class, 'store']);
        Route::get('legacy-apps/grant-sets/{grantSet}', [LegacyAppGrantSetController::class, 'show']);
        Route::patch('legacy-apps/grant-sets/{grantSet}', [LegacyAppGrantSetController::class, 'update']);
        Route::post('legacy-apps/grant-sets/{grantSet}/archive', [LegacyAppGrantSetController::class, 'archive']);
        Route::post('legacy-apps/grant-sets/{grantSet}/restore', [LegacyAppGrantSetController::class, 'restore']);
        Route::post( 'users/{user}/legacy-apps/grant-sets/{grantSet}/apply',  [LegacyAppGrantSetController::class, 'apply']);
        Route::get( 'users/{user}/legacy-apps', [LegacyAppAccessController::class, 'show'] );
    });

    Route::get('edge-check', function (Request $request) {
        $userId = $request->server('CONCORD_USER_ID');

        return $userId
            ? response()->json(['user_id' => $userId])
            : response()->json(['message' => 'No identity from the edge.'], 401);
    });

});