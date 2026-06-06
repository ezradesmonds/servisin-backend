<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admin_settings')->insert([
            ['key' => 'platform_fee_percentage', 'value' => '15', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'tax_provision_percentage', 'value' => '2.5', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mock_maps_enabled', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mock_payment_gateway', 'value' => 'midtrans_xendit_ready', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('subscription_plans')->insert([
            [
                'name' => 'Servisin+ Bulanan',
                'slug' => 'servisin-plus-monthly',
                'description' => 'Prioritas booking, diskon diagnosis, dan dukungan komplain lebih cepat.',
                'price' => 49000,
                'duration_days' => 30,
                'benefits' => json_encode(['Diskon diagnosis 15%', 'Prioritas teknisi online', 'Voucher emergency 1x']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Servisin+ Tahunan',
                'slug' => 'servisin-plus-yearly',
                'description' => 'Paket hemat tahunan untuk rumah dengan banyak perangkat elektronik.',
                'price' => 399000,
                'duration_days' => 365,
                'benefits' => json_encode(['Diskon diagnosis 20%', 'Prioritas teknisi online', 'Voucher emergency 6x']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('promo_codes')->insert([
            ['code' => 'HEMATAC', 'name' => 'Promo Servis AC', 'discount_type' => 'percentage', 'discount_value' => 10, 'min_transaction_amount' => 150000, 'quota' => 500, 'used_count' => 12, 'status' => 'active', 'expired_at' => now()->addMonths(2), 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BARUSERVISIN', 'name' => 'Pengguna Baru', 'discount_type' => 'fixed', 'discount_value' => 25000, 'min_transaction_amount' => 100000, 'quota' => 1000, 'used_count' => 43, 'status' => 'active', 'expired_at' => now()->addMonths(3), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('homepage_banners')->insert([
            ['title' => 'Servis AC Lebih Hemat', 'body' => 'Gunakan kode HEMATAC untuk diskon 10%.', 'image_url' => '/storage/mock/banners/servis-ac.jpg', 'cta_label' => 'Booking Sekarang', 'cta_url' => 'servisin://discover?search=AC', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Servisin+ untuk Rumahmu', 'body' => 'Langganan prioritas teknisi dan voucher emergency.', 'image_url' => '/storage/mock/banners/servisin-plus.jpg', 'cta_label' => 'Lihat Paket', 'cta_url' => 'servisin://subscriptions', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('cms_pages')->insert([
            ['slug' => 'faq', 'title' => 'FAQ Servisin', 'content' => 'Pertanyaan umum tentang booking, pembayaran, garansi, dan komplain.', 'status' => 'published', 'last_published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'terms', 'title' => 'Syarat dan Ketentuan', 'content' => 'Ketentuan penggunaan marketplace Servisin.', 'status' => 'published', 'last_published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'terms-and-conditions', 'title' => 'Terms and Conditions', 'content' => 'Ketentuan layanan, pembayaran, komplain, refund, dan garansi Servisin.', 'status' => 'published', 'last_published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'help-center', 'title' => 'Help Center', 'content' => 'Pusat bantuan untuk pelanggan dan teknisi Servisin.', 'status' => 'published', 'last_published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'privacy', 'title' => 'Kebijakan Privasi', 'content' => 'Kebijakan data pelanggan, teknisi, dan transaksi.', 'status' => 'published', 'last_published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('partnerships')->insert([
            'name' => 'Perumahan Grand Citra',
            'code' => 'GRANDCITRA',
            'discount_percentage' => 10,
            'status' => 'active',
            'expired_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
