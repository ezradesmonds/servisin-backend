<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function send(?int $userId, string $title, string $body, string $type, ?string $targetRole = null): void
    {
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'target_role' => $targetRole,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
