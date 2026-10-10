<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_daily', function (Blueprint $table) {
            $table->decimal('pending_ot_hours', 6, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_daily', function (Blueprint $table) {
            $table->dropColumn('pending_ot_hours');
        });
    }
};
