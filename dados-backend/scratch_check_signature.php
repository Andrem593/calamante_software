<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$o = App\Models\Order::whereNotNull('signature')->first();
if ($o) {
    echo "ID: " . $o->id . "\n";
    echo "Signature sample: " . substr($o->signature, 0, 100) . "\n";
} else {
    echo "No orders with signature found.\n";
}
