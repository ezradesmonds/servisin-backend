<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admin_settings')->insert([
            ['key' => 'platform_fee_percentage', 'value' => '15', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'tax_provision_percentage', 'value' => '2.5', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mock_maps_enabled', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mock_payment_gateway', 'value' => 'midtrans_xendit_ready', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $admin = User::create([
            'name' => 'Admin Servisin',
            'email' => 'admin@servisin.test',
            'phone' => '081100000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $customer = User::create([
            'name' => 'Customer Demo',
            'email' => 'customer@servisin.test',
            'phone' => '081100000002',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $technician = User::create([
            'name' => 'Technician Demo',
            'email' => 'technician@servisin.test',
            'phone' => '081100000003',
            'password' => Hash::make('password'),
            'role' => 'technician',
            'status' => 'active',
        ]);

        $partnershipId = DB::table('partnerships')->insertGetId([
            'name' => 'Perumahan Grand Citra',
            'code' => 'GRANDCITRA',
            'discount_percentage' => 10,
            'status' => 'active',
            'expired_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customer_profiles')->insert([
            'user_id' => $customer->id,
            'partnership_id' => $partnershipId,
            'total_bookings' => 3,
            'member_since' => today()->subMonths(4),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $addressId = DB::table('addresses')->insertGetId([
            'user_id' => $customer->id,
            'label' => 'Rumah',
            'address_line' => 'Jl. Melati No. 12, Perumahan Grand Citra',
            'city' => 'Surabaya',
            'district' => 'Rungkut',
            'postal_code' => '60293',
            'latitude' => -7.321943,
            'longitude' => 112.778008,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categories = [
            ['Servis AC', 'servis-ac', 'wind', 'Cuci AC, freon, bocor, dan perawatan rutin.'],
            ['Kulkas', 'kulkas', 'snowflake', 'Perbaikan kulkas tidak dingin, kompresor, dan pintu.'],
            ['Mesin Cuci', 'mesin-cuci', 'washing-machine', 'Servis mesin cuci top/front loading.'],
            ['Elektronik Rumah', 'elektronik-rumah', 'tv', 'TV, speaker, rice cooker, dan perangkat rumah.'],
            ['Listrik', 'listrik', 'zap', 'Instalasi dan perbaikan listrik ringan.'],
            ['Plumbing', 'plumbing', 'droplets', 'Pipa bocor, kran, dan saluran air.'],
            ['Furniture Assembly', 'furniture-assembly', 'sofa', 'Rakit furniture dan pemasangan ringan.'],
            ['General Handyman', 'general-handyman', 'wrench', 'Perbaikan umum rumah.'],
        ];

        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[] = DB::table('service_categories')->insertGetId([
                'name' => $category[0],
                'slug' => $category[1],
                'icon' => $category[2],
                'description' => $category[3],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $problemIds = [];
        foreach ($categoryIds as $index => $categoryId) {
            for ($i = 1; $i <= 4; $i++) {
                $problemIds[] = DB::table('service_problem_types')->insertGetId([
                    'service_category_id' => $categoryId,
                    'name' => ['Tidak dingin', 'Bocor / rembes', 'Perawatan rutin', 'Kerusakan berat'][$i - 1] ?? 'Masalah umum',
                    'description' => 'Estimasi awal untuk kategori '.$categories[$index][0].'.',
                    'base_diagnosis_fee' => 50000 + ($i * 10000),
                    'min_estimated_price' => 125000 + ($i * 35000),
                    'max_estimated_price' => 350000 + ($i * 75000),
                    'warranty_days' => [30, 45, 60, 90][$i - 1],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $technicianNames = ['Budi Santoso', 'Andi Pratama', 'Rina Septiani', 'Siti Aminah', 'Mark Wilson', 'Alex Thompson', 'Sarah Jenkins', 'Marcus Lee', 'Dimas Saputra', 'Yusuf Hartono', 'Nadia Putri', 'Teguh Wijaya', 'Lina Marlina', 'Oscar Hidayat', 'Kevin Tan', 'Maya Lestari', 'Agus Salim', 'Dewi Anggraini', 'Fajar Nugroho', 'Hendra Gunawan'];
        $technicianUsers = [$technician];

        foreach ($technicianNames as $i => $name) {
            $technicianUsers[] = User::create([
                'name' => $name,
                'email' => 'teknisi'.($i + 1).'@servisin.test',
                'phone' => '0822'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'technician',
                'status' => 'active',
            ]);
        }

        foreach ($technicianUsers as $i => $techUser) {
            $profileId = DB::table('technician_profiles')->insertGetId([
                'user_id' => $techUser->id,
                'bio' => 'Teknisi elektronik berpengalaman dengan layanan rapi dan garansi Servisin.',
                'experience_years' => random_int(2, 12),
                'rating_avg' => random_int(42, 50) / 10,
                'total_reviews' => random_int(10, 120),
                'completed_jobs' => random_int(25, 350),
                'on_time_percentage' => random_int(84, 99),
                'service_radius_km' => random_int(8, 25),
                'verification_status' => $i % 9 === 0 ? 'pending' : 'approved',
                'is_online' => $i % 3 !== 0,
                'current_lat' => -7.25 - ($i / 1000),
                'current_lng' => 112.75 + ($i / 1000),
                'wallet_balance' => random_int(5, 60) * 50000,
                'pending_payout_balance' => random_int(1, 20) * 25000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('technician_documents')->insert([
                ['technician_profile_id' => $profileId, 'type' => 'ktp', 'file_path' => 'storage/mock/ktp-'.$profileId.'.jpg', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
                ['technician_profile_id' => $profileId, 'type' => 'certificate', 'file_path' => 'storage/mock/cert-'.$profileId.'.jpg', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ]);

            foreach (array_slice($categoryIds, $i % 4, 3) as $categoryId) {
                DB::table('technician_services')->insert([
                    'technician_profile_id' => $profileId,
                    'service_category_id' => $categoryId,
                    'diagnosis_fee' => 60000,
                    'min_price' => 150000,
                    'max_price' => 650000,
                    'emergency_surcharge' => 75000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $customerUsers = [$customer];
        for ($i = 1; $i <= 20; $i++) {
            $newCustomer = User::create([
                'name' => 'Pelanggan '.$i,
                'email' => 'pelanggan'.$i.'@servisin.test',
                'phone' => '0813'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'customer',
                'status' => 'active',
            ]);
            $customerUsers[] = $newCustomer;
            DB::table('customer_profiles')->insert(['user_id' => $newCustomer->id, 'member_since' => today()->subDays($i * 7), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('addresses')->insert(['user_id' => $newCustomer->id, 'label' => 'Rumah', 'address_line' => 'Jl. Servisin '.$i, 'city' => 'Surabaya', 'district' => 'Wonokromo', 'postal_code' => '60200', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        $statuses = ['pending', 'accepted', 'technician_on_the_way', 'arrived', 'in_progress', 'completed', 'paid', 'cancelled', 'complaint', 'refunded'];
        for ($i = 1; $i <= 50; $i++) {
            $cust = $customerUsers[array_rand($customerUsers)];
            $tech = $technicianUsers[array_rand($technicianUsers)];
            $categoryId = $categoryIds[array_rand($categoryIds)];
            $problemId = $problemIds[array_rand($problemIds)];
            $custAddress = DB::table('addresses')->where('user_id', $cust->id)->value('id') ?: $addressId;
            $status = $statuses[$i % count($statuses)];
            $price = random_int(200000, 900000);
            $bookingId = DB::table('bookings')->insertGetId([
                'booking_code' => 'SRV-20260603-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'customer_id' => $cust->id,
                'technician_id' => $tech->id,
                'service_category_id' => $categoryId,
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
        }

        DB::table('payouts')->insert([
            ['technician_id' => $technician->id, 'amount' => 750000, 'status' => 'requested', 'requested_at' => now()->subDay(), 'processed_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['technician_id' => $technicianUsers[2]->id, 'amount' => 1200000, 'status' => 'processed', 'requested_at' => now()->subDays(5), 'processed_at' => now()->subDays(3), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('cms_pages')->insert([
            ['slug' => 'faq', 'title' => 'FAQ Servisin', 'content' => 'Pertanyaan umum tentang booking, pembayaran, garansi, dan komplain.', 'status' => 'published', 'last_published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'terms', 'title' => 'Syarat dan Ketentuan', 'content' => 'Ketentuan penggunaan marketplace Servisin.', 'status' => 'published', 'last_published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'privacy', 'title' => 'Kebijakan Privasi', 'content' => 'Kebijakan data pelanggan, teknisi, dan transaksi.', 'status' => 'published', 'last_published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('broadcasts')->insert([
            'admin_id' => $admin->id,
            'title' => 'Promo Servis AC',
            'body' => 'Diskon 10% untuk warga Grand Citra minggu ini.',
            'target_audience' => 'customers',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
