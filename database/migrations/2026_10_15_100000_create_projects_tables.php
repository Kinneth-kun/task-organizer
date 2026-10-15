<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->string('priority', 20)->default('medium');
            $table->string('color', 20)->default('indigo');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            // Cached; recalculated by ProjectHealthService whenever tasks change.
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('health', 20)->default('on_track')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('project_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_suggestions');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
