<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NotificationController;
use App\Livewire\Admin\ActivityLog;
use App\Livewire\Admin\Departments;
use App\Livewire\Admin\SettingsPage;
use App\Livewire\Admin\UserForm;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Calendar;
use App\Livewire\Dashboard;
use App\Livewire\Executive;
use App\Livewire\Meeting;
use App\Livewire\Notifications\NotificationCenter;
use App\Livewire\Profile;
use App\Livewire\Projects\ProjectForm;
use App\Livewire\Projects\ProjectIndex;
use App\Livewire\Projects\ProjectShow;
use App\Livewire\Search;
use App\Livewire\Tasks\TaskForm;
use App\Livewire\Tasks\TaskIndex;
use App\Livewire\Tasks\TaskShow;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::livewire('/', Dashboard::class)->name('dashboard');

    Route::livewire('/tasks', TaskIndex::class)->name('tasks.index');
    Route::livewire('/tasks/create', TaskForm::class)->name('tasks.create');
    Route::livewire('/tasks/{task}', TaskShow::class)->name('tasks.show');
    Route::livewire('/tasks/{task}/edit', TaskForm::class)->name('tasks.edit');

    Route::livewire('/projects', ProjectIndex::class)->name('projects.index');
    Route::livewire('/projects/create', ProjectForm::class)->name('projects.create');
    Route::livewire('/projects/{project}', ProjectShow::class)->name('projects.show');
    Route::livewire('/projects/{project}/edit', ProjectForm::class)->name('projects.edit');

    Route::livewire('/calendar', Calendar::class)->name('calendar');
    Route::livewire('/search', Search::class)->name('search');
    Route::livewire('/notifications', NotificationCenter::class)->name('notifications.index');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::livewire('/profile', Profile::class)->name('profile');

    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::get('/lookup/users', [LookupController::class, 'users'])->middleware('throttle:lookup')->name('lookup.users');

    Route::get('/export/tasks.csv', [ExportController::class, 'tasks'])->name('export.tasks');
    Route::get('/export/projects.csv', [ExportController::class, 'projects'])->name('export.projects');

    Route::middleware('can:full-access')->group(function () {
        Route::livewire('/executive', Executive::class)->name('executive');
        Route::livewire('/admin/activity', ActivityLog::class)->name('admin.activity');
    });

    Route::middleware('can:admin')->group(function () {
        Route::livewire('/meeting', Meeting::class)->name('meeting');
        Route::livewire('/admin/users', UserIndex::class)->name('admin.users.index');
        Route::livewire('/admin/users/create', UserForm::class)->name('admin.users.create');
        Route::livewire('/admin/users/{user}/edit', UserForm::class)->name('admin.users.edit');
        Route::livewire('/admin/departments', Departments::class)->name('admin.departments');
        Route::livewire('/admin/settings', SettingsPage::class)->name('admin.settings');
    });
});
