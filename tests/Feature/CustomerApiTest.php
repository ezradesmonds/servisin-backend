<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\ServiceCategory;
use App\Models\ServiceProblemType;
use App\Models\Address;
use App\Models\Booking;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->token = $this->customer->createToken('test')->plainTextToken;
    }

    public function test_customer_can_view_profile()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/customer/profile');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['profile']]);
    }

    public function test_customer_can_create_address()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/customer/addresses', [
            'label' => 'Home',
            'recipient_name' => 'John Doe',
            'phone_number' => '081234567890',
            'address_line' => '123 Test Street',
            'latitude' => '-6.200000',
            'longitude' => '106.816666',
            'is_primary' => true
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $this->customer->id,
            'label' => 'Home',
        ]);
    }

    public function test_customer_can_create_booking()
    {
        $category = ServiceCategory::create(['name' => 'AC', 'icon_url' => 'http://test.com/icon.png', 'base_price' => 50000]);
        $problem = ServiceProblemType::create(['service_category_id' => $category->id, 'name' => 'Not cold', 'estimated_price' => 150000]);
        $address = Address::create([
            'user_id' => $this->customer->id,
            'label' => 'Home',
            'recipient_name' => 'John',
            'phone_number' => '123',
            'address_line' => 'test',
            'latitude' => '-6.2',
            'longitude' => '106.8',
            'is_primary' => true
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/bookings', [
            'service_category_id' => $category->id,
            'service_problem_type_id' => $problem->id,
            'address_id' => $address->id,
            'scheduled_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'notes' => 'Please fix quickly',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'customer_id' => $this->customer->id,
            'service_category_id' => $category->id,
            'status' => 'pending'
        ]);
    }

    public function test_customer_can_view_bookings()
    {
        Booking::create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'scheduled_at' => now(),
            'total_price' => 100000
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/customer/bookings');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['bookings']]);
    }
}
