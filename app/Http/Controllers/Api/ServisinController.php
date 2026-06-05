<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\ServisinResource;
use App\Models\User;
use App\Services\BookingService;
use App\Services\ComplaintService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\PayoutService;
use App\Services\PricingService;
use App\Services\TechnicianMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ServisinController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private PricingService $pricing,
        private TechnicianMatchingService $matching,
        private PaymentService $payments,
        private ComplaintService $complaints,
        private PayoutService $payouts,
        private NotificationService $notifications,
    ) {}

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'unique:users,phone'],
            'password' => ['required', 'min:8'],
            'role' => ['required', Rule::in(['customer', 'technician'])],
        ]);

        $user = User::create($data);

        if ($user->role === 'customer') {
            DB::table('customer_profiles')->insert(['user_id' => $user->id, 'member_since' => today(), 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('technician_profiles')->insert(['user_id' => $user->id, 'verification_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['user' => $user, 'token' => $user->createToken('mobile')->plainTextToken], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 422);
        }

        return response()->json(['user' => $user, 'token' => $user->createToken($user->role.'-token')->plainTextToken]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function me(Request $request)
    {
        return new ServisinResource(['user' => $request->user(), 'profile' => $this->profileFor($request->user())]);
    }

    public function mockOk(Request $request)
    {
        return response()->json(['message' => 'Kode mock berhasil diproses.', 'otp' => '123456']);
    }

    public function customerHome(Request $request)
    {
        return new ServisinResource([
            'user' => $request->user(),
            'categories' => DB::table('service_categories')->where('is_active', true)->limit(8)->get(),
            'recommended_technicians' => $this->matching->recommendedForCategory(1, 6),
            'active_booking' => DB::table('bookings')->where('customer_id', $request->user()->id)->latest()->first(),
        ]);
    }

    public function profile(Request $request)
    {
        return new ServisinResource(['user' => $request->user(), 'profile' => $this->profileFor($request->user())]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => ['string'], 'phone' => ['nullable', 'string'], 'avatar' => ['nullable', 'string']]);
        $request->user()->update($data);

        return new ServisinResource($request->user()->fresh());
    }

    public function addresses(Request $request)
    {
        return ServisinResource::collection(DB::table('addresses')->where('user_id', $request->user()->id)->get());
    }

    public function storeAddress(Request $request)
    {
        $data = $request->validate([
            'label' => ['required'], 'address_line' => ['required'], 'city' => ['required'], 'district' => ['required'],
            'postal_code' => ['nullable'], 'latitude' => ['nullable', 'numeric'], 'longitude' => ['nullable', 'numeric'], 'is_default' => ['boolean'],
        ]);
        $data += ['user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()];
        $id = DB::table('addresses')->insertGetId($data);

        return new ServisinResource(DB::table('addresses')->find($id));
    }

    public function validatePartnership(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $partner = DB::table('partnerships')->where('code', strtoupper($data['code']))->where('status', 'active')->first();

        return response()->json(['valid' => (bool) $partner, 'partnership' => $partner, 'badge' => $partner ? 'Warga '.$partner->name : null]);
    }

    public function categories()
    {
        return ServisinResource::collection(DB::table('service_categories')->where('is_active', true)->get());
    }

    public function categoryTechnicians(int $id)
    {
        return ServisinResource::collection($this->matching->recommendedForCategory($id, 50));
    }

    public function technicians(Request $request)
    {
        $query = DB::table('technician_profiles')
            ->join('users', 'users.id', '=', 'technician_profiles.user_id')
            ->select('users.id', 'users.name', 'users.avatar', 'technician_profiles.*')
            ->where('technician_profiles.verification_status', 'approved');

        if ($request->boolean('online')) {
            $query->where('is_online', true);
        }

        return ServisinResource::collection($query->orderByDesc('rating_avg')->get());
    }

    public function technicianDetail(int $id)
    {
        return new ServisinResource([
            'technician' => DB::table('technician_profiles')->join('users', 'users.id', '=', 'technician_profiles.user_id')->where('users.id', $id)->select('users.id', 'users.name', 'users.email', 'users.phone', 'users.avatar', 'technician_profiles.*')->first(),
            'services' => DB::table('technician_services')->join('service_categories', 'service_categories.id', '=', 'technician_services.service_category_id')->where('technician_profile_id', DB::table('technician_profiles')->where('user_id', $id)->value('id'))->select('technician_services.*', 'service_categories.name as category_name')->get(),
            'reviews' => DB::table('reviews')->where('technician_id', $id)->latest()->limit(10)->get(),
        ]);
    }

    public function problemTypes(Request $request)
    {
        return ServisinResource::collection(DB::table('service_problem_types')->when($request->service_category_id, fn ($q) => $q->where('service_category_id', $request->service_category_id))->get());
    }

    public function priceEstimate(Request $request)
    {
        $data = $request->validate(['service_problem_type_id' => ['required', 'exists:service_problem_types,id'], 'is_emergency' => ['boolean'], 'partnership_code' => ['nullable', 'string']]);

        return new ServisinResource($this->pricing->estimate($data['service_problem_type_id'], (bool) ($data['is_emergency'] ?? false), $data['partnership_code'] ?? null));
    }

    public function createBooking(StoreBookingRequest $request)
    {
        $booking = $this->bookings->create($request->validated(), $request->user()->id);
        $this->notifications->send($request->user()->id, 'Booking dibuat', 'Booking '.$booking->booking_code.' menunggu konfirmasi teknisi.', 'booking');

        return response()->json(new ServisinResource($booking), 201);
    }

    public function customerBookings(Request $request)
    {
        return ServisinResource::collection(DB::table('bookings')->where('customer_id', $request->user()->id)->latest()->get());
    }

    public function bookingDetail(Request $request, int $id)
    {
        $booking = DB::table('bookings')->find($id);
        $this->guardBookingAccess($request, $booking);

        return new ServisinResource([
            'booking' => $booking,
            'history' => DB::table('booking_status_histories')->where('booking_id', $id)->get(),
            'chat_room' => DB::table('chat_rooms')->where('booking_id', $id)->first(),
            'invoice' => DB::table('invoices')->where('booking_id', $id)->first(),
        ]);
    }

    public function cancelBooking(Request $request, int $id)
    {
        $booking = DB::table('bookings')->find($id);
        $this->guardBookingAccess($request, $booking);
        $this->bookings->transition($id, $request->user()->id, 'cancelled');

        return response()->json(['message' => 'Booking dibatalkan.']);
    }

    public function payBooking(Request $request, int $id)
    {
        $data = $request->validate(['method' => ['required', Rule::in(['transfer', 'qris', 'ewallet', 'cod'])]]);

        return new ServisinResource($this->payments->payMock($id, $request->user()->id, $data['method']));
    }

    public function reviewBooking(Request $request, int $id)
    {
        $booking = DB::table('bookings')->find($id);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string']]);
        $data += ['booking_id' => $id, 'customer_id' => $request->user()->id, 'technician_id' => $booking->technician_id, 'created_at' => now(), 'updated_at' => now()];
        $reviewId = DB::table('reviews')->insertGetId($data);

        return new ServisinResource(DB::table('reviews')->find($reviewId));
    }

    public function complaintBooking(Request $request, int $id)
    {
        $booking = DB::table('bookings')->find($id);
        $data = $request->validate(['reason' => ['required', 'string'], 'description' => ['nullable', 'string']]);
        $data += ['booking_id' => $id, 'customer_id' => $request->user()->id, 'technician_id' => $booking->technician_id, 'status' => 'open', 'resolution_type' => 'pending', 'created_at' => now(), 'updated_at' => now()];
        $complaintId = DB::table('complaints')->insertGetId($data);
        $this->bookings->transition($id, $request->user()->id, 'complaint');

        return new ServisinResource(DB::table('complaints')->find($complaintId));
    }

    public function warrantyBooking(Request $request, int $id)
    {
        $booking = DB::table('bookings')->find($id);
        $data = $request->validate(['description' => ['required', 'string']]);
        $data += ['booking_id' => $id, 'customer_id' => $request->user()->id, 'technician_id' => $booking->technician_id, 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()];
        $claimId = DB::table('warranty_claims')->insertGetId($data);

        return new ServisinResource(DB::table('warranty_claims')->find($claimId));
    }

    public function notifications(Request $request)
    {
        return ServisinResource::collection(DB::table('notifications')->where('user_id', $request->user()->id)->orWhere('target_role', $request->user()->role)->latest()->get());
    }

    public function readNotification(Request $request, int $id)
    {
        DB::table('notifications')->where('id', $id)->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifikasi dibaca.']);
    }

    public function technicianOnboarding(Request $request)
    {
        $data = $request->validate(['bio' => ['nullable'], 'experience_years' => ['integer'], 'service_radius_km' => ['integer']]);
        DB::table('technician_profiles')->updateOrInsert(['user_id' => $request->user()->id], $data + ['verification_status' => 'pending', 'updated_at' => now(), 'created_at' => now()]);

        return new ServisinResource($this->profileFor($request->user()));
    }

    public function technicianOrders(Request $request)
    {
        return ServisinResource::collection(DB::table('bookings')->where('technician_id', $request->user()->id)->latest()->get());
    }

    public function technicianOrderAction(Request $request, int $id, string $action)
    {
        $map = [
            'accept' => 'accepted', 'reject' => 'pending', 'start-trip' => 'technician_on_the_way',
            'arrived' => 'arrived', 'start-work' => 'in_progress', 'complete' => 'completed',
        ];
        $data = $request->validate(['final_price' => ['nullable', 'numeric']]);
        $booking = DB::table('bookings')->find($id);

        if ($booking->technician_id !== $request->user()->id) {
            return response()->json(['message' => 'Order ini bukan milik teknisi yang login.'], 403);
        }

        $this->bookings->transition($id, $request->user()->id, $map[$action], $data['final_price'] ?? null);

        return response()->json(['message' => 'Status order diperbarui.', 'status' => $map[$action]]);
    }

    public function technicianWallet(Request $request)
    {
        return new ServisinResource([
            'profile' => DB::table('technician_profiles')->where('user_id', $request->user()->id)->first(),
            'transactions' => DB::table('wallet_transactions')->where('user_id', $request->user()->id)->latest()->get(),
            'payouts' => DB::table('payouts')->where('technician_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function requestPayout(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:10000']]);
        $id = DB::table('payouts')->insertGetId(['technician_id' => $request->user()->id, 'amount' => $data['amount'], 'status' => 'requested', 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return new ServisinResource(DB::table('payouts')->find($id));
    }

    public function chats(Request $request)
    {
        return ServisinResource::collection(DB::table('chat_rooms')->where('customer_id', $request->user()->id)->orWhere('technician_id', $request->user()->id)->get());
    }

    public function chatMessages(int $roomId)
    {
        return ServisinResource::collection(DB::table('chat_messages')->where('chat_room_id', $roomId)->get());
    }

    public function sendChat(Request $request, int $roomId)
    {
        $data = $request->validate(['message' => ['nullable', 'string'], 'image_path' => ['nullable', 'string']]);
        $id = DB::table('chat_messages')->insertGetId($data + ['chat_room_id' => $roomId, 'sender_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return new ServisinResource(DB::table('chat_messages')->find($id));
    }

    public function adminDashboard()
    {
        return new ServisinResource([
            'total_users' => DB::table('users')->where('role', 'customer')->count(),
            'total_technicians' => DB::table('users')->where('role', 'technician')->count(),
            'total_bookings' => DB::table('bookings')->count(),
            'total_revenue' => DB::table('payments')->where('status', 'paid')->sum('amount'),
            'pending_payouts' => DB::table('payouts')->where('status', 'requested')->count(),
            'pending_complaints' => DB::table('complaints')->where('status', 'open')->count(),
            'daily_transaction_volume' => DB::table('payments')->whereDate('created_at', today())->sum('amount'),
        ]);
    }

    public function tableIndex(string $table)
    {
        return ServisinResource::collection(DB::table($this->allowedTable($table))->latest('id')->limit(200)->get());
    }

    public function approveTechnician(int $id)
    {
        DB::table('technician_profiles')->where('user_id', $id)->update(['verification_status' => 'approved', 'rejection_reason' => null, 'updated_at' => now()]);

        return response()->json(['message' => 'Teknisi disetujui.']);
    }

    public function rejectTechnician(Request $request, int $id)
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        DB::table('technician_profiles')->where('user_id', $id)->update(['verification_status' => 'rejected', 'rejection_reason' => $data['reason'], 'updated_at' => now()]);

        return response()->json(['message' => 'Teknisi ditolak.']);
    }

    public function assignTechnician(Request $request, int $id)
    {
        $data = $request->validate(['technician_id' => ['required', 'exists:users,id']]);
        DB::table('bookings')->where('id', $id)->update(['technician_id' => $data['technician_id'], 'updated_at' => now()]);

        return response()->json(['message' => 'Teknisi berhasil ditugaskan.']);
    }

    public function resolveComplaint(Request $request, int $id)
    {
        $data = $request->validate(['resolution_type' => ['required', Rule::in(['refund', 'redispatch', 'reject'])], 'admin_decision' => ['required', 'string']]);
        $this->complaints->resolve($id, $data['resolution_type'], $data['admin_decision']);

        return response()->json(['message' => 'Komplain selesai diproses.']);
    }

    public function processPayout(int $id)
    {
        $this->payouts->process($id);

        return response()->json(['message' => 'Payout ditandai selesai.']);
    }

    public function flagReview(Request $request, int $id)
    {
        $data = $request->validate(['status' => ['required', 'string']]);
        DB::table('reviews')->where('id', $id)->update(['admin_flag_status' => $data['status'], 'updated_at' => now()]);

        return response()->json(['message' => 'Review diperbarui.']);
    }

    public function createBroadcast(Request $request)
    {
        $data = $request->validate(['title' => ['required'], 'body' => ['required'], 'target_audience' => ['required'], 'status' => ['nullable'], 'scheduled_at' => ['nullable', 'date']]);
        $id = DB::table('broadcasts')->insertGetId($data + ['admin_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return new ServisinResource(DB::table('broadcasts')->find($id));
    }

    public function upsertCms(Request $request, ?int $id = null)
    {
        $data = $request->validate(['slug' => ['required'], 'title' => ['required'], 'content' => ['required'], 'status' => ['required']]);
        $data['updated_at'] = now();
        if ($id) {
            DB::table('cms_pages')->where('id', $id)->update($data);
        } else {
            $id = DB::table('cms_pages')->insertGetId($data + ['created_at' => now()]);
        }

        return new ServisinResource(DB::table('cms_pages')->find($id));
    }

    public function updateSettings(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            DB::table('admin_settings')->updateOrInsert(['key' => $key], ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]);
        }

        return ServisinResource::collection(DB::table('admin_settings')->get());
    }

    private function profileFor(User $user): ?object
    {
        return $user->role === 'technician'
            ? DB::table('technician_profiles')->where('user_id', $user->id)->first()
            : DB::table('customer_profiles')->where('user_id', $user->id)->first();
    }

    private function guardBookingAccess(Request $request, object $booking): void
    {
        abort_if($request->user()->role === 'customer' && $booking->customer_id !== $request->user()->id, 403);
        abort_if($request->user()->role === 'technician' && $booking->technician_id !== $request->user()->id, 403);
    }

    private function allowedTable(string $table): string
    {
        abort_unless(in_array($table, [
            'users', 'technician_profiles', 'service_categories', 'service_problem_types', 'bookings',
            'complaints', 'payouts', 'wallet_transactions', 'reviews', 'broadcasts', 'cms_pages',
            'admin_settings', 'activity_logs',
        ], true), 404);

        return $table;
    }
}
