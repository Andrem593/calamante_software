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
            $table->boolean('is_visible')->default(true)->after('sku');
            $table->string('status')->default('A')->after('is_visible'); // A: Activo, I: Inactivo, etc.
            $table->decimal('tax_percentage', 5, 2)->default(15.00)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'status', 'tax_percentage']);
        });
    }
};
