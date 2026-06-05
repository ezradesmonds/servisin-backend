<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ComplaintService
{
    public function resolve(int $complaintId, string $resolutionType, string $decision): void
    {
        DB::table('complaints')->where('id', $complaintId)->update([
            'status' => 'resolved',
            'resolution_type' => $resolutionType,
            'admin_decision' => $decision,
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
