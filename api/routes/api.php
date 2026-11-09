<?php

use App\Auth\Permission;
use App\Http\Controllers\Api\V1\AgendaSettingsController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BookingRequestController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ClinicalRecordController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentTemplateController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\OwnerController;
use App\Http\Controllers\Api\V1\PetCardController;
use App\Http\Controllers\Api\V1\PetController;
use App\Http\Controllers\Api\V1\PetDocumentController;
use App\Http\Controllers\Api\V1\Portal\PortalAuthController;
use App\Http\Controllers\Api\V1\Portal\PortalBookingController;
use App\Http\Controllers\Api\V1\Portal\PortalDocumentController;
use App\Http\Controllers\Api\V1\Portal\PortalPetController;
use App\Http\Controllers\Api\V1\PortalInvitationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PublicBookingController;
use App\Http\Controllers\Api\V1\PublicPetController;
use App\Http\Controllers\Api\V1\PublicSigningController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ReminderSettingsController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use App\Models\PersonalAccessToken;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 (see bootstrap/app.php).

// Malformed ids get a 404 instead of a PostgreSQL "invalid uuid" error.
$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
Route::pattern('branch', $uuid);
Route::pattern('member', $uuid);
Route::pattern('invitation', $uuid);
Route::pattern('token', '[A-Za-z0-9]{48}');
Route::pattern('publicToken', '[A-Za-z0-9]{32}');
Route::pattern('catalog', 'species|breeds|vaccines|procedure-types|clinical-alerts');
Route::pattern('catalogItem', $uuid);
Route::pattern('owner', $uuid);
Route::pattern('pet', $uuid);
Route::pattern('alert', $uuid);
Route::pattern('product', $uuid);
Route::pattern('movement', $uuid);
Route::pattern('sale', $uuid);
Route::pattern('item', $uuid);
Route::pattern('payment', $uuid);
Route::pattern('appointment', $uuid);
Route::pattern('service', $uuid);
Route::pattern('block', $uuid);
Route::pattern('record', $uuid);
Route::pattern('attachment', $uuid);
Route::pattern('task', $uuid);
Route::pattern('conversation', $uuid);
Route::pattern('petDocument', $uuid);
Route::pattern('signatureRole', 'owner|vet|witness');
Route::pattern('documentType', '[a-z_]{3,40}');

$can = fn (Permission $permission) => 'permission:'.$permission->value;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// ---- Public auth -------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::post('select-clinic', [AuthController::class, 'selectClinic'])
        ->middleware(['auth:sanctum', 'abilities:'.PersonalAccessToken::ABILITY_SELECT_CLINIC]);
});

// ---- Public invitations (the invitee is not in the clinic yet) -----------
// Meta's WhatsApp webhook (docs/whatsapp.md): GET = subscription handshake, POST = signed notifications.
Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:600,1');

// Public plan catalog for the landing page (prices stay in sync with the database).
Route::get('public/plans', fn () => response()->json(['data' => Plan::catalog()]))->middleware('throttle:public-card');

// Public online booking /reservar/{clinic} (docs/reservas.md): requests wait for the clinic.
Route::prefix('public/booking/{clinicSlug}')->where(['clinicSlug' => '[a-z0-9-]{1,80}'])->group(function () {
    Route::get('/', [PublicBookingController::class, 'show'])->middleware('throttle:booking-browse');
    Route::get('logo', [PublicBookingController::class, 'logo'])->middleware('throttle:booking-browse');
    Route::get('slots', [PublicBookingController::class, 'slots'])->middleware('throttle:booking-browse');
    Route::post('/', [PublicBookingController::class, 'store'])->middleware('throttle:booking-request');
});

// Public pet card (QR), docs/database.md §10b: no login, rate limited.
Route::prefix('public/pets/{publicToken}')->middleware('throttle:public-card')->group(function () {
    Route::get('/', [PublicPetController::class, 'show']);
    Route::get('photo', [PublicPetController::class, 'photo']);
});

