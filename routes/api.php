<?php

use App\Http\Controllers\Api\ServisinController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::post('/register', [ServisinController::class, 'register']);
Route::post('/login', [ServisinController::class, 'login']);
Route::post('/login/google', [ServisinController::class, 'loginGoogle']);
Route::post('/mock/send-otp', [ServisinController::class, 'sendOtpMock']);
Route::post('/mock/verify-otp', [ServisinController::class, 'verifyOtpMock']);
Route::post('/forgot-password/mock', [ServisinController::class, 'forgotPasswordMock']);
Route::post('/reset-password/mock', [ServisinController::class, 'resetPasswordMock']);
Route::get('/cms-pages/{slug}', [ServisinController::class, 'cmsPage']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ServisinController::class, 'logout']);
    Route::get('/me', [ServisinController::class, 'me']);
    Route::get('/categories', [ServisinController::class, 'categories']);
    Route::get('/categories/{id}/technicians', [ServisinController::class, 'categoryTechnicians']);
    Route::get('/technicians', [ServisinController::class, 'technicians']);
    Route::get('/technicians/{id}', [ServisinController::class, 'technicianDetail']);
    Route::get('/service-problem-types', [ServisinController::class, 'problemTypes']);
    Route::post('/price-estimate', [ServisinController::class, 'priceEstimate']);
    Route::get('/bookings/{id}', [ServisinController::class, 'bookingDetail']);
    Route::get('/chats', [ServisinController::class, 'chats']);
    Route::get('/chats/{roomId}/messages', [ServisinController::class, 'chatMessages']);
    Route::post('/chats/{roomId}/messages', [ServisinController::class, 'sendChat']);
    Route::post('/notifications/{id}/read', [ServisinController::class, 'readNotification']);
    Route::post('/notifications/read-all', [ServisinController::class, 'readAllNotifications']);
    Route::post('/upload/image', [ServisinController::class, 'uploadImage']);
    Route::get('/help-center/articles', [ServisinController::class, 'helpCenterArticles']);

    Route::middleware('role:customer')->group(function () {
        Route::get('/customer/home', [ServisinController::class, 'customerHome']);
        Route::get('/customer/profile', [ServisinController::class, 'profile']);
        Route::put('/customer/profile', [ServisinController::class, 'updateProfile']);
        Route::post('/customer/change-password', [ServisinController::class, 'changePassword']);
        Route::delete('/customer/account', [ServisinController::class, 'deleteAccount']);
        Route::get('/customer/addresses', [ServisinController::class, 'addresses']);
        Route::post('/customer/addresses', [ServisinController::class, 'storeAddress']);
        Route::put('/customer/addresses/{id}', [ServisinController::class, 'updateAddress']);
        Route::delete('/customer/addresses/{id}', [ServisinController::class, 'deleteAddress']);
        Route::post('/customer/partnership/validate', [ServisinController::class, 'validatePartnership']);
        Route::post('/customer/promo/validate', [ServisinController::class, 'validatePromo']);
        Route::get('/customer/subscriptions', [ServisinController::class, 'subscriptionPlans']);
        Route::post('/customer/subscriptions/subscribe', [ServisinController::class, 'subscribe']);
        Route::get('/customer/referral', [ServisinController::class, 'referral']);
        Route::post('/customer/referral/claim', [ServisinController::class, 'claimReferral']);
        Route::get('/customer/favorites', [ServisinController::class, 'favorites']);
        Route::post('/customer/favorites/{technician_id}', fn ($technician_id, ServisinController $controller, \Illuminate\Http\Request $request) => $controller->toggleFavorite($request, (int) $technician_id));
        Route::post('/customer/device-token', [ServisinController::class, 'storeDeviceToken']);
        Route::post('/bookings', [ServisinController::class, 'createBooking']);
        Route::get('/customer/bookings', [ServisinController::class, 'customerBookings']);
        Route::post('/bookings/{id}/cancel', [ServisinController::class, 'cancelBooking']);
        Route::post('/bookings/{id}/reschedule', [ServisinController::class, 'rescheduleBooking']);
        Route::post('/bookings/{id}/pay/mock', [ServisinController::class, 'payBooking']);
        Route::post('/bookings/{id}/review', [ServisinController::class, 'reviewBooking']);
        Route::post('/bookings/{id}/complaint', [ServisinController::class, 'complaintBooking']);
        Route::post('/bookings/{id}/warranty-claim', [ServisinController::class, 'warrantyBooking']);
        Route::get('/customer/notifications', [ServisinController::class, 'notifications']);
    });

    Route::middleware('role:technician')->group(function () {
        Route::get('/technician/dashboard', [ServisinController::class, 'technicianDashboard']);
        Route::get('/technician/calendar', [ServisinController::class, 'technicianCalendar']);
        Route::get('/technician/bank-accounts', [ServisinController::class, 'bankAccounts']);
        Route::post('/technician/bank-accounts', [ServisinController::class, 'storeBankAccount']);
        Route::delete('/technician/bank-accounts/{id}', [ServisinController::class, 'deleteBankAccount']);
        Route::get('/technician/service-areas', [ServisinController::class, 'serviceAreas']);
        Route::put('/technician/service-areas', [ServisinController::class, 'updateServiceAreas']);
        Route::put('/technician/skills', [ServisinController::class, 'updateSkills']);
        Route::post('/technician/onboarding', [ServisinController::class, 'technicianOnboarding']);
        Route::get('/technician/profile', [ServisinController::class, 'profile']);
        Route::put('/technician/profile', [ServisinController::class, 'updateProfile']);
        Route::post('/technician/documents', [ServisinController::class, 'mockOk']);
        Route::get('/technician/services', fn () => response()->json(['data' => []]));
        Route::post('/technician/services', [ServisinController::class, 'mockOk']);
        Route::get('/technician/availability', fn () => response()->json(['data' => []]));
        Route::post('/technician/availability', [ServisinController::class, 'mockOk']);
        Route::get('/technician/orders', [ServisinController::class, 'technicianOrders']);
        Route::get('/technician/orders/{id}', [ServisinController::class, 'bookingDetail']);
        foreach (['accept', 'reject', 'start-trip', 'arrived', 'start-work', 'complete'] as $action) {
            Route::post('/technician/orders/{id}/'.$action, fn ($id, ServisinController $controller, \Illuminate\Http\Request $request) => $controller->technicianOrderAction($request, (int) $id, $action));
        }
        Route::get('/technician/wallet', [ServisinController::class, 'technicianWallet']);
        Route::post('/technician/payouts', [ServisinController::class, 'requestPayout']);
        Route::get('/technician/job-history', [ServisinController::class, 'technicianOrders']);
        Route::post('/technician/location/update', [ServisinController::class, 'mockOk']);
        Route::post('/technician/online-status', [ServisinController::class, 'mockOk']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [ServisinController::class, 'adminDashboard']);
        Route::get('/users', fn (ServisinController $c) => $c->tableIndex('users'));
        Route::get('/technicians/pending', fn () => response()->json(['data' => DB::table('technician_profiles')->where('verification_status', 'pending')->get()]));
        Route::get('/technicians/{id}', [ServisinController::class, 'technicianDetail']);
        Route::post('/technicians/{id}/approve', [ServisinController::class, 'approveTechnician']);
        Route::post('/technicians/{id}/reject', [ServisinController::class, 'rejectTechnician']);
        Route::get('/categories', fn (ServisinController $c) => $c->tableIndex('service_categories'));
        Route::post('/categories', [ServisinController::class, 'mockOk']);
        Route::get('/problem-types', fn (ServisinController $c) => $c->tableIndex('service_problem_types'));
        Route::post('/problem-types', [ServisinController::class, 'mockOk']);
        Route::get('/bookings', fn (ServisinController $c) => $c->tableIndex('bookings'));
        Route::get('/bookings/{id}', [ServisinController::class, 'bookingDetail']);
        Route::post('/bookings/{id}/assign-technician', [ServisinController::class, 'assignTechnician']);
        Route::get('/complaints', fn (ServisinController $c) => $c->tableIndex('complaints'));
        Route::get('/complaints/{id}', fn ($id) => response()->json(['data' => DB::table('complaints')->find($id)]));
        Route::post('/complaints/{id}/resolve', [ServisinController::class, 'resolveComplaint']);
        Route::get('/payouts', fn (ServisinController $c) => $c->tableIndex('payouts'));
        Route::post('/payouts/{id}/process', [ServisinController::class, 'processPayout']);
        Route::get('/wallet-transactions', fn (ServisinController $c) => $c->tableIndex('wallet_transactions'));
        Route::get('/reviews', fn (ServisinController $c) => $c->tableIndex('reviews'));
        Route::post('/reviews/{id}/flag', [ServisinController::class, 'flagReview']);
        Route::post('/broadcasts', [ServisinController::class, 'createBroadcast']);
        Route::get('/broadcasts', fn (ServisinController $c) => $c->tableIndex('broadcasts'));
        Route::get('/cms-pages', fn (ServisinController $c) => $c->tableIndex('cms_pages'));
        Route::post('/cms-pages', [ServisinController::class, 'upsertCms']);
        Route::put('/cms-pages/{id}', [ServisinController::class, 'upsertCms']);
        Route::get('/settings', fn (ServisinController $c) => $c->tableIndex('admin_settings'));
        Route::put('/settings', [ServisinController::class, 'updateSettings']);
        Route::get('/activity-logs', fn (ServisinController $c) => $c->tableIndex('activity_logs'));
    });
});
