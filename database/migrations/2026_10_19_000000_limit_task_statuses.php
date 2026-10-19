<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks are now only Pending, In Progress or Completed. Tasks that were Delayed, On Hold or
 * Cancelled take the status their progress gives them (a Delayed task goes back to the status
 * it had before the delay). Task history is append-only and keeps its old statuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tasks')
            ->where('status', 'delayed')
            ->whereIn('status_before_delay', ['pending', 'in_progress'])
            ->update(['status' => DB::raw('status_before_delay')]);

        DB::table('tasks')->whereIn('status', ['delayed', 'on_hold', 'cancelled'])->where('progress', '<=', 0)->update(['status' => 'pending']);
        DB::table('tasks')->whereIn('status', ['delayed', 'on_hold', 'cancelled'])->where('progress', '>=', 100)
            ->update(['status' => 'completed', 'completed_at' => DB::raw('COALESCE(completed_at, updated_at)')]);
        DB::table('tasks')->whereIn('status', ['delayed', 'on_hold', 'cancelled'])->update(['status' => 'in_progress']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['status_before_delay', 'delayed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status_before_delay', 20)->nullable()->after('status');
            $table->timestamp('delayed_at')->nullable()->after('completed_at');
        });
    }
};
