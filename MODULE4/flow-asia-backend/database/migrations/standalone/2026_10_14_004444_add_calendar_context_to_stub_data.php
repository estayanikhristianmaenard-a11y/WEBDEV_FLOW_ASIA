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
        Schema::table('attendance_daily', function (Blueprint $table) {
            $table->json('calendar_details')->nullable();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->json('calendar_profile')->nullable();
        });
        Schema::create('calendar_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title');
            $table->text('body');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_reports');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('calendar_profile');
        });
        Schema::table('attendance_daily', function (Blueprint $table) {
            $table->dropColumn('calendar_details');
        });
    }
};
