<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PayoutService
{
    public function process(int $payoutId): void
    {
        DB::table('payouts')->where('id', $payoutId)->update([
            'status' => 'processed',
            'processed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
