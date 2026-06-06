<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\TechnicianProfile;
use App\Models\Booking;

class TechnicianApiTest extends TestCase
{
    use RefreshDatabase;

    protected $technicianUser;
    protected $technicianProfile;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->technicianUser = User::factory()->create(['role' => 'technician']);
        $this->technicianProfile = TechnicianProfile::create([
            'user_id' => $this->technicianUser->id,
            'verification_status' => 'approved',
            'rating' => 5.0,
            'completed_jobs' => 0
        ]);
        $this->token = $this->technicianUser->createToken('test')->plainTextToken;
    }

    public function test_technician_can_view_dashboard()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/technician/dashboard');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'daily_earnings',
                         'jobs_completed',
                         'rating',
                         'new_requests',
                         'recent_jobs'
                     ]
                 ]);
    }

    public function test_technician_can_view_orders()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Booking::create([
            'customer_id' => $customer->id,
            'technician_id' => $this->technicianProfile->id,
            'status' => 'pending',
            'scheduled_at' => now(),
            'total_price' => 150000
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/technician/orders');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }

    public function test_technician_can_accept_order()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::create([
            'customer_id' => $customer->id,
            'technician_id' => $this->technicianProfile->id,
            'status' => 'pending',
            'scheduled_at' => now(),
            'total_price' => 150000
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/technician/orders/{$booking->id}/accept");

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed'
        ]);
    }
}
