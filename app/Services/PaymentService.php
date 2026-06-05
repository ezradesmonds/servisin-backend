<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function payMock(int $bookingId, int $customerId, string $method): array
    {
        return DB::transaction(function () use ($bookingId, $customerId, $method) {
            $booking = DB::table('bookings')->find($bookingId);
            $amount = (float) ($booking->final_price ?? $booking->estimated_max_price);
            $platformFee = round($amount * 0.15);
            $tax = round($amount * 0.025);

            $paymentId = DB::table('payments')->insertGetId([
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
                'amount' => $amount + $tax,
                'method' => $method,
                'status' => 'paid',
                'gateway_reference' => 'MOCK-'.Str::upper(Str::random(8)),
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $invoiceId = DB::table('invoices')->insertGetId([
                'booking_id' => $bookingId,
                'invoice_number' => 'INV-'.now()->format('Ymd').'-'.$bookingId,
                'subtotal' => $amount,
                'platform_fee' => $platformFee,
                'tax' => $tax,
                'total' => $amount + $tax,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bookings')->where('id', $bookingId)->update([
                'payment_status' => 'paid',
                'status' => 'paid',
                'platform_fee' => $platformFee,
                'updated_at' => now(),
            ]);

            if ($booking->technician_id) {
                DB::table('wallet_transactions')->insert([
                    'user_id' => $booking->technician_id,
                    'booking_id' => $bookingId,
                    'type' => 'commission',
                    'amount' => $amount - $platformFee,
                    'description' => 'Pendapatan teknisi dari booking '.$booking->booking_code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return ['payment_id' => $paymentId, 'invoice_id' => $invoiceId, 'status' => 'paid'];
        });
    }
}
