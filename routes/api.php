<?php

use App\Http\Controllers\MomentFinderController;
use App\Http\Controllers\PoseLibraryController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

Route::post('/recommendations', [RecommendationController::class, 'recommend'])->name('api.recommendations');

// Body state presets
Route::get('/presets',             [MomentFinderController::class, 'listPresets'])->name('api.presets.index');
Route::post('/presets',            [MomentFinderController::class, 'createPreset'])->name('api.presets.store');
Route::delete('/presets/{preset}', [MomentFinderController::class, 'deletePreset'])->name('api.presets.destroy');

// Pose opinions + unrated
Route::put('/moves/{move}/opinion', [PoseLibraryController::class, 'upsertOpinion'])->name('api.moves.opinion');
Route::get('/moves/unrated',        [PoseLibraryController::class, 'unrated'])->name('api.moves.unrated');

// Video search (for session logger)
Route::get('/videos/search', function (\Illuminate\Http\Request $request) {
    $q = $request->input('q', '');
    $videos = \App\Models\Video::where('title', 'like', "%{$q}%")
        ->orWhere('youtube_id', $q)
        ->limit(10)
        ->get(['id', 'title', 'thumbnail_url', 'duration_seconds']);
    return response()->json(['results' => $videos]);
})->name('api.videos.search');

// Sessions
use App\Http\Controllers\SessionController;
Route::get('/sessions',                     [SessionController::class, 'list'])->name('api.sessions.index');
Route::post('/sessions',                    [SessionController::class, 'store'])->name('api.sessions.store');
Route::get('/sessions/{session}',           [SessionController::class, 'show'])->name('api.sessions.show');
Route::put('/sessions/{session}',           [SessionController::class, 'update'])->name('api.sessions.update');
Route::post('/sessions/{session}/flags',    [SessionController::class, 'storeFlags'])->name('api.sessions.flags');
