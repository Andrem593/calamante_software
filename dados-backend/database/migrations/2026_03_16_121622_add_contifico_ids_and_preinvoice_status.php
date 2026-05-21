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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('contifico_id')->nullable()->after('id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('contifico_id')->nullable()->after('id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('contifico_id')->nullable()->after('id');
            $table->boolean('is_preinvoiced')->default(false)->after('is_invoiced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('contifico_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('contifico_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['contifico_id', 'is_preinvoiced']);
        });
    }
};
