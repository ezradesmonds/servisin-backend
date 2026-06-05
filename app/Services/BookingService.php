<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(private PricingService $pricing) {}

    public function create(array $data, int $customerId): object
    {
        return DB::transaction(function () use ($data, $customerId) {
            $estimate = $this->pricing->estimate((int) $data['service_problem_type_id'], (bool) ($data['is_emergency'] ?? false));
            $bookingId = DB::table('bookings')->insertGetId([
                'booking_code' => 'SRV-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'customer_id' => $customerId,
                'technician_id' => $data['technician_id'] ?? null,
                'service_category_id' => $data['service_category_id'],
                'service_problem_type_id' => $data['service_problem_type_id'],
                'address_id' => $data['address_id'],
                'scheduled_at' => $data['scheduled_at'],
                'status' => 'pending',
                'is_emergency' => (bool) ($data['is_emergency'] ?? false),
                'notes' => $data['notes'] ?? null,
                'estimated_min_price' => $estimate['estimated_min_price'],
                'estimated_max_price' => $estimate['estimated_max_price'],
                'diagnosis_fee' => $estimate['diagnosis_fee'],
                'emergency_surcharge' => $estimate['emergency_surcharge'],
                'payment_status' => 'unpaid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('booking_status_histories')->insert([
                'booking_id' => $bookingId,
                'status' => 'pending',
                'changed_by_user_id' => $customerId,
                'note' => 'Booking dibuat pelanggan.',
                'created_at' => now(),
            ]);

            if (! empty($data['technician_id'])) {
                DB::table('chat_rooms')->insert([
                    'booking_id' => $bookingId,
                    'customer_id' => $customerId,
                    'technician_id' => $data['technician_id'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return DB::table('bookings')->find($bookingId);
        });
    }

    public function transition(int $bookingId, int $actorId, string $status, ?float $finalPrice = null): void
    {
        $updates = ['status' => $status, 'updated_at' => now()];
        if ($finalPrice !== null) {
            $updates['final_price'] = $finalPrice;
        }

        DB::table('bookings')->where('id', $bookingId)->update($updates);
        DB::table('booking_status_histories')->insert([
            'booking_id' => $bookingId,
            'status' => $status,
            'changed_by_user_id' => $actorId,
            'note' => 'Status diperbarui menjadi '.$status,
            'created_at' => now(),
        ]);
    }
}
