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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 12, 4)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price', 12, 4)->change();
            $table->decimal('subtotal', 12, 4)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total', 12, 4)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
            $table->decimal('subtotal', 10, 2)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total', 10, 2)->change();
        });
    }
};
