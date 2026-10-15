<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tasks a person pinned (☆) — shown in their own Focus of the Day until unpinned.
        Schema::create('task_focus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'task_id']);
        });

        // "Not today": hides a task from one person's Focus of the Day for one date.
        Schema::create('focus_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
            $table->unique(['user_id', 'task_id', 'date']);
        });

        // "Currently working on": at most one live session per person.
        Schema::create('active_task_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('active_task_sessions');
        Schema::dropIfExists('focus_dismissals');
        Schema::dropIfExists('task_focus');
    }
};
