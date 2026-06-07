<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$booking = DB::table('bookings')->latest('id')->first();
echo json_encode($booking, JSON_PRETTY_PRINT);
