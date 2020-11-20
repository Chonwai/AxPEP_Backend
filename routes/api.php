<?php

use App\Http\Controllers\Apis\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('/v1/axpep')->group(function () {
    /**
     * Tasks API ------------------------------------------------------------
     *
     * @api
     */
    // Response Specify Task By ID
    Route::get('/tasks/{id}', [TaskController::class, 'responseSpecify']);

    // Create Task by File
    Route::post('/tasks/file', [TaskController::class, 'createNewTaskByFile']);

    /**
     * Searching API ------------------------------------------------------------
     *
     * @api
     */
    // Searching Task By Email
    Route::get('/emails/{email}/tasks', [TaskController::class, 'responseSpecifyTaskByEmail']);
});
