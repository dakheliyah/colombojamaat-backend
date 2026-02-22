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
use App\Http\Controllers\SharafDefinitionMappingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SharafTypeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WajebaatController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SilaFitraConfigController;
use App\Http\Controllers\SilaFitraController;
use Illuminate\Support\Facades\Route;
// Auth Session (user cookie)
Route::get('/auth/session', [AuthSessionController::class, 'show']);
Route::post('/auth/login', [AuthSessionController::class, 'login']);
Route::post('/auth/logout', [AuthSessionController::class, 'logout']);
// Census routes
Route::get('/census', [CensusController::class, 'index']);
Route::get('/census/search', [CensusController::class, 'search']);
Route::get('/census/family/{hof_its}', [CensusController::class, 'familyMembers']);
Route::get('/census/{its_id}/with-relations', [CensusController::class, 'showWithRelations']);
Route::get('/census/{its_id}', [CensusController::class, 'show']);
// Family routes
Route::get('/families/{hof_its}/summary', [FamilySummaryController::class, 'show']);
// Roles (all assignable roles for user create/edit forms)
Route::get('/roles', [RoleController::class, 'index']);
// User routes
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/its/{its_no}', [UserController::class, 'showByItsNo']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
// Miqaat routes (active before {miqaat_id} so "active" is not captured as id)
Route::get('/miqaats', [MiqaatController::class, 'index']);
Route::get('/miqaats/active', [MiqaatController::class, 'active']);
Route::post('/miqaats', [MiqaatController::class, 'store']);
Route::patch('/miqaats/{id}', [MiqaatController::class, 'update']);
Route::put('/miqaats/{id}', [MiqaatController::class, 'update']);
Route::get('/miqaats/{miqaat_id}/events', [EventController::class, 'byMiqaatId']);
Route::get('/miqaats/{miqaat_id}/sharaf-report-summary-cross-events', [EventController::class, 'sharafReportSummaryCrossEvents']);
Route::get('/miqaats/{miqaat_id}/miqaat-checks', [MiqaatCheckController::class, 'index']);
Route::put('/miqaats/{miqaat_id}/miqaat-checks', [MiqaatCheckController::class, 'upsert']);
Route::post('/miqaats/{miqaat_id}/miqaat-checks', [MiqaatCheckController::class, 'upsert']);
// Sila Fitra routes (config, calculations, receipt)
Route::middleware('user.from.cookie')->group(function () {
    Route::get('/miqaats/{miqaat_id}/sila-fitra-config', [SilaFitraConfigController::class, 'show']);
    Route::put('/miqaats/{miqaat_id}/sila-fitra-config', [SilaFitraConfigController::class, 'update']);
    Route::get('/miqaats/{miqaat_id}/sila-fitra/me', [SilaFitraController::class, 'me']);
    Route::post('/miqaats/{miqaat_id}/sila-fitra/save', [SilaFitraController::class, 'save']);
    Route::post('/miqaats/{miqaat_id}/sila-fitra/receipt', [SilaFitraController::class, 'uploadReceipt']);
    Route::get('/miqaats/{miqaat_id}/sila-fitra/receipt/{calculationId}', [SilaFitraController::class, 'serveReceipt']);
    Route::get('/miqaats/{miqaat_id}/sila-fitra/submissions', [SilaFitraController::class, 'submissions']);
    Route::patch('/miqaats/{miqaat_id}/sila-fitra/{calculationId}/verify', [SilaFitraController::class, 'verify']);
});
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
Route::get('/events/{event_id}/sharaf-definitions', [SharafDefinitionController::class, 'index'])
    ->middleware('user.from.cookie');
