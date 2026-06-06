<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin Servisin',
            'email' => 'admin@servisin.test',
            'phone' => '081100000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $partnershipId = DB::table('partnerships')->first()->id ?? null;

        // --- Customers ---
        $customerNames = ['Customer Demo'];
        for ($i = 1; $i <= 20; $i++) {
            $customerNames[] = 'Pelanggan '.$i;
        }

        foreach ($customerNames as $i => $name) {
            $isDemo = $i === 0;
            $customer = User::create([
                'name' => $name,
                'email' => $isDemo ? 'customer@servisin.test' : 'pelanggan'.$i.'@servisin.test',
                'phone' => $isDemo ? '081100000002' : '0813'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'customer',
                'status' => 'active',
            ]);

            DB::table('customer_profiles')->insert([
                'user_id' => $customer->id,
                'partnership_id' => $isDemo ? $partnershipId : null,
                'total_bookings' => $isDemo ? 3 : 0,
                'member_since' => $isDemo ? today()->subMonths(4) : today()->subDays($i * 7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('addresses')->insert([
                'user_id' => $customer->id,
                'label' => 'Rumah',
                'address_line' => $isDemo ? 'Jl. Melati No. 12, Perumahan Grand Citra' : 'Jl. Servisin '.$i,
                'city' => 'Surabaya',
                'district' => $isDemo ? 'Rungkut' : 'Wonokromo',
                'postal_code' => $isDemo ? '60293' : '60200',
                'latitude' => $isDemo ? -7.321943 : null,
                'longitude' => $isDemo ? 112.778008 : null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- Technicians ---
        $technicianNames = ['Technician Demo', 'Budi Santoso', 'Andi Pratama', 'Rina Septiani', 'Siti Aminah', 'Mark Wilson', 'Alex Thompson', 'Sarah Jenkins', 'Marcus Lee', 'Dimas Saputra', 'Yusuf Hartono', 'Nadia Putri', 'Teguh Wijaya', 'Lina Marlina', 'Oscar Hidayat', 'Kevin Tan', 'Maya Lestari', 'Agus Salim', 'Dewi Anggraini', 'Fajar Nugroho', 'Hendra Gunawan'];
        $categoryIds = DB::table('service_categories')->pluck('id')->toArray();

        foreach ($technicianNames as $i => $name) {
            $isDemo = $i === 0;
            $techUser = User::create([
                'name' => $name,
                'email' => $isDemo ? 'technician@servisin.test' : 'teknisi'.$i.'@servisin.test',
                'phone' => $isDemo ? '081100000003' : '0822'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'technician',
                'status' => 'active',
            ]);

            $profileId = DB::table('technician_profiles')->insertGetId([
                'user_id' => $techUser->id,
                'bio' => 'Teknisi elektronik berpengalaman dengan layanan rapi dan garansi Servisin.',
                'experience_years' => random_int(2, 12),
                'rating_avg' => random_int(42, 50) / 10,
                'total_reviews' => random_int(10, 120),
                'completed_jobs' => random_int(25, 350),
                'on_time_percentage' => random_int(84, 99),
                'service_radius_km' => random_int(8, 25),
                'verification_status' => $i % 9 === 0 && !$isDemo ? 'pending' : 'approved',
                'is_online' => $i % 3 !== 0 || $isDemo,
                'current_lat' => -7.25 - ($i / 1000),
                'current_lng' => 112.75 + ($i / 1000),
                'wallet_balance' => random_int(5, 60) * 50000,
                'pending_payout_balance' => random_int(1, 20) * 25000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('technician_documents')->insert([
                ['technician_profile_id' => $profileId, 'type' => 'ktp', 'file_path' => 'storage/mock/ktp-'.$profileId.'.jpg', 'status' => 'approved', 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
                ['technician_profile_id' => $profileId, 'type' => 'certificate', 'file_path' => 'storage/mock/cert-'.$profileId.'.jpg', 'status' => 'approved', 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
                ['technician_profile_id' => $profileId, 'type' => 'portfolio', 'file_path' => 'storage/mock/portfolio-'.$profileId.'-1.jpg', 'status' => 'approved', 'notes' => 'Hasil pekerjaan servis elektronik rumah.', 'created_at' => now(), 'updated_at' => now()],
                ['technician_profile_id' => $profileId, 'type' => 'portfolio', 'file_path' => 'storage/mock/portfolio-'.$profileId.'-2.jpg', 'status' => 'approved', 'notes' => 'Before-after pekerjaan teknisi.', 'created_at' => now(), 'updated_at' => now()],
            ]);

            if (count($categoryIds) > 0) {
                foreach (array_slice($categoryIds, $i % count($categoryIds), 3) as $categoryId) {
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

            DB::table('technician_bank_accounts')->insert([
                'technician_id' => $techUser->id,
                'bank_name' => ['BCA', 'Mandiri', 'BNI', 'BRI'][$i % 4],
                'account_number' => '821000' . random_int(1000, 9999),
                'account_name' => strtoupper($techUser->name),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($day = 1; $day <= 7; $day++) {
                DB::table('technician_availabilities')->insert([
                    'technician_profile_id' => $profileId,
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'is_available' => $day < 7,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
