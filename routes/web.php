<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AgentPerformanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortalWebController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ScholarshipController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentRequestController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\IntakeCalendarController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\UniversityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CrmMessageController;
use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\SaasTenantController;
use App\Http\Controllers\SaasPackageController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\StudyCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index']);
Route::get('/register', [LandingController::class, 'showRegister']);
Route::post('/register', [LandingController::class, 'register']);
Route::get('/privacy-policy', [LandingController::class, 'privacy']);
Route::get('/contact', [LandingController::class, 'contact']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->middleware('guest:crm');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware(['guest:crm', 'throttle:5,1']);
Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->middleware('guest:crm');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware(['guest:crm', 'throttle:5,1']);
Route::get('/up', [HealthController::class, 'readiness'])->middleware('throttle:30,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.crm');
Route::post('/portal/logout', [PortalWebController::class, 'logout'])->middleware('auth.student');

Route::get('/portal/login', [PortalWebController::class, 'showLogin']);
Route::post('/portal/login', [PortalWebController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth.crm', 'tenant', 'subscription.active'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/pipeline', [PipelineController::class, 'index'])->middleware('permission:students.view');
    Route::post('/pipeline/move', [PipelineController::class, 'move'])->middleware('permission:students.update');
    Route::post('/pipeline/preferences', [PipelineController::class, 'savePreferences'])->middleware('permission:students.view');

    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:students.view');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:students.create');
    Route::get('/students/{id}', [StudentController::class, 'show'])->middleware('permission:students.view');
    Route::put('/students/{id}', [StudentController::class, 'update'])->middleware('permission:students.update');
    Route::post('/students/{id}/reset-password', [StudentController::class, 'resetPassword'])->middleware('permission:students.update');
    Route::post('/students/{id}/documents', [StudentController::class, 'uploadDocument'])->middleware('permission:students.update');
    Route::get('/students/{id}/documents/{documentId}/view', [StudentController::class, 'viewDocument'])->middleware('permission:students.view');
    Route::post('/students/{id}/documents/{documentId}/verify', [StudentController::class, 'verifyDocument'])->middleware('permission:students.update');
    Route::post('/students/{id}/documents/{documentId}/reject', [StudentController::class, 'rejectDocument'])->middleware('permission:students.update');
    Route::delete('/students/{id}/documents/{documentId}', [StudentController::class, 'deleteDocument'])->middleware('permission:students.update');
    Route::delete('/students/{id}', [StudentController::class, 'destroy'])->middleware('permission:students.delete');

    Route::get('/universities', [UniversityController::class, 'index'])->middleware('permission:universities.view');
    Route::post('/universities', [UniversityController::class, 'store'])->middleware('permission:universities.create');
    Route::post('/universities/deduplicate', [UniversityController::class, 'deduplicate'])->middleware('permission:universities.update');
    Route::get('/universities/template/export', [UniversityController::class, 'exportTemplate'])->middleware('permission:universities.view');
    Route::post('/universities/import', [UniversityController::class, 'import'])->middleware('permission:universities.create');
    Route::post('/universities/delete-all', [UniversityController::class, 'deleteAll'])->middleware('permission:universities.delete');
    Route::post('/universities/bulk-delete', [UniversityController::class, 'bulkDestroy'])->middleware('permission:universities.delete');
    Route::delete('/universities/{id}/programs/{programId}', [UniversityController::class, 'destroyProgram'])->middleware('permission:universities.update');
    Route::post('/universities/{id}/document-requirements', [UniversityController::class, 'storeDocumentRequirement'])->middleware('permission:universities.update');
    Route::delete('/universities/{id}/document-requirements/{requirementId}', [UniversityController::class, 'destroyDocumentRequirement'])->middleware('permission:universities.update');
    Route::get('/universities/{id}', [UniversityController::class, 'show'])->middleware('permission:universities.view');
    Route::put('/universities/{id}', [UniversityController::class, 'update'])->middleware('permission:universities.update');
    Route::delete('/universities/{id}', [UniversityController::class, 'destroy'])->middleware('permission:universities.delete');

    Route::get('/applications', [ApplicationController::class, 'index'])->middleware('permission:applications.view');
    Route::post('/applications', [ApplicationController::class, 'store'])->middleware('permission:applications.create');
    Route::get('/applications/options/universities', [ApplicationController::class, 'universitiesByCountry'])->middleware('permission:applications.view');
    Route::get('/applications/options/programs', [ApplicationController::class, 'programsByUniversity'])->middleware('permission:applications.view');
    Route::get('/applications/{id}', [ApplicationController::class, 'show'])->middleware('permission:applications.view');
    Route::put('/applications/{id}', [ApplicationController::class, 'update'])->middleware('permission:applications.update');
    Route::delete('/applications/{id}', [ApplicationController::class, 'destroy'])->middleware('permission:applications.delete');
    Route::post('/applications/{id}/visa', [ApplicationController::class, 'saveVisaCase'])->middleware('permission:applications.update');

    Route::get('/agents', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::get('/agents/performance', [AgentPerformanceController::class, 'index'])->middleware('permission:agent_performance.view');
    Route::get('/agents/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::post('/agents', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::put('/agents/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('/agents/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::post('/agents/roles/permissions', [UserController::class, 'updateRolePermissions'])->middleware('permission:users.update');
    Route::post('/agents/{id}/permissions', [UserController::class, 'updateUserPermissions'])->middleware('permission:users.update');

    Route::get('/student-requests', [StudentRequestController::class, 'index'])->middleware('permission:student_requests.view');
    Route::get('/student-requests/{id}', [StudentRequestController::class, 'show'])->middleware('permission:student_requests.view');
    Route::post('/student-requests/{id}/approve', [StudentRequestController::class, 'approve'])->middleware('permission:student_requests.approve');
    Route::post('/student-requests/{id}/reject', [StudentRequestController::class, 'reject'])->middleware('permission:student_requests.reject');

    Route::get('/tasks', [TaskController::class, 'index'])->middleware('permission:tasks.view');
    Route::get('/tasks/{id}', [TaskController::class, 'show'])->middleware('permission:tasks.view');
    Route::post('/tasks', [TaskController::class, 'store'])->middleware('permission:tasks.create');
    Route::put('/tasks/{id}', [TaskController::class, 'update'])->middleware('permission:tasks.update');
    Route::post('/tasks/{id}/complete', [TaskController::class, 'markComplete'])->middleware('permission:tasks.update');
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy'])->middleware('permission:tasks.delete');

    Route::get('/messages', [CrmMessageController::class, 'index'])->middleware('permission:messages.view');
    Route::post('/messages', [CrmMessageController::class, 'send'])->middleware('permission:messages.create');

    Route::get('/scholarships', [ScholarshipController::class, 'index'])->middleware('permission:scholarships.view');
    Route::post('/scholarships', [ScholarshipController::class, 'store'])->middleware('permission:scholarships.update');
    Route::put('/scholarships/{id}', [ScholarshipController::class, 'update'])->middleware('permission:scholarships.update');
    Route::delete('/scholarships/{id}', [ScholarshipController::class, 'destroy'])->middleware('permission:scholarships.update');

    Route::get('/search', [SearchController::class, 'index'])->middleware('permission:students.view');
    Route::get('/search/options/programs', [SearchController::class, 'programsByUniversity'])->middleware('permission:students.view');
    Route::get('/finance', [FinanceController::class, 'index'])->middleware('permission:finance.view');
    Route::post('/finance', [FinanceController::class, 'store'])->middleware('permission:finance.update');
    Route::put('/finance/{id}', [FinanceController::class, 'update'])->middleware('permission:finance.update');
    Route::delete('/finance/{id}', [FinanceController::class, 'destroy'])->middleware('permission:finance.update');
    Route::get('/calendar', [IntakeCalendarController::class, 'index'])->middleware('permission:applications.view');
    Route::get('/finance/commissions', [CommissionController::class, 'index'])->middleware('permission:finance.view');
    Route::post('/finance/commissions', [CommissionController::class, 'store'])->middleware('permission:finance.update');
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/automation-rules', [AutomationRuleController::class, 'index'])->middleware('permission:settings.update');
    Route::post('/automation-rules', [AutomationRuleController::class, 'store'])->middleware('permission:settings.update');
    Route::post('/automation-rules/run', [AutomationRuleController::class, 'run'])->middleware('permission:settings.update');
    Route::get('/reports/advanced', [ReportController::class, 'index'])->middleware('permission:reports.view');
    Route::get('/reports/advanced/export-csv', [ReportController::class, 'exportCsv'])->middleware('permission:reports.view');
    Route::get('/reports/advanced/export-excel', [ReportController::class, 'exportExcel'])->middleware('permission:reports.view');
    Route::get('/reports/advanced/export-pdf', [ReportController::class, 'exportPdf'])->middleware('permission:reports.view');
    Route::get('/templates', [TemplateController::class, 'index'])->middleware('permission:settings.update');
    Route::post('/templates', [TemplateController::class, 'store'])->middleware('permission:settings.update');
    Route::put('/templates/{id}', [TemplateController::class, 'update'])->middleware('permission:settings.update');
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->middleware('permission:settings.update');
    Route::get('/audit-logs', [AuditController::class, 'index'])->middleware('permission:audit.view');
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->middleware('permission:settings.update');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->middleware('permission:settings.update');
    Route::delete('/api-tokens/{id}', [ApiTokenController::class, 'destroy'])->middleware('permission:settings.update');
    Route::get('/health', [HealthController::class, 'index'])->middleware('permission:settings.view');
    Route::get('/health/backup', [HealthController::class, 'backup'])->middleware('permission:settings.update');
    Route::post('/health/restore', [HealthController::class, 'restore'])->middleware('permission:settings.update');

    Route::get('/saas/tenants', [SaasTenantController::class, 'index']);
    Route::get('/saas/tenants/{id}', [SaasTenantController::class, 'show']);
    Route::post('/saas/tenants', [SaasTenantController::class, 'store']);
    Route::put('/saas/tenants/{id}', [SaasTenantController::class, 'update']);
    Route::post('/saas/tenants/{id}/approve', [SaasTenantController::class, 'approve']);
    Route::post('/saas/tenants/{id}/features', [SaasTenantController::class, 'updateFeatures']);
    Route::get('/saas/packages', [SaasPackageController::class, 'index']);
    Route::post('/saas/packages', [SaasPackageController::class, 'store']);
    Route::put('/saas/packages/{id}', [SaasPackageController::class, 'update']);
    Route::delete('/saas/packages/{id}', [SaasPackageController::class, 'destroy']);

    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:settings.view');
    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])->middleware('permission:settings.update');
    Route::post('/settings/password', [SettingsController::class, 'changePassword'])->middleware('permission:settings.update');
    Route::post('/settings/currencies', [SettingsController::class, 'addCurrency'])->middleware('permission:settings.update');
    Route::post('/settings/whatsapp', [SettingsController::class, 'updateWhatsapp'])->middleware('permission:settings.update');
    Route::post('/settings/integrations', [SettingsController::class, 'updateIntegrations'])->middleware('permission:settings.update');
    Route::post('/settings/lead-source-costs', [SettingsController::class, 'updateLeadSourceCosts'])->middleware('permission:settings.update');
    Route::get('/settings/study-catalogs', [StudyCatalogController::class, 'index'])->middleware('permission:study_fields.view');
    Route::post('/settings/study-fields', [StudyCatalogController::class, 'storeField'])->middleware('permission:study_fields.update');
    Route::post('/settings/study-fields/sync', [StudyCatalogController::class, 'syncFields'])->middleware('permission:study_fields.update');
    Route::post('/settings/study-fields/bulk-delete', [StudyCatalogController::class, 'bulkDeleteFields'])->middleware('permission:study_fields.update');
    Route::delete('/settings/study-fields', [StudyCatalogController::class, 'deleteAllFields'])->middleware('permission:study_fields.update');
    Route::put('/settings/study-fields/{id}', [StudyCatalogController::class, 'updateField'])->middleware('permission:study_fields.update');
    Route::delete('/settings/study-fields/{id}', [StudyCatalogController::class, 'deleteField'])->middleware('permission:study_fields.update');
    Route::post('/settings/intake-terms', [StudyCatalogController::class, 'storeIntake'])->middleware('permission:study_fields.update');
    Route::delete('/settings/intake-terms/{id}', [StudyCatalogController::class, 'deleteIntake'])->middleware('permission:study_fields.update');
    Route::post('/settings/telegram/generate-code', [TelegramController::class, 'generateCode'])->middleware('permission:telegram.use');
    Route::post('/settings/telegram/unlink', [TelegramController::class, 'unlink'])->middleware('permission:telegram.use');
    Route::get('/telegram', [TelegramController::class, 'settings'])->middleware('permission:telegram.use');
    Route::post('/telegram/generate-code', [TelegramController::class, 'generateCode'])->middleware('permission:telegram.use');
    Route::post('/telegram/unlink', [TelegramController::class, 'unlink'])->middleware('permission:telegram.use');
});

Route::post('/webhooks/student-status', [WebhookController::class, 'studentStatus'])->middleware('throttle:60,1');
Route::post('/telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle'])->middleware('throttle:120,1');

Route::middleware(['auth.student', 'tenant'])->prefix('portal')->group(function (): void {
    Route::get('/dashboard', [PortalWebController::class, 'dashboard']);
    Route::get('/universities', [PortalWebController::class, 'universities']);
    Route::get('/applications', [PortalWebController::class, 'applications']);
    Route::get('/documents', [PortalWebController::class, 'documents']);
    Route::post('/documents', [PortalWebController::class, 'uploadDocument']);
    Route::get('/documents/{documentId}/view', [PortalWebController::class, 'viewDocument']);
    Route::get('/messages', [PortalWebController::class, 'messages']);
    Route::post('/messages', [PortalWebController::class, 'sendMessage']);
    Route::post('/universities/apply', [PortalWebController::class, 'applyToUniversity']);
    Route::post('/telegram/generate-code', [TelegramController::class, 'generateCode']);
    Route::post('/telegram/unlink', [TelegramController::class, 'unlink']);
});
