<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_events');
    }
};
