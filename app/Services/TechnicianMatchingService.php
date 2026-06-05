<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TechnicianMatchingService
{
    public function recommendedForCategory(int $categoryId, int $limit = 10)
    {
        return DB::table('technician_profiles')
            ->join('users', 'users.id', '=', 'technician_profiles.user_id')
            ->join('technician_services', 'technician_services.technician_profile_id', '=', 'technician_profiles.id')
            ->where('technician_services.service_category_id', $categoryId)
            ->where('technician_profiles.verification_status', 'approved')
            ->select('users.id', 'users.name', 'users.avatar', 'technician_profiles.*', 'technician_services.min_price', 'technician_services.max_price', 'technician_services.diagnosis_fee')
            ->orderByDesc('technician_profiles.is_online')
            ->orderByDesc('technician_profiles.rating_avg')
            ->limit($limit)
            ->get();
    }
}
