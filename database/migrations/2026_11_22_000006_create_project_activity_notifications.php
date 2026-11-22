<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Keep a historical ID after a task is deleted; never cascade its history.
            $table->unsignedBigInteger('task_id')->nullable();
            $table->string('action', 40);
            $table->string('title', 255);
            $table->timestamps();

            $table->index(['project_id', 'id']);
        });

        Schema::create('project_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_activity_id')->constrained('project_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['project_activity_id', 'user_id']);
            $table->index(['user_id', 'read_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_notifications');
        Schema::dropIfExists('project_activities');
    }
};
