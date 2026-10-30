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
            $table->index(['created_at', 'status']);
            $table->index(['delivery_date', 'status']);
            $table->index('status');
            $table->index(['user_id', 'created_at']);
            $table->index('client_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'product_id']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('identification');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_visible', 'status']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->index('client_id');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'status']);
            $table->dropIndex(['delivery_date', 'status']);
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['client_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'product_id']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['identification']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_visible', 'status']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['client_id']);
        });
    }
};
