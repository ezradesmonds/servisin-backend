<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $technician = DB::table('users')->where('email', 'technician@servisin.test')->first();
        $customer = DB::table('users')->where('email', 'customer@servisin.test')->first();
        $admin = DB::table('users')->where('role', 'admin')->first();

        if ($technician) {
            DB::table('payouts')->insert([
                ['technician_id' => $technician->id, 'amount' => 750000, 'status' => 'requested', 'requested_at' => now()->subDay(), 'processed_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);
            
            $otherTech = DB::table('users')->where('role', 'technician')->where('id', '!=', $technician->id)->first();
            if ($otherTech) {
                DB::table('payouts')->insert([
                    ['technician_id' => $otherTech->id, 'amount' => 1200000, 'status' => 'processed', 'requested_at' => now()->subDays(5), 'processed_at' => now()->subDays(3), 'created_at' => now(), 'updated_at' => now()],
                ]);
                if ($customer) {
                    DB::table('customer_favorites')->insert([
                        'customer_id' => $customer->id,
                        'technician_id' => $otherTech->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('wallet_transactions')->insert([
                ['user_id' => $technician->id, 'booking_id' => null, 'payout_id' => null, 'type' => 'credit', 'amount' => 150000, 'description' => 'Pendapatan servis AC SRV-001', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)],
                ['user_id' => $technician->id, 'booking_id' => null, 'payout_id' => null, 'type' => 'debit', 'amount' => 50000, 'description' => 'Penarikan dana ke bank', 'created_at' => now()->subDays(1), 'updated_at' => now()->subDays(1)],
            ]);
            
            DB::table('notifications')->insert([
                ['user_id' => $technician->id, 'target_role' => 'technician', 'title' => 'Job Baru', 'body' => 'Kamu mendapatkan request servis AC.', 'type' => 'booking', 'read_at' => now(), 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)],
            ]);
        }

        if ($customer) {
            $plan = DB::table('subscription_plans')->first();
            if ($plan) {
                DB::table('customer_subscriptions')->insert([
                    'customer_id' => $customer->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => 'active',
                    'started_at' => now()->subDays(5),
                    'expired_at' => now()->addDays(25),
                    'paid_amount' => 49000,
                    'payment_reference' => 'SUB-MOCK-DEMO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $otherCustomer = DB::table('users')->where('role', 'customer')->where('id', '!=', $customer->id)->first();
            if ($otherCustomer) {
                DB::table('referrals')->insert([
                    'referrer_id' => $customer->id,
                    'referred_user_id' => $otherCustomer->id,
                    'code' => 'SRV'.str_pad((string) $customer->id, 6, '0', STR_PAD_LEFT),
                    'status' => 'claimed',
                    'reward_amount' => 25000,
                    'claimed_at' => now()->subDays(7),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('notifications')->insert([
                ['user_id' => $customer->id, 'target_role' => 'customer', 'title' => 'Booking Dikonfirmasi', 'body' => 'Teknisi sedang menuju ke lokasi.', 'type' => 'booking', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if ($admin) {
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
}
