<?php

use App\Http\Controllers\CensusController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FamilySummaryController;
use App\Http\Controllers\MiqaatController;
use App\Http\Controllers\SharafController;
use App\Http\Controllers\SharafDefinitionController;
use App\Http\Controllers\SharafPositionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Census routes
Route::get('/census', [CensusController::class, 'index']);
Route::get('/census/search', [CensusController::class, 'search']);
Route::get('/census/family/{hof_its}', [CensusController::class, 'familyMembers']);
Route::get('/census/{its_id}/with-relations', [CensusController::class, 'showWithRelations']);
Route::get('/census/{its_id}', [CensusController::class, 'show']);

// Family routes
Route::get('/families/{hof_its}/summary', [FamilySummaryController::class, 'show']);

// User routes
Route::get('/users/its/{its_no}', [UserController::class, 'showByItsNo']);

// Miqaat routes
Route::get('/miqaats', [MiqaatController::class, 'index']);
Route::post('/miqaats', [MiqaatController::class, 'store']);

// Events routes
Route::get('/events', [EventController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
Route::get('/events/{event_id}/sharaf-definitions', [SharafDefinitionController::class, 'index']);
Route::get('/events/{miqaat_id}', [EventController::class, 'byMiqaat']);

// Sharaf Definition routes
Route::get('/sharaf-definitions/{sd_id}/sharafs', [SharafDefinitionController::class, 'sharafs']);
Route::post('/sharaf-definitions', [SharafDefinitionController::class, 'store']);
Route::get('/sharaf-definitions/{id}/positions', [SharafDefinitionController::class, 'positions']);

// Sharaf Position routes
Route::post('/sharaf-positions', [SharafPositionController::class, 'store']);

// Sharaf routes
Route::get('/sharafs', [SharafController::class, 'index']);
Route::post('/sharafs', [SharafController::class, 'store']);
Route::get('/sharafs/{sharaf_id}', [SharafController::class, 'show']);
Route::delete('/sharafs/{sharaf_id}', [SharafController::class, 'destroy']);
Route::put('/sharafs/{sharaf_id}/status', [SharafController::class, 'status']);
Route::patch('/sharafs/{sharaf_id}/status', [SharafController::class, 'status']);
Route::post('/sharafs/{sharaf_id}/evaluate-confirmation', [SharafController::class, 'evaluateConfirmation']);
