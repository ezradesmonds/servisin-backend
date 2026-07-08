# Servisin Backend

Servisin Backend is a Laravel API for an on-demand home service marketplace. It supports customer, technician, and admin workflows across services, bookings, matching, payments, complaints, subscriptions, referrals, and notifications.

## Overview

The backend provides the business logic behind the Servisin mobile app. It manages user roles, service categories, technician matching, booking lifecycle, payment simulation, technician wallets, complaints, warranty claims, referrals, promos, and admin operations.

## Key Features

- Customer, technician, and admin role support.
- Authentication and API access through Laravel Sanctum.
- Service categories and problem catalog.
- Technician matching based on online status, rating, skills, and service area.
- Booking creation, assignment, status history, and lifecycle handling.
- Mock payment and wallet transaction flows.
- Technician dashboard, orders, calendar, wallet, bank accounts, skills, and service areas.
- Customer subscriptions, referrals, favorites, promos, and saved addresses.
- Complaints and warranty claim handling.
- Device token and notification support.
- Admin statistics, technician approval, assignment, broadcasts, and content pages.
- Feature tests for auth, customer APIs, and technician APIs.

## Tech Stack

- PHP 8.3
- Laravel 13
- Laravel Sanctum
- MySQL/MariaDB or another Laravel-supported SQL database
- Vite and Tailwind CSS for local assets
- PHPUnit 12

## Project Structure

```text
app/Http/Controllers/Api/ServisinController.php  Main API controller
app/Services/                                    Booking, matching, pricing, payment, payout, complaint, notification services
app/Models/                                      Domain models
database/migrations/                            Database schema
database/seeders/                               Seed data
routes/api.php                                  API routes
tests/Feature/                                  Feature tests
```

## Getting Started

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Configure the database, then run migrations and seeders:

```bash
php artisan migrate --seed
```

Start the API:

```bash
php artisan serve
```

Run tests:

```bash
php artisan test
```

## API Areas

- Auth and profile
- Customer services, bookings, addresses, subscriptions, referrals, promos, complaints, and reviews
- Technician onboarding, orders, calendar, wallet, bank accounts, service areas, and skills
- Admin statistics, technician approval, booking assignment, broadcasts, and content pages

## Security Notes

- Enforce authorization for every role-specific endpoint.
- Booking and payment state transitions should be server-owned and audit-friendly.
- Technician matching should guard against assigning unavailable or unapproved technicians.
- Mock payment code should be replaced with a real provider integration before production.

## Suggested Tests

- Booking lifecycle tests from customer creation through technician completion.
- Authorization tests across customer, technician, and admin roles.
- Payment failure and duplicate callback tests when a real payment gateway is added.
- Matching edge-case tests for no technician, offline technician, and overloaded technician cases.

## Status

Backend API for a service marketplace prototype with multiple domain services already separated.
