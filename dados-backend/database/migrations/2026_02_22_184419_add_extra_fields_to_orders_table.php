<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('address')->nullable()->after('notes');
            $table->string('payment_method')->nullable()->after('address');
            $table->string('requested_by_name')->nullable()->after('payment_method');
            $table->string('requested_by_id')->nullable()->after('requested_by_name');
            $table->longText('signature')->nullable()->after('requested_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['address', 'payment_method', 'requested_by_name', 'requested_by_id', 'signature']);
        });
    }
};
