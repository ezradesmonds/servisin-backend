<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $customers = DB::table('users')->where('role', 'customer')->get();
        $technicians = DB::table('users')->where('role', 'technician')->get();
        $categories = DB::table('service_categories')->get();
        
        if ($customers->isEmpty() || $technicians->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $statuses = ['pending', 'accepted', 'technician_on_the_way', 'arrived', 'in_progress', 'completed', 'paid', 'cancelled', 'complaint', 'refunded'];

        for ($i = 1; $i <= 50; $i++) {
            $cust = $customers->random();
            $tech = $technicians->random();
            $category = $categories->random();
            $problemIds = DB::table('service_problem_types')->where('service_category_id', $category->id)->pluck('id');
            $problemId = $problemIds->isEmpty() ? null : $problemIds->random();
            
            $custAddress = DB::table('addresses')->where('user_id', $cust->id)->value('id');
            if (!$custAddress) continue;

            $status = $statuses[$i % count($statuses)];
            $price = random_int(200000, 900000);
            
            $bookingId = DB::table('bookings')->insertGetId([
                'booking_code' => 'SRV-20260603-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'customer_id' => $cust->id,
                'technician_id' => $tech->id,
                'service_category_id' => $category->id,
                'service_problem_type_id' => $problemId,
                'address_id' => $custAddress,
                'scheduled_at' => now()->addDays($i % 14),
                'status' => $status,
                'is_emergency' => $i % 5 === 0,
                'notes' => 'Perangkat bermasalah, mohon dibantu cek di lokasi.',
                'estimated_min_price' => max(100000, $price - 100000),
                'estimated_max_price' => $price + 150000,
                'final_price' => in_array($status, ['completed', 'paid', 'complaint', 'refunded'], true) ? $price : null,
                'diagnosis_fee' => 60000,
                'emergency_surcharge' => $i % 5 === 0 ? 75000 : 0,
                'platform_fee' => round($price * 0.15),
                'payment_status' => in_array($status, ['paid', 'refunded'], true) ? 'paid' : 'unpaid',
                'created_at' => now()->subDays($i),
                'updated_at' => now()->subDays($i % 5),
            ]);

            DB::table('booking_status_histories')->insert(['booking_id' => $bookingId, 'status' => $status, 'changed_by_user_id' => $tech->id, 'note' => 'Seeded status '.$status, 'created_at' => now()]);
            DB::table('chat_rooms')->insert(['booking_id' => $bookingId, 'customer_id' => $cust->id, 'technician_id' => $tech->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

            if ($i <= 18) {
                DB::table('reviews')->insert(['booking_id' => $bookingId, 'customer_id' => $cust->id, 'technician_id' => $tech->id, 'rating' => random_int(4, 5), 'comment' => 'Teknisi responsif, estimasi harga jelas, pekerjaan rapi.', 'created_at' => now(), 'updated_at' => now()]);
            }

            if ($i % 13 === 0) {
                DB::table('complaints')->insert(['booking_id' => $bookingId, 'customer_id' => $cust->id, 'technician_id' => $tech->id, 'reason' => 'Hasil belum maksimal', 'description' => 'Masih perlu pengecekan ulang setelah servis.', 'status' => 'open', 'resolution_type' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }

            if (in_array($status, ['paid', 'completed'], true)) {
                DB::table('invoices')->insert([
                    'booking_id' => $bookingId,
                    'invoice_number' => 'INV-20260603-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'subtotal' => $price,
                    'platform_fee' => round($price * 0.15),
                    'tax' => 0,
                    'total' => $price + round($price * 0.15),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('booking_photos')->insert([
                    'booking_id' => $bookingId,
                    'uploaded_by_user_id' => $tech->id,
                    'type' => 'completion',
                    'file_path' => 'storage/mock/booking-'.$bookingId.'-complete.jpg',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
