<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'sector')) {
                $table->string('sector')->nullable()->after('address');
            }
            if (!Schema::hasColumn('branches', 'parroquia')) {
                $table->string('parroquia')->nullable()->after('sector');
            }
            if (!Schema::hasColumn('branches', 'google_address')) {
                $table->text('google_address')->nullable()->after('parroquia');
            }
            if (!Schema::hasColumn('branches', 'geocoded_at')) {
                $table->timestamp('geocoded_at')->nullable()->after('google_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['sector', 'parroquia', 'google_address', 'geocoded_at']);
        });
    }
};
