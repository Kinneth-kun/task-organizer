<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            // Null = a standalone task.
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            // Null = a top-level task. Subtasks nest up to config('organizer.max_task_depth').
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            // Null = a build task; otherwise post-launch work on a completed project.
            $table->string('category', 20)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('status_before_delay', 20)->nullable();
            $table->string('priority', 20)->default('medium')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            // The owning department: the assignee's, else the creator's. Kept in sync by TaskService.
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delayed_at')->nullable();
            // The due date a "deadline approaching" reminder was last sent for.
            $table->date('deadline_reminded_for')->nullable();
            // Denormalised "latest update" for fast list rendering.
            $table->text('latest_remark')->nullable();
            $table->timestamp('latest_update_at')->nullable();
            $table->foreignId('latest_update_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'parent_id']);
            $table->index(['project_id', 'category']);
            $table->index(['assignee_id', 'status']);
            $table->index('created_by');
        });

        Schema::create('task_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });

        // Append-only history. Never updated or deleted by the application (enforced below).
        Schema::create('task_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(); // null = system
            $table->string('type', 30)->index();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->unsignedTinyInteger('old_progress')->nullable();
            $table->unsignedTinyInteger('new_progress')->nullable();
            $table->text('remark')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });

        // Progress notes that supervisors review; proof-of-work files attach to them.
        Schema::create('remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            // task | comment | remark (proof of work) | suggestion
            $table->string('attachable_type', 40);
            $table->unsignedBigInteger('attachable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path', 500);
            $table->string('disk', 30)->default('local');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['attachable_type', 'attachable_id']);
        });

        $this->protectHistory();
    }

    /**
     * Reject UPDATE and DELETE on task_updates at the database level.
     */
    private function protectHistory(): void
    {
        $message = 'task_updates is append-only';

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER task_updates_no_update BEFORE UPDATE ON task_updates BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
            DB::unprepared("CREATE TRIGGER task_updates_no_delete BEFORE DELETE ON task_updates BEGIN SELECT RAISE(ABORT, '{$message}'); END;");

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER task_updates_no_update BEFORE UPDATE ON task_updates FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
            DB::unprepared("CREATE TRIGGER task_updates_no_delete BEFORE DELETE ON task_updates FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['sqlite', 'mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS task_updates_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS task_updates_no_delete');
        }

        Schema::dropIfExists('attachments');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('remarks');
        Schema::dropIfExists('task_updates');
        Schema::dropIfExists('task_collaborators');
        Schema::dropIfExists('tasks');
    }
};