Route::get('/events/{miqaat_id}', [EventController::class, 'byMiqaat']);
Route::get('/events/{event_id}/sharaf-report-summary', [EventController::class, 'sharafReportSummary']);
// Sharaf Type routes (CRUD)
Route::get('/sharaf-types', [SharafTypeController::class, 'index']);
Route::post('/sharaf-types', [SharafTypeController::class, 'store']);
Route::get('/sharaf-types/{id}', [SharafTypeController::class, 'show']);
Route::put('/sharaf-types/{id}', [SharafTypeController::class, 'update']);
Route::patch('/sharaf-types/{id}', [SharafTypeController::class, 'update']);
Route::delete('/sharaf-types/{id}', [SharafTypeController::class, 'destroy']);
// Sharaf Definition routes
Route::get('/sharaf-definitions/{sd_id}/sharafs', [SharafDefinitionController::class, 'sharafs']);
Route::get('/sharaf-definitions/{sd_id}/sharafs-with-members', [SharafDefinitionController::class, 'sharafsWithMembers']);
Route::post('/sharaf-definitions', [SharafDefinitionController::class, 'store']);
Route::put('/sharaf-definitions/{id}', [SharafDefinitionController::class, 'update']);
Route::patch('/sharaf-definitions/{id}', [SharafDefinitionController::class, 'update']);
Route::get('/sharaf-definitions/{id}/positions', [SharafDefinitionController::class, 'positions']);
Route::get('/sharaf-definitions/{id}/payment-definitions', [SharafDefinitionController::class, 'paymentDefinitions']);
// Sharaf Definition Mapping routes
Route::get('/sharaf-definition-mappings', [SharafDefinitionMappingController::class, 'index']);
Route::post('/sharaf-definition-mappings', [SharafDefinitionMappingController::class, 'store']);
Route::get('/sharaf-definition-mappings/{id}', [SharafDefinitionMappingController::class, 'show']);
Route::put('/sharaf-definition-mappings/{id}', [SharafDefinitionMappingController::class, 'update']);
Route::patch('/sharaf-definition-mappings/{id}', [SharafDefinitionMappingController::class, 'update']);
Route::delete('/sharaf-definition-mappings/{id}', [SharafDefinitionMappingController::class, 'destroy']);
// Position mapping routes
Route::post('/sharaf-definition-mappings/{id}/position-mappings', [SharafDefinitionMappingController::class, 'addPositionMapping']);
Route::delete('/sharaf-definition-mappings/{id}/position-mappings/{positionMappingId}', [SharafDefinitionMappingController::class, 'removePositionMapping']);
// Payment definition mapping routes
Route::post('/sharaf-definition-mappings/{id}/payment-definition-mappings', [SharafDefinitionMappingController::class, 'addPaymentDefinitionMapping']);
Route::delete('/sharaf-definition-mappings/{id}/payment-definition-mappings/{paymentMappingId}', [SharafDefinitionMappingController::class, 'removePaymentDefinitionMapping']);
// Validation and shift routes
Route::get('/sharaf-definition-mappings/{id}/validate', [SharafDefinitionMappingController::class, 'validateMapping']);
Route::post('/sharaf-definition-mappings/{id}/shift', [SharafDefinitionMappingController::class, 'shift']);
Route::get('/sharaf-definition-mappings/{id}/audit-logs', [SharafDefinitionMappingController::class, 'auditLogs']);
// Sharaf Position routes
Route::post('/sharaf-positions', [SharafPositionController::class, 'store']);
Route::put('/sharaf-positions/{id}', [SharafPositionController::class, 'update']);
Route::patch('/sharaf-positions/{id}', [SharafPositionController::class, 'update']);
// Sharaf routes
Route::get('/sharafs', [SharafController::class, 'index']);
Route::post('/sharafs', [SharafController::class, 'store']);
Route::get('/sharafs/{sharaf_id}', [SharafController::class, 'show']);
Route::put('/sharafs/{sharaf_id}', [SharafController::class, 'update']);
Route::patch('/sharafs/{sharaf_id}', [SharafController::class, 'update']);
Route::delete('/sharafs/{sharaf_id}', [SharafController::class, 'destroy']);
Route::put('/sharafs/{sharaf_id}/status', [SharafController::class, 'status']);
Route::patch('/sharafs/{sharaf_id}/status', [SharafController::class, 'status']);
Route::post('/sharafs/{sharaf_id}/evaluate-confirmation', [SharafController::class, 'evaluateConfirmation']);
// Sharaf Member routes
Route::get('/sharafs/{sharaf_id}/members', [SharafMemberController::class, 'index']);
Route::post('/sharafs/{sharaf_id}/members', [SharafMemberController::class, 'store']);
Route::patch('/sharafs/{sharaf_id}/members/on-vms', [SharafMemberController::class, 'updateOnVmsBulk']);
Route::patch('/sharafs/{sharaf_id}/members/{its}/on-vms', [SharafMemberController::class, 'updateOnVms']);
Route::delete('/sharafs/{sharaf_id}/members/{its}', [SharafMemberController::class, 'destroy']);
// Sharaf Clearance routes
Route::post('/sharafs/{sharaf_id}/clearances', [SharafClearanceController::class, 'store']);
// Payment Definition routes
Route::get('/payment-definitions', [PaymentDefinitionController::class, 'index']);
Route::post('/payment-definitions', [PaymentDefinitionController::class, 'store']);
Route::put('/payment-definitions/{id}', [PaymentDefinitionController::class, 'update']);
Route::patch('/payment-definitions/{id}', [PaymentDefinitionController::class, 'update']);
// Sharaf Payment routes
Route::get('/sharaf-payments', [SharafPaymentController::class, 'index']);
Route::post('/sharafs/{sharaf_id}/payments', [SharafPaymentController::class, 'store']);
Route::patch('/sharafs/{sharaf_id}/payments/{payment_definition_id}', [SharafPaymentController::class, 'toggle']);
Route::post('/sharafs/{sharaf_id}/lagat', [SharafPaymentController::class, 'lagat']);
Route::post('/sharafs/{sharaf_id}/najwa', [SharafPaymentController::class, 'najwa']);
// Wajebaat (Takhmeen / Finance Ada) routes
Route::post('/wajebaat/takhmeen', [WajebaatController::class, 'takhmeenStore']);
Route::get('/wajebaat/history/{its_id}', [WajebaatController::class, 'history']);
Route::post('/miqaats/{miqaat_id}/wajebaat/takhmeen/csv-upload', [WajebaatController::class, 'takhmeenCsvUpload']);
Route::get('/wajebaat/takhmeen/csv/sample', [WajebaatController::class, 'takhmeenCsvSample']);
Route::get('/wajebaat/takhmeen/csv/guidelines', [WajebaatController::class, 'takhmeenCsvGuidelines']);
Route::get('/miqaats/{miqaat_id}/mumin-profile/{its_id}', [WajebaatController::class, 'muminProfile']);
Route::get('/miqaats/{miqaat_id}/wajebaat', [WajebaatController::class, 'index']);
Route::get('/miqaats/{miqaat_id}/wajebaat/by-its-list', [WajebaatController::class, 'wajebaatByItsList']);
Route::get('/miqaats/{miqaat_id}/wajebaat/{its_id}', [WajebaatController::class, 'show']);
Route::get('/miqaats/{miqaat_id}/wajebaat/related-its/{its_id}', [WajebaatController::class, 'relatedIts']);
Route::get('/miqaats/{miqaat_id}/wajebaat/related/{its_id}', [WajebaatController::class, 'related']);
Route::get('/miqaats/{miqaat_id}/wajebaat-categories', [WajebaatController::class, 'categories']);
Route::get('/miqaats/{miqaat_id}/wajebaat/{its_id}/clearance', [WajebaatController::class, 'clearance']);
Route::get('/miqaats/{miqaat_id}/wajebaat/{its_id}/aggregated-amounts', [WajebaatController::class, 'getAggregatedAmounts']);
Route::post('/miqaats/{miqaat_id}/wajebaat/{its_id}/categorize', [WajebaatController::class, 'categorize']);
Route::post('/miqaats/{miqaat_id}/wajebaat/categorize', [WajebaatController::class, 'categorize']);
Route::patch('/miqaats/{miqaat_id}/wajebaat/{its_id}/paid', [WajebaatController::class, 'financeAdaUpdate']);
Route::get('/miqaats/{miqaat_id}/wajebaat-groups', [WajebaatController::class, 'groupsIndex']);
Route::post('/miqaats/{miqaat_id}/wajebaat-groups', [WajebaatController::class, 'groupsStore']);
Route::get('/miqaats/{miqaat_id}/wajebaat-groups/by-master/{its_id}', [WajebaatController::class, 'getByMaster']);
Route::get('/miqaats/{miqaat_id}/wajebaat-groups/by-member/{its_id}', [WajebaatController::class, 'getByMember']);
Route::get('/miqaats/{miqaat_id}/wajebaat-groups/{wg_id}', [WajebaatController::class, 'groupMembers']);
Route::put('/miqaats/{miqaat_id}/wajebaat-groups/{wg_id}', [WajebaatController::class, 'groupsUpdate']);
Route::delete('/miqaats/{miqaat_id}/wajebaat-groups/{wg_id}', [WajebaatController::class, 'groupsDestroy']);
// Reporting routes
Route::get('/reports/entities', [ReportController::class, 'entities']);
Route::get('/reports/{entity_type}', [ReportController::class, 'index']);
Route::get('/reports/{entity_type}/fields', [ReportController::class, 'fields']);
Route::get('/reports/{entity_type}/filters', [ReportController::class, 'filters']);