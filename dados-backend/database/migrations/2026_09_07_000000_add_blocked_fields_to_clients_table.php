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
            $table->boolean('is_blocked')->default(false)->after('is_active');
            $table->timestamp('unblocked_until')->nullable()->after('is_blocked');
            $table->string('block_reason')->nullable()->after('unblocked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['is_blocked', 'unblocked_until', 'block_reason']);
        });
    }
};
