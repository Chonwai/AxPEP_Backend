<?php

use App\Http\Controllers\Apis\AcPEPController;
use App\Http\Controllers\Apis\AmPEPController;
use App\Http\Controllers\Apis\BESToxController;
use App\Http\Controllers\Apis\CodonController;
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
    // Download the Classification Result File
    Route::get('/tasks/{id}/classification/download', [TaskController::class, 'downloadSpecifyClassification']);

    // Download the Score Result File
    Route::get('/tasks/{id}/score/download', [TaskController::class, 'downloadSpecifyPredictionScore']);

    // Download the Result File
    Route::get('/tasks/{id}/result/download', [TaskController::class, 'downloadSpecifyResult']);

    /**
     * Searching API ------------------------------------------------------------
     *
     * @api
     */
    // Searching Task By Email
    Route::get('/emails/{email}/tasks', [TaskController::class, 'responseSpecifyTaskByEmail']);

    // Response Specify Task By ID
    Route::get('/tasks/{id}', [TaskController::class, 'responseSpecify']);

    /**
     * Analysis API ------------------------------------------------------------
     *
     * @api
     */
    // Count N Days Location API
    Route::get('/analysis/count/tasks/locations', [TaskController::class, 'countDistinctIpNDays']);

    // Count N Days Task API
    Route::get('/analysis/count/tasks', [TaskController::class, 'countTasksNDays']);

    // Count Each Methods API
    Route::get('/analysis/count/method', [TaskController::class, 'countEachMethods']);

    /**
     * Codons API ------------------------------------------------------------
     *
     * @api
     */
    // Get All Codons API
    Route::get('/codons/all', [CodonController::class, 'responseAll']);
});

Route::prefix('/v1/ampep')->group(function () {
    /**
     * Tasks API ------------------------------------------------------------
     *
     * @api
     */
    // Create Task by File
    Route::post('/tasks/file', [AmPEPController::class, 'createNewTaskByFile']);

    // Create Task by Textarea
    Route::post('/tasks/textarea', [AmPEPController::class, 'createNewTaskByTextarea']);

    // Create Task by Codon
    Route::post('/tasks/codon', [AmPEPController::class, 'createNewTaskByFileAndCodon']);
});

Route::prefix('/v1/acpep')->group(function () {
    /**
     * Tasks API ------------------------------------------------------------
     *
     * @api
     */
    // Create Task by File
    Route::post('/tasks/file', [AcPEPController::class, 'createNewTaskByFile']);

    // Create Task by Textarea
    Route::post('/tasks/textarea', [AcPEPController::class, 'createNewTaskByTextarea']);
});

Route::prefix('/v1/bestox')->group(function () {
    /**
     * Tasks API ------------------------------------------------------------
     *
     * @api
     */
    // Create Task by File
    Route::post('/tasks/file', [BESToxController::class, 'createNewTaskByFile']);

    // Create Task by Textarea
    Route::post('/tasks/textarea', [BESToxController::class, 'createNewTaskByTextarea']);
});
