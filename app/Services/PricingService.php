<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PricingService
{
    public function estimate(int $problemTypeId, bool $emergency = false, ?string $partnershipCode = null): array
    {
        $problem = DB::table('service_problem_types')->find($problemTypeId);
        $surcharge = $emergency ? 75000 : 0;
        $discount = 0;

        if ($partnershipCode) {
            $partner = DB::table('partnerships')
                ->where('code', strtoupper($partnershipCode))
                ->where('status', 'active')
                ->first();
            $discount = $partner ? (float) $partner->discount_percentage : 0;
        }

        $min = (float) $problem->base_diagnosis_fee + (float) $problem->min_estimated_price + $surcharge;
        $max = (float) $problem->base_diagnosis_fee + (float) $problem->max_estimated_price + $surcharge;

        return [
            'diagnosis_fee' => (float) $problem->base_diagnosis_fee,
            'estimated_min_price' => round($min * (1 - $discount / 100)),
            'estimated_max_price' => round($max * (1 - $discount / 100)),
            'emergency_surcharge' => $surcharge,
            'partnership_discount_percentage' => $discount,
            'currency' => 'IDR',
        ];
    }
}
