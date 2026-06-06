<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
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

        foreach ($categories as $category) {
            $categoryId = DB::table('service_categories')->insertGetId([
                'name' => $category[0],
                'slug' => $category[1],
                'icon' => $category[2],
                'description' => $category[3],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($i = 1; $i <= 4; $i++) {
                DB::table('service_problem_types')->insert([
                    'service_category_id' => $categoryId,
                    'name' => ['Tidak dingin', 'Bocor / rembes', 'Perawatan rutin', 'Kerusakan berat'][$i - 1] ?? 'Masalah umum',
                    'description' => 'Estimasi awal untuk kategori '.$category[0].'.',
                    'base_diagnosis_fee' => 50000 + ($i * 10000),
                    'min_estimated_price' => 125000 + ($i * 35000),
                    'max_estimated_price' => 350000 + ($i * 75000),
                    'warranty_days' => [30, 45, 60, 90][$i - 1],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
