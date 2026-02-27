<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ChannelController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MoveController;
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\MomentFinderController;
use App\Http\Controllers\PoseLibraryController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\VideoBrowseController;
use Illuminate\Support\Facades\Route;

// Frontend routes
Route::get('/', [MomentFinderController::class, 'index'])->name('home');
Route::get('/videos', [VideoBrowseController::class, 'index'])->name('videos');
Route::get('/videos/{video}', [MomentFinderController::class, 'show'])->name('videos.show');

Route::get('/poses',        [PoseLibraryController::class, 'index'])->name('poses');
Route::get('/poses/rate',   [PoseLibraryController::class, 'index'])->name('poses.rate');
Route::get('/poses/{move}', [PoseLibraryController::class, 'show'])->name('poses.show');

Route::get('/history', [SessionController::class, 'index'])->name('history');

Route::get('/stats', [StatsController::class, 'index'])->name('stats');

Route::prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

    // Queue monitor + manual triggers
    Route::get('/queue', [QueueController::class, 'index'])->name('queue');
    Route::post('/queue/trigger/scan',    [QueueController::class, 'triggerScan'])->name('queue.trigger.scan');
    Route::post('/queue/trigger/analyze', [QueueController::class, 'triggerAnalyze'])->name('queue.trigger.analyze');
    Route::post('/queue/trigger/enrich',  [QueueController::class, 'triggerEnrich'])->name('queue.trigger.enrich');
    Route::post('/queue/retry-all',       [QueueController::class, 'retryAllFailed'])->name('queue.retry-all');
    Route::post('/queue/retry/{uuid}',    [QueueController::class, 'retryFailed'])->name('queue.retry');
    Route::delete('/queue/failed/{uuid}', [QueueController::class, 'deleteFailed'])->name('queue.delete');

    // Videos browser
    Route::get('/videos',                     [VideoController::class, 'index'])->name('videos');
    Route::post('/videos/{video}/reanalyze',  [VideoController::class, 'reanalyze'])->name('videos.reanalyze');
    Route::post('/videos/requeue-all-failed', [VideoController::class, 'requeueAllFailed'])->name('videos.requeue-all-failed');

    // Yoga moves browser
    Route::get('/moves',                      [MoveController::class, 'index'])->name('moves');
    Route::get('/moves/{move}',               [MoveController::class, 'show'])->name('moves.show');
    Route::post('/moves/{move}/reenrich',     [MoveController::class, 'reenrich'])->name('moves.reenrich');
    Route::post('/moves/reenrich-all-pending',[MoveController::class, 'reenrichAllPending'])->name('moves.reenrich-all-pending');

    // Analysis logs browser
    Route::get('/logs', [LogController::class, 'index'])->name('logs');

    // Channel info
    Route::get('/channel',      [ChannelController::class, 'index'])->name('channel');
    Route::post('/channel/scan',[ChannelController::class, 'scan'])->name('channel.scan');
});