// ---- Owner portal (Fase 4e): its own tokens (ability "tutor", no clinic) ----
Route::pattern('portalToken', '[A-Za-z0-9]{48}');
Route::pattern('portalPet', $uuid);
Route::pattern('portalDocument', $uuid);
Route::pattern('signToken', '[A-Za-z0-9]{48}');

// Remote signing of a consent (docs/documentos.md, D3): no login, link + last 4 ID digits.
Route::prefix('public/sign/{signToken}')->middleware('throttle:invitations')->group(function () {
    Route::get('/', [PublicSigningController::class, 'show']);
    Route::post('/', [PublicSigningController::class, 'sign']);
});

Route::prefix('portal')->group(function () {
    Route::post('login', [PortalAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('password/forgot', [PortalAuthController::class, 'forgotPassword'])->middleware('throttle:password-code');
    Route::post('password/reset', [PortalAuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::middleware('throttle:invitations')->group(function () {
        Route::get('invitations/{portalToken}', [PortalAuthController::class, 'invitation']);
        Route::post('invitations/{portalToken}/accept', [PortalAuthController::class, 'accept']);
    });

    Route::middleware(['auth:sanctum', 'tutor'])->group(function () {
        Route::get('me', [PortalAuthController::class, 'me']);
        Route::post('logout', [PortalAuthController::class, 'logout']);
        Route::post('invitations/{portalToken}/link', [PortalAuthController::class, 'link'])->middleware('throttle:invitations');

        Route::get('booking', [PortalBookingController::class, 'index']); // docs/reservas.md, B3
        Route::get('pets', [PortalPetController::class, 'index']);
        Route::get('pets/{portalPet}', [PortalPetController::class, 'show']);
        Route::get('pets/{portalPet}/photo', [PortalPetController::class, 'photo']);
        Route::get('pets/{portalPet}/qr', [PortalPetController::class, 'qr']);
        Route::get('pets/{portalPet}/logo', [PortalPetController::class, 'logo']);

        Route::get('documents', [PortalDocumentController::class, 'index']);
        Route::get('documents/{portalDocument}', [PortalDocumentController::class, 'show']);
        Route::post('documents/{portalDocument}/sign', [PortalDocumentController::class, 'sign']);
        Route::get('documents/{portalDocument}/pdf', [PortalDocumentController::class, 'pdf']);
    });
});

Route::prefix('invitations/{token}')->middleware('throttle:invitations')->group(function () {
    Route::get('/', [InvitationController::class, 'show']);
    Route::post('accept', [InvitationController::class, 'acceptWithNewAccount']);
    Route::post('accept-existing', [InvitationController::class, 'acceptAsExistingUser'])->middleware('auth:sanctum');
});

// ---- Clinic routes: the tenant comes from the token ----------------------
Route::middleware(['auth:sanctum', 'clinic'])->group(function () use ($can) {
    Route::get('me', [AuthController::class, 'me']);
    Route::get('me/clinics', [AuthController::class, 'myClinics']);
    Route::patch('me', [ProfileController::class, 'update']);
    Route::put('me/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:login');

    Route::get('clinic', [ClinicController::class, 'show']);
    Route::get('clinic/logo', [ClinicController::class, 'logo']);
    Route::post('auth/switch-clinic', [AuthController::class, 'switchClinic']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('branches', [BranchController::class, 'index'])->middleware($can(Permission::BranchesView));
    Route::get('branches/{branch}', [BranchController::class, 'show'])->middleware($can(Permission::BranchesView));

    Route::get('members', [MemberController::class, 'index'])->middleware($can(Permission::MembersView));
    Route::get('invitations', [MemberController::class, 'invitations'])->middleware($can(Permission::MembersInvite));

    // Catalogs: global + the clinic's own. Reading is open to every member.
    Route::get('catalogs/{catalog}', [CatalogController::class, 'index']);

    Route::middleware($can(Permission::PatientsView))->group(function () {
        Route::get('owners', [OwnerController::class, 'index']);
        Route::get('owners/{owner}', [OwnerController::class, 'show']);
        Route::get('owners/{owner}/messages', [ReminderSettingsController::class, 'ownerMessages']);
        Route::get('owners/{owner}/portal', [PortalInvitationController::class, 'show']);
        Route::get('pets/{pet}', [PetController::class, 'show']);
        Route::get('pets/{pet}/photo', [PetController::class, 'photo']);
        Route::get('pets/{pet}/card', [PetCardController::class, 'card']);
        Route::get('pets/{pet}/qr', [PetCardController::class, 'qr']);
        Route::get('pets/{pet}/public-views', [PetCardController::class, 'views']);
    });

    Route::middleware($can(Permission::AppointmentsView))->group(function () {
        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::get('appointments/availability', [AppointmentController::class, 'availability']);
        Route::get('appointments/professionals', [AppointmentController::class, 'professionals']);
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('reminders/due', [ReminderController::class, 'due']);
        Route::get('reminders', [ReminderSettingsController::class, 'index']);
        Route::get('reminders/settings', [ReminderSettingsController::class, 'show']);
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
        Route::get('booking-requests', [BookingRequestController::class, 'index']);
        Route::get('booking-requests/count', [BookingRequestController::class, 'count']);
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::get('services', [AgendaSettingsController::class, 'services']);
        Route::get('schedules', [AgendaSettingsController::class, 'schedules']);
        Route::get('schedule-blocks', [AgendaSettingsController::class, 'blocks']);
    });

    Route::middleware($can(Permission::ClinicalRecordsView))->group(function () {
        Route::get('pets/{pet}/clinical-records', [ClinicalRecordController::class, 'index']);
        Route::get('clinical-records/{record}', [ClinicalRecordController::class, 'show']);

        // Consents and authorizations signed on screen (docs/documentos.md).
        Route::get('document-types', [PetDocumentController::class, 'types']);
        Route::get('pets/{pet}/documents', [PetDocumentController::class, 'index']);
        Route::get('documents/{petDocument}', [PetDocumentController::class, 'show']);
        Route::get('documents/{petDocument}/pdf', [PetDocumentController::class, 'pdf']);
        Route::get('documents/{petDocument}/signatures/{signatureRole}', [PetDocumentController::class, 'signatureImage']);
        Route::get('pets/{pet}/clinical-history/pdf', [ClinicalRecordController::class, 'historyPdf']);
        Route::get('clinical-records/{record}/pdf', [ClinicalRecordController::class, 'recordPdf']);
        Route::get('attachments/{attachment}/download', [ClinicalRecordController::class, 'downloadAttachment']);
    });

    Route::get('tasks', [TaskController::class, 'index'])->middleware($can(Permission::TasksView));

    // Inventory (docs/inventario.md): everyone in the clinic can look.
    Route::middleware($can(Permission::InventoryView))->group(function () {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::get('inventory/alerts', [InventoryController::class, 'alerts']);
        Route::get('inventory/import-template', [InventoryController::class, 'template']);
    });

    // Sales and payments (docs/inventario.md, V2): everyone in the clinic can look.
    Route::middleware($can(Permission::SalesView))->group(function () {
        Route::get('sales', [SaleController::class, 'index']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt']);
        Route::get('owners/{owner}/account', [SaleController::class, 'ownerAccount']);
    });

    // Money reports (docs/inventario.md, V4).
    Route::get('reports/daily-close', [ReportController::class, 'dailyClose'])->middleware($can(Permission::SalesManage));
    Route::middleware($can(Permission::ReportsView))->group(function () {
        Route::get('reports/sales', [ReportController::class, 'sales']);
        Route::get('reports/sales/export', [ReportController::class, 'export']);
    });

    // The notification bell (docs/notificaciones.md). Reading/marking is allowed even read-only.
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification');

    // Plan and billing (docs/cobro.md): outside the "writable" lock on purpose,
    // an expired clinic has to be able to choose a plan.
    Route::middleware($can(Permission::ClinicSettingsManage))->group(function () {
        Route::get('billing', [BillingController::class, 'show']);
        Route::post('billing/request', [BillingController::class, 'requestPlan']);
    });

    // Writes blocked when the trial/subscription is over (reads keep working).
    Route::middleware('writable')->group(function () use ($can) {
        Route::middleware($can(Permission::ClinicSettingsManage))->group(function () {
            Route::get('onboarding', [OnboardingController::class, 'show']);
            Route::post('onboarding/dismiss', [OnboardingController::class, 'dismiss']);

            Route::get('online-booking/settings', [BookingRequestController::class, 'settings']);
            Route::get('online-booking/qr', [BookingRequestController::class, 'qr']);
            Route::put('online-booking/settings', [BookingRequestController::class, 'updateSettings']);

            // The clinic's wording of its consents (docs/documentos.md, D4).
            Route::get('document-templates', [DocumentTemplateController::class, 'index']);
            Route::put('document-templates/{documentType}', [DocumentTemplateController::class, 'update']);
            Route::delete('document-templates/{documentType}', [DocumentTemplateController::class, 'destroy']);
            Route::patch('clinic', [ClinicController::class, 'update']);
            Route::post('clinic/logo', [ClinicController::class, 'storeLogo']);
            Route::delete('clinic/logo', [ClinicController::class, 'destroyLogo']);
            Route::put('clinic/pet-card', [ClinicController::class, 'updatePetCard']);
        });

        Route::post('branches', [BranchController::class, 'store'])->middleware($can(Permission::BranchesManage));
        Route::patch('branches/{branch}', [BranchController::class, 'update'])->middleware($can(Permission::BranchesManage));

        Route::patch('members/{member}', [MemberController::class, 'update'])->middleware($can(Permission::MembersManage));
        Route::post('invitations', [MemberController::class, 'invite'])->middleware($can(Permission::MembersInvite));
        Route::delete('invitations/{invitation}', [MemberController::class, 'revokeInvitation'])->middleware($can(Permission::MembersInvite));

        Route::post('catalogs/{catalog}', [CatalogController::class, 'store'])->middleware($can(Permission::CatalogsManage));
        Route::patch('catalogs/{catalog}/{catalogItem}', [CatalogController::class, 'update'])->middleware($can(Permission::CatalogsManage));

        Route::middleware($can(Permission::PatientsManage))->group(function () {
            Route::post('owners', [OwnerController::class, 'store']);
            Route::patch('owners/{owner}', [OwnerController::class, 'update']);
            Route::post('owners/{owner}/portal-invitation', [PortalInvitationController::class, 'store']);
            Route::put('owners/{owner}/consents', [OwnerController::class, 'updateConsents']);

            Route::post('pets', [PetController::class, 'store']);
            Route::patch('pets/{pet}', [PetController::class, 'update']);
            Route::post('pets/{pet}/photo', [PetController::class, 'storePhoto']);
            Route::delete('pets/{pet}/photo', [PetController::class, 'destroyPhoto']);
            Route::post('pets/{pet}/lost-mode', [PetCardController::class, 'lostMode']);
            Route::put('pets/{pet}/card-design', [PetCardController::class, 'updateDesign']);
            Route::post('pets/{pet}/public-token', [PetController::class, 'regeneratePublicToken']);
            Route::post('pets/{pet}/alerts', [PetController::class, 'storeAlert']);
            Route::patch('pets/{pet}/alerts/{alert}', [PetController::class, 'updateAlert']);
            Route::put('pets/{pet}/lifestyle', [PetController::class, 'updateLifestyle']);
        });

        Route::middleware($can(Permission::AppointmentsManage))->group(function () {
            Route::post('booking-requests/{appointment}/accept', [BookingRequestController::class, 'accept']);
            Route::post('booking-requests/{appointment}/reject', [BookingRequestController::class, 'reject']);
            Route::post('appointments', [AppointmentController::class, 'store']);
            Route::patch('appointments/{appointment}', [AppointmentController::class, 'update']);
            Route::post('appointments/{appointment}/status', [AppointmentController::class, 'changeStatus']);
            Route::post('reminders/manual', [ReminderController::class, 'manual']);
            Route::post('conversations/{conversation}/reply', [ConversationController::class, 'reply']);
            Route::post('conversations/{conversation}/resolve', [ConversationController::class, 'resolve']);
            Route::post('dev/whatsapp/simulate', [ConversationController::class, 'simulate']); // only with WHATSAPP_DRIVER=fake
        });

        Route::middleware($can(Permission::InventoryManage))->group(function () {
            Route::post('products', [ProductController::class, 'store']);
            Route::patch('products/{product}', [ProductController::class, 'update']);
            Route::post('products/{product}/purchase', [InventoryController::class, 'purchase']);
            Route::post('products/{product}/transfer', [InventoryController::class, 'transfer']);
            Route::post('inventory/import', [InventoryController::class, 'import']);
            Route::post('inventory/lots/{movement}/close', [InventoryController::class, 'closeLot']);
        });
        Route::post('products/{product}/adjust', [InventoryController::class, 'adjust'])->middleware($can(Permission::InventoryAdjust));

        Route::middleware($can(Permission::SalesManage))->group(function () {
            Route::get('sellables', [SaleController::class, 'sellables']);
            Route::post('sales', [SaleController::class, 'store']);
            Route::patch('sales/{sale}', [SaleController::class, 'update']);
            Route::post('sales/{sale}/items', [SaleController::class, 'addItem']);
            Route::patch('sale-items/{item}', [SaleController::class, 'updateItem']);
            Route::delete('sale-items/{item}', [SaleController::class, 'removeItem']);
            Route::post('sales/{sale}/discount', [SaleController::class, 'discount']);
            Route::post('sales/{sale}/payments', [SaleController::class, 'addPayment']);
            Route::post('appointments/{appointment}/charge', [SaleController::class, 'chargeAppointment']);
        });
        Route::middleware($can(Permission::SalesVoid))->group(function () {
            Route::post('sales/{sale}/void', [SaleController::class, 'void']);
            Route::post('payments/{payment}/void', [SaleController::class, 'voidPayment']);
        });

        Route::middleware($can(Permission::TasksManage))->group(function () {
            Route::post('tasks', [TaskController::class, 'store']);
            Route::patch('tasks/{task}', [TaskController::class, 'update']);
        });

        // Edit/void rules (author, 24 h window, admin) are checked by RecordAccess.
        Route::middleware($can(Permission::ClinicalRecordsCreate))->group(function () {
            Route::post('pets/{pet}/clinical-records', [ClinicalRecordController::class, 'store']);
            Route::post('pets/{pet}/documents', [PetDocumentController::class, 'store']);
            Route::patch('documents/{petDocument}', [PetDocumentController::class, 'update']);
            Route::post('documents/{petDocument}/signatures', [PetDocumentController::class, 'sign']);
            Route::post('documents/{petDocument}/void', [PetDocumentController::class, 'void']);
            Route::post('documents/{petDocument}/remote', [PetDocumentController::class, 'sendRemote']);
            Route::delete('documents/{petDocument}/remote', [PetDocumentController::class, 'revokeRemote']);
            Route::patch('clinical-records/{record}', [ClinicalRecordController::class, 'update']);
            Route::post('clinical-records/{record}/void', [ClinicalRecordController::class, 'void']);
            Route::post('clinical-records/{record}/attachments', [ClinicalRecordController::class, 'storeAttachment']);
            Route::delete('attachments/{attachment}', [ClinicalRecordController::class, 'destroyAttachment']);
        });

        Route::middleware($can(Permission::AgendaSettingsManage))->group(function () {
            Route::post('services', [AgendaSettingsController::class, 'storeService']);
            Route::patch('services/{service}', [AgendaSettingsController::class, 'updateService']);
            Route::put('schedules', [AgendaSettingsController::class, 'replaceSchedule']);
            Route::put('reminders/settings', [ReminderSettingsController::class, 'update']);
            Route::post('schedule-blocks', [AgendaSettingsController::class, 'storeBlock']);
            Route::delete('schedule-blocks/{block}', [AgendaSettingsController::class, 'destroyBlock']);
        });

        Route::middleware($can(Permission::PatientsDelete))->group(function () {
            Route::delete('owners/{owner}', [OwnerController::class, 'destroy']);
            Route::delete('pets/{pet}', [PetController::class, 'destroy']);
        });
    });
});
