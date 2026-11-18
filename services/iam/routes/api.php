<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\StudentEnrollmentController;
use App\Http\Controllers\Admin\UserCreationController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\LegacyAppController;
use App\Http\Controllers\LegacyAppGrantSetController;
use App\Http\Controllers\LegacyAppAccessController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

        // Public Legacy App Directory
        Route::get('legacy-apps', [LegacyAppController::class, 'index']);
    });

    Route::prefix('admin')->middleware(['session.auth', 'it.admin'])->group(function () {

        // User Management
        Route::get('users', [UserCreationController::class, 'index']);
        Route::get('users/{user}', [UserCreationController::class, 'show']);
        Route::post('users', [UserCreationController::class, 'store']);
        Route::patch('users/{user}', [UserCreationController::class, 'update']);
        Route::post('users/{user}/password', [UserCreationController::class, 'resetPassword']);
        Route::post('users/{user}/reactivate', [UserManagementController::class, 'reactivate']);
        Route::post('users/{user}/reinstate', [UserManagementController::class, 'reinstate']);
        Route::post('users/{user}/disable', [UserManagementController::class, 'disable']);
        Route::post('users/{user}/deactivate', [UserManagementController::class, 'deactivate']);

        // Student Enrollments
        Route::get('students/{studentId}/enrollments', [StudentEnrollmentController::class, 'index']);
        Route::post('students/{studentId}/enrollments', [StudentEnrollmentController::class, 'store']);

        // Legacy App Directory Management: static routes first
        Route::get('legacy-apps', [\App\Http\Controllers\LegacyAppDirectoryController::class, 'index']);
        Route::post('legacy-apps', [\App\Http\Controllers\LegacyAppDirectoryController::class, 'store']);

        // Legacy App Grant Sets
        Route::get('legacy-apps/grant-sets', [LegacyAppGrantSetController::class, 'index']);
        Route::post('legacy-apps/grant-sets', [LegacyAppGrantSetController::class, 'store']);
        Route::get('legacy-apps/grant-sets/{grantSet}', [LegacyAppGrantSetController::class, 'show']);
        Route::patch('legacy-apps/grant-sets/{grantSet}', [LegacyAppGrantSetController::class, 'update']);
        Route::post('legacy-apps/grant-sets/{grantSet}/archive', [LegacyAppGrantSetController::class, 'archive']);
        Route::post('legacy-apps/grant-sets/{grantSet}/restore', [LegacyAppGrantSetController::class, 'restore']);

        // Legacy App Access
        Route::get('legacy-apps/access', [LegacyAppAccessController::class, 'index']);
        Route::post('legacy-apps/batch-assignments', [LegacyAppAccessController::class, 'batchAssign']);

        Route::get('users/{user}/legacy-apps', [LegacyAppAccessController::class, 'show']);
        Route::post('users/{user}/legacy-apps/grant-sets/{grantSet}/apply', [LegacyAppGrantSetController::class, 'apply']);
        Route::post('users/{user}/legacy-apps/{legacyApp}/grant', [LegacyAppAccessController::class, 'grant']);
        Route::post('users/{user}/legacy-apps/{legacyApp}/revoke', [LegacyAppAccessController::class, 'revoke']);

        // Legacy App Directory Management: dynamic routes last
        Route::get('legacy-apps/{legacyApp}', [\App\Http\Controllers\LegacyAppDirectoryController::class, 'show']);
        Route::patch('legacy-apps/{legacyApp}', [\App\Http\Controllers\LegacyAppDirectoryController::class, 'update']);
        Route::post('legacy-apps/{legacyApp}/activate', [\App\Http\Controllers\LegacyAppDirectoryController::class, 'activate']);
        Route::post('legacy-apps/{legacyApp}/deactivate', [\App\Http\Controllers\LegacyAppDirectoryController::class, 'deactivate']);
    });

    Route::get('edge-check', function (Request $request) {
        $userId = $request->server('CONCORD_USER_ID');

        return $userId
            ? response()->json(['user_id' => $userId])
            : response()->json(['message' => 'No identity from the edge.'], 401);
    });

});