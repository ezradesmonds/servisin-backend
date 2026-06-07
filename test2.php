<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$booking = \App\Models\Booking::with(['serviceCategory', 'serviceProblemType', 'technician'])->find(54);
echo json_encode($booking, JSON_PRETTY_PRINT);
