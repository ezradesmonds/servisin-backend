<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnerships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partnership_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('default_address_id')->nullable();
            $table->unsignedInteger('total_bookings')->default(0);
            $table->date('member_since')->nullable();
            $table->timestamps();
        });

        Schema::create('technician_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->unsignedInteger('experience_years')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->decimal('on_time_percentage', 5, 2)->default(0);
            $table->unsignedInteger('service_radius_km')->default(10);
            $table->string('verification_status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_online')->default(false)->index();
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->decimal('wallet_balance', 14, 2)->default(0);
            $table->decimal('pending_payout_balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('address_line');
            $table->string('city');
            $table->string('district');
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('service_problem_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_diagnosis_fee', 14, 2)->default(0);
            $table->decimal('min_estimated_price', 14, 2)->default(0);
            $table->decimal('max_estimated_price', 14, 2)->default(0);
            $table->unsignedInteger('warranty_days')->default(30);
            $table->timestamps();
        });

        Schema::create('technician_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('diagnosis_fee', 14, 2)->default(0);
            $table->decimal('min_price', 14, 2)->default(0);
            $table->decimal('max_price', 14, 2)->default(0);
            $table->decimal('emergency_surcharge', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('technician_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('technician_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_problem_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('status')->default('pending')->index();
            $table->boolean('is_emergency')->default(false);
            $table->text('notes')->nullable();
            $table->decimal('estimated_min_price', 14, 2)->default(0);
            $table->decimal('estimated_max_price', 14, 2)->default(0);
            $table->decimal('final_price', 14, 2)->nullable();
            $table->decimal('diagnosis_fee', 14, 2)->default(0);
            $table->decimal('emergency_surcharge', 14, 2)->default(0);
            $table->decimal('platform_fee', 14, 2)->default(0);
            $table->string('payment_status')->default('unpaid')->index();
            $table->timestamps();
        });

        Schema::create('booking_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->timestamps();
        });

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('changed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method');
            $table->string('status')->default('pending');
            $table->string('gateway_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('platform_fee', 14, 2);
            $table->decimal('tax', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('admin_flag_status')->nullable();
            $table->text('technician_reply')->nullable();
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->text('admin_decision')->nullable();
            $table->string('resolution_type')->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->string('status')->default('submitted');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('requested');
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('target_role')->nullable();
            $table->string('title');
            $table->text('body');
            $table->string('type');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('target_audience');
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content');
            $table->string('status')->default('draft');
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('description');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'activity_logs', 'admin_settings', 'cms_pages', 'broadcasts', 'notifications',
            'wallet_transactions', 'payouts', 'warranty_claims', 'complaints', 'reviews',
            'invoices', 'payments', 'chat_messages', 'chat_rooms', 'booking_status_histories',
            'booking_photos', 'bookings', 'technician_availabilities', 'technician_documents',
            'technician_services', 'service_problem_types', 'service_categories', 'addresses',
            'technician_profiles', 'customer_profiles', 'partnerships',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
