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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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

    public function loginGoogle(Request $request)
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:100'],
            'avatar' => ['nullable', 'string'],
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'] ?? Str::before($data['email'], '@'),
                'password' => Hash::make(Str::random(32)),
                'role' => 'customer',
                'avatar' => $data['avatar'] ?? null,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        DB::table('customer_profiles')->updateOrInsert(
            ['user_id' => $user->id],
            ['member_since' => today(), 'updated_at' => now(), 'created_at' => now()],
        );

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('google-login')->plainTextToken,
            'provider' => 'google',
            'mode' => 'mock-token-validation',
        ]);
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

    public function sendOtpMock(Request $request)
    {
        $request->validate(['phone' => ['nullable', 'string'], 'email' => ['nullable', 'email']]);

        return response()->json(['message' => 'OTP mock dikirim.', 'otp' => '123456', 'expires_in_seconds' => 300]);
    }

    public function verifyOtpMock(Request $request)
    {
        $data = $request->validate(['otp' => ['required', 'string']]);

        return response()->json(['verified' => $data['otp'] === '123456', 'message' => $data['otp'] === '123456' ? 'OTP valid.' : 'OTP tidak valid.']);
    }

    public function forgotPasswordMock(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        return response()->json(['message' => 'Link reset password mock dikirim.', 'reset_token' => 'RESET-123456']);
    }

    public function resetPasswordMock(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'min:8'],
        ]);

        if ($data['token'] !== 'RESET-123456') {
            return response()->json(['message' => 'Token reset tidak valid.'], 422);
        }

        User::where('email', $data['email'])->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Password berhasil direset.']);
    }

    public function customerHome(Request $request)
    {
        return new ServisinResource([
            'user' => $request->user(),
            'banners' => DB::table('homepage_banners')
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderBy('sort_order')
                ->get(),
            'categories' => DB::table('service_categories')->where('is_active', true)->limit(8)->get(),
            'recommended_technicians' => $this->matching->recommendedForCategory(1, 6),
            'active_booking' => DB::table('bookings')->where('customer_id', $request->user()->id)->latest()->first(),
        ]);
    }

    public function customerDiscover(Request $request)
    {
        $search = $request->string('search')->toString();

        $query = DB::table('technician_profiles')
            ->join('users', 'users.id', '=', 'technician_profiles.user_id')
            ->select('technician_profiles.*', 'users.id', 'users.name', 'users.avatar')
            ->where('technician_profiles.verification_status', 'approved');

        if ($search) {
            $query->where(function ($inner) use ($search) {
                $inner->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('technician_profiles.bio', 'like', '%'.$search.'%')
                    ->orWhereExists(function ($exists) use ($search) {
                        $exists->select(DB::raw(1))
                            ->from('technician_services')
                            ->join('service_categories', 'service_categories.id', '=', 'technician_services.service_category_id')
                            ->whereColumn('technician_services.technician_profile_id', 'technician_profiles.id')
                            ->where('service_categories.name', 'like', '%'.$search.'%');
                    });
            });
        }

        $technicians = $query->orderByDesc('rating_avg')->limit(20)->get()->map(function ($tech) {
            return [
                'id' => $tech->id,
                'name' => $tech->name,
                'distance_km' => round(rand(10, 50) / 10, 1),
                'profile' => [
                    'avatar_url' => $tech->avatar,
                    'rating_avg' => $tech->rating_avg,
                    'bio' => $tech->bio,
                ]
            ];
        });

        $recentBookings = DB::table('bookings')
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                $technician = DB::table('users')->where('id', $booking->technician_id)->first();
                $problemType = DB::table('service_problem_types')->where('id', $booking->service_problem_type_id ?? null)->first();
                $category = $problemType ? DB::table('service_categories')->where('id', $problemType->service_category_id)->first() : null;
                
                return [
                    'id' => $booking->id,
                    'technician_id' => $booking->technician_id,
                    'status' => $booking->status,
                    'technician' => [
                        'name' => $technician->name ?? 'Unknown',
                        'profile' => [
                            'avatar_url' => $technician->avatar ?? null,
                        ],
                    ],
                    'service_category' => [
                        'name' => $category->name ?? 'Service',
                    ],
                ];
            });

        return new ServisinResource([
            'technicians' => $technicians,
            'recent_bookings' => $recentBookings,
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
        return response()->json(['data' => DB::table('addresses')->where('user_id', $request->user()->id)->get()]);
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

    public function updateAddress(Request $request, int $id)
    {
        $address = DB::table('addresses')->where('id', $id)->where('user_id', $request->user()->id)->first();
        abort_if(! $address, 404, 'Alamat tidak ditemukan.');

        $data = $request->validate([
            'label' => ['sometimes', 'required'],
            'address_line' => ['sometimes', 'required'],
            'city' => ['sometimes', 'required'],
            'district' => ['sometimes', 'required'],
            'postal_code' => ['nullable'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_default' => ['boolean'],
        ]);

        if (($data['is_default'] ?? false) === true) {
            DB::table('addresses')->where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        DB::table('addresses')->where('id', $id)->update($data + ['updated_at' => now()]);

        return new ServisinResource(DB::table('addresses')->find($id));
    }

    public function deleteAddress(Request $request, int $id)
    {
        $deleted = DB::table('addresses')->where('id', $id)->where('user_id', $request->user()->id)->delete();
        abort_if(! $deleted, 404, 'Alamat tidak ditemukan.');

        return response()->json(['message' => 'Alamat berhasil dihapus.']);
    }

    public function validatePartnership(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $partner = DB::table('partnerships')->where('code', strtoupper($data['code']))->where('status', 'active')->first();

        return response()->json(['valid' => (bool) $partner, 'partnership' => $partner, 'badge' => $partner ? 'Warga '.$partner->name : null]);
    }

    public function categories(Request $request)
    {
        $search = $request->string('search')->toString();

        return ServisinResource::collection(
            DB::table('service_categories')
                ->where('is_active', true)
                ->when($search, fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')))
                ->get()
        );
    }

    public function categoryTechnicians(int $id)
    {
        return ServisinResource::collection($this->matching->recommendedForCategory($id, 50));
    }

    public function technicians(Request $request)
    {
        $query = DB::table('technician_profiles')
            ->join('users', 'users.id', '=', 'technician_profiles.user_id')
            ->select('technician_profiles.*', 'users.id', 'users.name', 'users.avatar')
            ->where('technician_profiles.verification_status', 'approved');

        if ($request->boolean('online')) {
            $query->where('is_online', true);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($inner) use ($search) {
                $inner->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('technician_profiles.bio', 'like', '%'.$search.'%')
                    ->orWhereExists(function ($exists) use ($search) {
                        $exists->select(DB::raw(1))
                            ->from('technician_services')
                            ->join('service_categories', 'service_categories.id', '=', 'technician_services.service_category_id')
                            ->whereColumn('technician_services.technician_profile_id', 'technician_profiles.id')
                            ->where('service_categories.name', 'like', '%'.$search.'%');
                    });
            });
        }

        return ServisinResource::collection($query->orderByDesc('rating_avg')->get());
    }

    public function technicianDetail(int $id)
    {
        $profileId = DB::table('technician_profiles')->where('user_id', $id)->value('id');

        return new ServisinResource([
            'technician' => DB::table('technician_profiles')->join('users', 'users.id', '=', 'technician_profiles.user_id')->where('users.id', $id)->select('technician_profiles.*', 'users.id', 'users.name', 'users.email', 'users.phone', 'users.avatar')->first(),
            'services' => DB::table('technician_services')->join('service_categories', 'service_categories.id', '=', 'technician_services.service_category_id')->where('technician_profile_id', $profileId)->select('technician_services.*', 'service_categories.name as category_name')->get(),
            'reviews' => DB::table('reviews')->where('technician_id', $id)->latest()->limit(10)->get(),
            'portfolio' => DB::table('technician_documents')->where('technician_profile_id', $profileId)->whereIn('type', ['portfolio', 'certificate'])->latest()->get(),
            'completion_photos' => DB::table('booking_photos')
                ->join('bookings', 'bookings.id', '=', 'booking_photos.booking_id')
                ->where('bookings.technician_id', $id)
                ->where('booking_photos.type', 'completion')
                ->select('booking_photos.*')
                ->latest('booking_photos.id')
                ->limit(12)
                ->get(),
        ]);
    }

    public function problemTypes(Request $request)
    {
        return ServisinResource::collection(DB::table('service_problem_types')->when($request->service_category_id, fn ($q) => $q->where('service_category_id', $request->service_category_id))->get());
    }

    public function priceEstimate(Request $request)
    {
        $data = $request->validate(['service_problem_type_id' => ['required', 'exists:service_problem_types,id'], 'is_emergency' => ['boolean'], 'partnership_code' => ['nullable', 'string'], 'promo_code' => ['nullable', 'string']]);

        $estimate = $this->pricing->estimate($data['service_problem_type_id'], (bool) ($data['is_emergency'] ?? false), $data['partnership_code'] ?? null);

        if (! empty($data['promo_code'])) {
            $estimate['promo'] = $this->calculatePromo($data['promo_code'], $estimate['estimated_max_price']);
            $estimate['estimated_max_price_after_promo'] = max(0, $estimate['estimated_max_price'] - $estimate['promo']['discount_amount']);
        }

        return new ServisinResource($estimate);
    }

    public function createBooking(StoreBookingRequest $request)
    {
        $booking = $this->bookings->create($request->validated(), $request->user()->id);
        $this->notifications->send($request->user()->id, 'Booking dibuat', 'Booking '.$booking->booking_code.' menunggu konfirmasi teknisi.', 'booking');
        if ($booking->technician_id) {
            $this->notifications->send($booking->technician_id, 'Pesanan Masuk', 'Anda mendapat pesanan baru '.$booking->booking_code.' dari pelanggan.', 'job');
        }

        return response()->json(['data' => $booking], 201);
    }

    public function customerBookings(Request $request)
    {
        $bookings = \App\Models\Booking::with(['serviceCategory', 'serviceProblemType', 'technician'])->where('customer_id', $request->user()->id)->latest()->get();
        return response()->json(['data' => ['bookings' => $bookings]]);
    }

    public function bookingDetail(Request $request, int $id)
    {
        $booking = \App\Models\Booking::with(['serviceCategory', 'serviceProblemType', 'technician', 'customer', 'address'])->find($id);
        $this->guardBookingAccess($request, $booking);

        return new ServisinResource([
            'booking' => $booking,
            'history' => DB::table('booking_status_histories')->where('booking_id', $id)->get(),
            'chat_room' => DB::table('chat_rooms')->where('booking_id', $id)->first(),
            'invoice' => DB::table('invoices')->where('booking_id', $id)->first(),
            'complaints' => DB::table('complaints')->where('booking_id', $id)->latest()->get(),
            'warranty_claims' => DB::table('warranty_claims')->where('booking_id', $id)->latest()->get(),
        ]);
    }

    public function customerCompleteBooking(Request $request, int $id)
    {
        $booking = DB::table('bookings')->find($id);
        if ($booking->customer_id !== $request->user()->id) return response()->json(['message' => 'Unauthorized'], 403);
        $this->bookings->transition($id, $request->user()->id, 'completed');
        return response()->json(['message' => 'Order selesai.']);
    }

    public function cancelBooking(Request $request, int $id)
    {
        $booking = DB::table('bookings')->find($id);
        $this->guardBookingAccess($request, $booking);
        $this->bookings->transition($id, $request->user()->id, 'cancelled');

        return response()->json(['message' => 'Booking dibatalkan.']);
    }

    public function rescheduleBooking(Request $request, int $id)
    {
        $booking = DB::table('bookings')->find($id);
        $this->guardBookingAccess($request, $booking);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::table('bookings')->where('id', $id)->update(['scheduled_at' => $data['scheduled_at'], 'status' => 'pending', 'updated_at' => now()]);
        DB::table('booking_status_histories')->insert([
            'booking_id' => $id,
            'status' => 'rescheduled',
            'changed_by_user_id' => $request->user()->id,
            'note' => $data['reason'] ?? 'Jadwal booking diubah pelanggan.',
            'created_at' => now(),
        ]);

        return new ServisinResource(DB::table('bookings')->find($id));
    }

    public function payBooking(Request $request, int $id)
    {
        $data = $request->validate(['method' => ['required', Rule::in(['transfer', 'qris', 'ewallet', 'cod'])]]);

        return new ServisinResource($this->payments->payMock($id, $request->user()->id, $data['method']));
    }

    public function payExtraCharge(Request $request, int $id)
    {
        $data = $request->validate(['method' => ['required', \Illuminate\Validation\Rule::in(['transfer', 'qris', 'ewallet', 'cod'])]]);
        
        $booking = DB::table('bookings')->find($id);
        if (!$booking || $booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Booking tidak ditemukan.'], 404);
        }

        DB::table('bookings')->where('id', $id)->update(['is_extra_charge_paid' => true, 'updated_at' => now()]);

        return response()->json(['message' => 'Extra charge berhasil dibayar.', 'status' => 'paid']);
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

    public function readAllNotifications(Request $request)
    {
        DB::table('notifications')
            ->where(fn ($query) => $query->where('user_id', $request->user()->id)->orWhere('target_role', $request->user()->role))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    public function subscriptionPlans(Request $request)
    {
        return new ServisinResource([
            'plans' => DB::table('subscription_plans')->where('is_active', true)->get(),
            'current_subscription' => DB::table('customer_subscriptions')
                ->where('customer_id', $request->user()->id)
                ->where('status', 'active')
                ->where('expired_at', '>=', now())
                ->latest('expired_at')
                ->first(),
        ]);
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate(['subscription_plan_id' => ['required', 'exists:subscription_plans,id']]);
        $plan = DB::table('subscription_plans')->where('id', $data['subscription_plan_id'])->where('is_active', true)->first();
        abort_if(! $plan, 422, 'Paket langganan tidak aktif.');

        $id = DB::table('customer_subscriptions')->insertGetId([
            'customer_id' => $request->user()->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now(),
            'expired_at' => now()->addDays($plan->duration_days),
            'paid_amount' => $plan->price,
            'payment_reference' => 'SUB-MOCK-'.Str::upper(Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new ServisinResource(DB::table('customer_subscriptions')->find($id));
    }

    public function referral(Request $request)
    {
        return new ServisinResource([
            'code' => $this->referralCode($request->user()->id),
            'successful_invites' => DB::table('referrals')->where('referrer_id', $request->user()->id)->where('status', 'claimed')->count(),
            'reward_total' => DB::table('referrals')->where('referrer_id', $request->user()->id)->sum('reward_amount'),
            'history' => DB::table('referrals')->where('referrer_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function claimReferral(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $referrerId = $this->userIdFromReferralCode($data['code']);
        abort_if(! $referrerId || $referrerId === $request->user()->id, 422, 'Kode referral tidak valid.');

        $alreadyClaimed = DB::table('referrals')->where('referred_user_id', $request->user()->id)->exists();
        abort_if($alreadyClaimed, 422, 'Kamu sudah pernah memakai kode referral.');

        $id = DB::table('referrals')->insertGetId([
            'referrer_id' => $referrerId,
            'referred_user_id' => $request->user()->id,
            'code' => strtoupper($data['code']),
            'status' => 'claimed',
            'reward_amount' => 25000,
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new ServisinResource(DB::table('referrals')->find($id));
    }

    public function validatePromo(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string'], 'amount' => ['nullable', 'numeric', 'min:0']]);

        return new ServisinResource($this->calculatePromo($data['code'], (float) ($data['amount'] ?? 0)));
    }

    public function toggleFavorite(Request $request, int $technicianId)
    {
        $technician = User::where('id', $technicianId)->where('role', 'technician')->first();
        abort_if(! $technician, 404, 'Teknisi tidak ditemukan.');

        $existing = DB::table('customer_favorites')->where('customer_id', $request->user()->id)->where('technician_id', $technicianId)->first();
        if ($existing) {
            DB::table('customer_favorites')->where('id', $existing->id)->delete();

            return response()->json(['favorited' => false, 'message' => 'Teknisi dihapus dari favorit.']);
        }

        DB::table('customer_favorites')->insert([
            'customer_id' => $request->user()->id,
            'technician_id' => $technicianId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['favorited' => true, 'message' => 'Teknisi disimpan ke favorit.']);
    }

    public function favorites(Request $request)
    {
        return ServisinResource::collection(
            DB::table('customer_favorites')
                ->join('users', 'users.id', '=', 'customer_favorites.technician_id')
                ->join('technician_profiles', 'technician_profiles.user_id', '=', 'users.id')
                ->where('customer_favorites.customer_id', $request->user()->id)
                ->select('customer_favorites.id as favorite_id', 'users.id', 'users.name', 'users.avatar', 'technician_profiles.rating_avg', 'technician_profiles.completed_jobs', 'customer_favorites.created_at')
                ->latest('customer_favorites.id')
                ->get()
        );
    }

    public function storeDeviceToken(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'platform' => ['nullable', Rule::in(['ios', 'android', 'web'])]]);
        DB::table('device_tokens')->updateOrInsert(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? null, 'last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now()],
        );

        return response()->json(['message' => 'Token notifikasi tersimpan.']);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        abort_if(! Hash::check($data['current_password'], $request->user()->password), 422, 'Password lama tidak sesuai.');
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    public function deleteAccount(Request $request)
    {
        $request->user()->tokens()->delete();
        $request->user()->update([
            'name' => 'Deleted User '.$request->user()->id,
            'email' => 'deleted-'.$request->user()->id.'@servisin.local',
            'phone' => null,
            'avatar' => null,
            'status' => 'deleted',
        ]);

        return response()->json(['message' => 'Akun berhasil dihapus dari akses aplikasi.']);
    }

    public function uploadImage(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'purpose' => ['nullable', 'string', 'max:50'],
        ]);

        $file = $data['image'];
        $path = $file->store('uploads/images', 'public');
        $url = Storage::disk('public')->url($path);
        $id = DB::table('uploaded_files')->insertGetId([
            'user_id' => $request->user()?->id,
            'disk' => 'public',
            'path' => $path,
            'url' => $url,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'purpose' => $data['purpose'] ?? 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'path' => $path, 'url' => $url], 201);
    }

    public function cmsPage(string $slug)
    {
        $page = DB::table('cms_pages')->where('slug', $slug)->where('status', 'published')->first();
        abort_if(! $page, 404, 'Halaman tidak ditemukan.');

        return new ServisinResource($page);
    }

    public function technicianOnboarding(Request $request)
    {
        $data = $request->validate(['bio' => ['nullable'], 'experience_years' => ['integer'], 'service_radius_km' => ['integer']]);
        DB::table('technician_profiles')->updateOrInsert(['user_id' => $request->user()->id], $data + ['verification_status' => 'pending', 'updated_at' => now(), 'created_at' => now()]);

        return new ServisinResource($this->profileFor($request->user()));
    }

    public function technicianOrders(Request $request)
    {
        $bookings = \App\Models\Booking::with(['customer', 'address', 'serviceCategory', 'serviceProblemType'])->where('technician_id', $request->user()->id)->latest()->get();
        return ServisinResource::collection($bookings);
    }

    public function technicianOrderAction(Request $request, int $id, string $action)
    {
        $map = [
            'accept' => 'accepted', 'reject' => 'pending', 'start-trip' => 'technician_on_the_way',
            'arrived' => 'arrived', 'start-work' => 'in_progress', 'complete' => 'completed',
        ];
        $data = $request->validate(['final_price' => ['nullable', 'numeric']]);
        $booking = DB::table('bookings')->find($id);

        if ($booking->technician_id !== null && $booking->technician_id !== $request->user()->id) {
            return response()->json(['message' => 'Order ini bukan milik teknisi yang login.'], 403);
        }

        if ($booking->technician_id === null && $action === 'accept') {
            DB::table('bookings')->where('id', $id)->update(['technician_id' => $request->user()->id]);
        }

        if ($action === 'add-extra-charge') {
            $extraData = $request->validate(['extra_charge' => ['required', 'numeric']]);
            DB::table('bookings')->where('id', $id)->update([
                'extra_charge' => $extraData['extra_charge'],
                'is_extra_charge_paid' => false
            ]);
            return response()->json(['message' => 'Extra charge ditambahkan.', 'status' => $booking->status]);
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

    public function technicianDashboard(Request $request)
    {
        $userId = $request->user()->id;
        return new ServisinResource([
            'stats' => [
                'today_earnings' => DB::table('bookings')->where('technician_id', $userId)->whereDate('scheduled_at', today())->where('status', 'completed')->sum('final_price') ?? 0,
                'active_jobs' => DB::table('bookings')->where('technician_id', $userId)->whereIn('status', ['accepted', 'technician_on_the_way', 'arrived', 'in_progress'])->count(),
                'completed_jobs' => DB::table('bookings')->where('technician_id', $userId)->where('status', 'completed')->count(),
            ],
            'available_jobs' => \App\Models\Booking::with(['customer', 'serviceCategory', 'serviceProblemType', 'address'])
                ->where(function($query) use ($userId) {
                    $query->whereNull('technician_id')->orWhere('technician_id', $userId);
                })
                ->whereIn('status', ['pending', 'paid'])
                ->latest()->get(),
            'recent_jobs' => \App\Models\Booking::with(['customer', 'serviceCategory', 'serviceProblemType', 'address'])->where('technician_id', $userId)->whereNotIn('status', ['pending', 'paid'])->latest()->limit(5)->get(),
        ]);
    }

    public function technicianCalendar(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        $jobs = DB::table('bookings')
            ->where('technician_id', $request->user()->id)
            ->where('status', '!=', 'pending')
            ->where('scheduled_at', 'like', $month . '%')
            ->get();
        return ServisinResource::collection($jobs);
    }

    public function bankAccounts(Request $request)
    {
        return ServisinResource::collection(DB::table('technician_bank_accounts')->where('technician_id', $request->user()->id)->get());
    }

    public function storeBankAccount(Request $request)
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'account_name' => ['required', 'string'],
        ]);
        $id = DB::table('technician_bank_accounts')->insertGetId(
            $data + ['technician_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]
        );
        return new ServisinResource(DB::table('technician_bank_accounts')->find($id));
    }

    public function deleteBankAccount(Request $request, int $id)
    {
        DB::table('technician_bank_accounts')->where('id', $id)->where('technician_id', $request->user()->id)->delete();
        return response()->json(['message' => 'Rekening berhasil dihapus.']);
    }

    public function serviceAreas(Request $request)
    {
        return new ServisinResource([
            'radius_km' => DB::table('technician_profiles')->where('user_id', $request->user()->id)->value('service_radius_km'),
        ]);
    }

    public function updateServiceAreas(Request $request)
    {
        $data = $request->validate(['service_radius_km' => ['required', 'integer', 'min:1']]);
        DB::table('technician_profiles')->where('user_id', $request->user()->id)->update(['service_radius_km' => $data['service_radius_km'], 'updated_at' => now()]);
        return response()->json(['message' => 'Area layanan diperbarui.']);
    }

    public function updateSkills(Request $request)
    {
        $data = $request->validate([
            'skills' => ['required', 'array'],
            'skills.*.service_category_id' => ['required', 'exists:service_categories,id'],
            'skills.*.is_active' => ['boolean']
        ]);
        
        $profileId = DB::table('technician_profiles')->where('user_id', $request->user()->id)->value('id');
        
        foreach ($data['skills'] as $skill) {
            DB::table('technician_services')->updateOrInsert(
                ['technician_profile_id' => $profileId, 'service_category_id' => $skill['service_category_id']],
                ['is_active' => $skill['is_active'] ?? true, 'updated_at' => now()]
            );
        }
        return response()->json(['message' => 'Spesialisasi diperbarui.']);
    }

    public function helpCenterArticles()
    {
        return ServisinResource::collection(DB::table('cms_pages')->where('status', 'published')->get());
    }

    private function profileFor(User $user): ?object
    {
        return $user->role === 'technician'
            ? DB::table('technician_profiles')->where('user_id', $user->id)->first()
            : DB::table('customer_profiles')->where('user_id', $user->id)->first();
    }

    private function calculatePromo(string $code, float $amount): array
    {
        $promo = DB::table('promo_codes')
            ->where('code', strtoupper($code))
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expired_at')->orWhere('expired_at', '>=', now()))
            ->first();

        if (! $promo || ($promo->quota !== null && $promo->used_count >= $promo->quota) || $amount < (float) $promo->min_transaction_amount) {
            return ['valid' => false, 'code' => strtoupper($code), 'discount_amount' => 0, 'message' => 'Kode promo tidak valid atau belum memenuhi minimum transaksi.'];
        }

        $discount = $promo->discount_type === 'fixed'
            ? (float) $promo->discount_value
            : round($amount * ((float) $promo->discount_value / 100));

        return [
            'valid' => true,
            'code' => $promo->code,
            'name' => $promo->name,
            'discount_type' => $promo->discount_type,
            'discount_value' => (float) $promo->discount_value,
            'discount_amount' => min($amount, $discount),
            'final_amount' => max(0, $amount - $discount),
        ];
    }

    private function referralCode(int $userId): string
    {
        return 'SRV'.str_pad((string) $userId, 6, '0', STR_PAD_LEFT);
    }

    private function userIdFromReferralCode(string $code): ?int
    {
        $normalized = strtoupper(trim($code));
        if (! str_starts_with($normalized, 'SRV')) {
            return null;
        }

        $userId = (int) substr($normalized, 3);

        return User::where('id', $userId)->where('role', 'customer')->exists() ? $userId : null;
    }

    private function guardBookingAccess(Request $request, ?object $booking): void
    {
        abort_if(! $booking, 404, 'Booking tidak ditemukan.');
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


