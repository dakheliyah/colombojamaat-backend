<?php

use App\Http\Controllers\AuthSessionController;
use App\Http\Controllers\CensusController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FamilySummaryController;
use App\Http\Controllers\MiqaatCheckController;
use App\Http\Controllers\MiqaatCheckDefinitionController;
use App\Http\Controllers\MiqaatController;
use App\Http\Controllers\SharafController;
use App\Http\Controllers\SharafClearanceController;
use App\Http\Controllers\SharafDefinitionController;
use App\Http\Controllers\SharafMemberController;
use App\Http\Controllers\PaymentDefinitionController;
use App\Http\Controllers\SharafPaymentController;
use App\Http\Controllers\SharafPositionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WajebaatController;
use Illuminate\Support\Facades\Route;

// Auth Session (user cookie)
Route::get('/auth/session', [AuthSessionController::class, 'show']);

// Census routes
Route::get('/census', [CensusController::class, 'index']);
Route::get('/census/search', [CensusController::class, 'search']);
Route::get('/census/family/{hof_its}', [CensusController::class, 'familyMembers']);
Route::get('/census/{its_id}/with-relations', [CensusController::class, 'showWithRelations']);
Route::get('/census/{its_id}', [CensusController::class, 'show']);

// Family routes
Route::get('/families/{hof_its}/summary', [FamilySummaryController::class, 'show']);

// User routes
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/its/{its_no}', [UserController::class, 'showByItsNo']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);

// Miqaat routes
Route::get('/miqaats', [MiqaatController::class, 'index']);
Route::post('/miqaats', [MiqaatController::class, 'store']);
Route::get('/miqaats/{miqaat_id}/miqaat-checks', [MiqaatCheckController::class, 'index']);
Route::put('/miqaats/{miqaat_id}/miqaat-checks', [MiqaatCheckController::class, 'upsert']);
Route::post('/miqaats/{miqaat_id}/miqaat-checks', [MiqaatCheckController::class, 'upsert']);

// Miqaat Check Definition routes (CRUD for miqaat_check_definitions table)
Route::get('/miqaat-check-definitions', [MiqaatCheckDefinitionController::class, 'index']);
Route::post('/miqaat-check-definitions', [MiqaatCheckDefinitionController::class, 'store']);
Route::get('/miqaat-check-definitions/{mcd_id}', [MiqaatCheckDefinitionController::class, 'show']);
Route::put('/miqaat-check-definitions/{mcd_id}', [MiqaatCheckDefinitionController::class, 'update']);
Route::patch('/miqaat-check-definitions/{mcd_id}', [MiqaatCheckDefinitionController::class, 'update']);
Route::delete('/miqaat-check-definitions/{mcd_id}', [MiqaatCheckDefinitionController::class, 'destroy']);

// Events routes
Route::get('/events', [EventController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
Route::get('/events/{event_id}/sharaf-definitions', [SharafDefinitionController::class, 'index']);
Route::get('/events/{miqaat_id}', [EventController::class, 'byMiqaat']);

// Sharaf Definition routes
Route::get('/sharaf-definitions/{sd_id}/sharafs', [SharafDefinitionController::class, 'sharafs']);
Route::post('/sharaf-definitions', [SharafDefinitionController::class, 'store']);
Route::get('/sharaf-definitions/{id}/positions', [SharafDefinitionController::class, 'positions']);
Route::get('/sharaf-definitions/{id}/payment-definitions', [SharafDefinitionController::class, 'paymentDefinitions']);

// Sharaf Position routes
Route::post('/sharaf-positions', [SharafPositionController::class, 'store']);

// Sharaf routes
Route::get('/sharafs', [SharafController::class, 'index']);
Route::post('/sharafs', [SharafController::class, 'store']);
Route::get('/sharafs/{sharaf_id}', [SharafController::class, 'show']);
Route::put('/sharafs/{sharaf_id}', [SharafController::class, 'update']);
Route::delete('/sharafs/{sharaf_id}', [SharafController::class, 'destroy']);
Route::put('/sharafs/{sharaf_id}/status', [SharafController::class, 'status']);
Route::patch('/sharafs/{sharaf_id}/status', [SharafController::class, 'status']);
Route::post('/sharafs/{sharaf_id}/evaluate-confirmation', [SharafController::class, 'evaluateConfirmation']);

// Sharaf Member routes
Route::get('/sharafs/{sharaf_id}/members', [SharafMemberController::class, 'index']);
Route::post('/sharafs/{sharaf_id}/members', [SharafMemberController::class, 'store']);
Route::delete('/sharafs/{sharaf_id}/members/{its}', [SharafMemberController::class, 'destroy']);

// Sharaf Clearance routes
Route::post('/sharafs/{sharaf_id}/clearances', [SharafClearanceController::class, 'store']);

// Payment Definition routes
Route::get('/payment-definitions', [PaymentDefinitionController::class, 'index']);
Route::post('/payment-definitions', [PaymentDefinitionController::class, 'store']);

// Sharaf Payment routes
Route::get('/sharaf-payments', [SharafPaymentController::class, 'index']);
Route::post('/sharafs/{sharaf_id}/payments', [SharafPaymentController::class, 'store']);
Route::post('/sharafs/{sharaf_id}/lagat', [SharafPaymentController::class, 'lagat']);
Route::post('/sharafs/{sharaf_id}/najwa', [SharafPaymentController::class, 'najwa']);

// Wajebaat (Takhmeen / Finance Ada) routes
Route::post('/wajebaat/takhmeen', [WajebaatController::class, 'takhmeenStore']);
Route::get('/miqaats/{miqaat_id}/wajebaat/{its_id}', [WajebaatController::class, 'show']);
Route::get('/miqaats/{miqaat_id}/wajebaat/{its_id}/clearance', [WajebaatController::class, 'clearance']);
Route::patch('/miqaats/{miqaat_id}/wajebaat/{its_id}/paid', [WajebaatController::class, 'financeAdaUpdate']);
