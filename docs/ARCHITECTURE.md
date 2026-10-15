# Architecture

Task Organizer merges two earlier systems into one Laravel application:

- **TaskFlow:** Laravel 12 + Filament.
- **to-do-system:** a Next.js rewrite of an earlier Laravel app.

Every task change goes through one service class, which keeps these consistent:

- status and progress
- history and notifications
- roll-up and project health

## Where each feature came from

| Feature | From | Notes |
|---|---|---|
| Projects → tasks, standalone tasks | both | |
| Subtasks with roll-up and numbering (1, 1.2, 1.2.3) | TaskFlow (depth), to-do-system (rules) | Up to 3 levels (`config/organizer.php`) |
| Six statuses, progress ⇄ status sync | to-do-system | |
| Auto-delay, deadline reminders | to-do-system | Scheduler, plus a throttled in-request fallback |
| Project health (9 rules, configurable thresholds) | to-do-system | `ProjectHealthService` |
| Post-launch work on completed projects | to-do-system | `tasks.category` |
| Project suggestions | to-do-system | |
| Append-only history | to-do-system | DB triggers + model guard |
| Remarks with supervisor review | TaskFlow | `remarks.reviewed_by/at` |
| Proof of work | TaskFlow | Files attached to a remark |
| Comments, @mentions, attachments | to-do-system | |
| Notification centre + bell | to-do-system | Plus email opt-in (new) |
| Daily briefing | to-do-system | Plain text, no AI |
| Focus of the Day: pins | to-do-system | |
| Focus of the Day: "not today" | TaskFlow | `focus_dismissals` |
| "Currently working on" | TaskFlow | One session per person, expires after 8 hours |
| Intern role | TaskFlow | |
| Department managers | TaskFlow | Department-wide management |
| Admin-managed departments | TaskFlow | Seeded with to-do-system's 8 |
| Department walls | to-do-system | |
| Executive dashboard, meeting mode | to-do-system | |
| Calendar, search | to-do-system | |
| Login throttle + lockout, session revocation | to-do-system | |
| Activity log | both | spatie/laravel-activitylog |
| Kanban board, CSV export, dark mode, email notifications | new | |

## Business rules

All of these are enforced in `app/Services/`.

### Hierarchy

- A task belongs to a project or stands alone.
- Subtasks nest up to three levels and always live in their parent's project.
- A parent's progress is the average of its non-cancelled children, and its status follows them (0% Pending, 1–99% In Progress, 100% Completed, Delayed when overdue). The parent can still take remarks.
- Changes roll up level by level to the project.

### Status and progress

- 0% is Pending, 1–99% In Progress, 100% Completed.
- When a request names a status, that status wins and progress is adjusted to match.

### Auto-delay

- Pending and In Progress tasks past their due date become Delayed. The previous status is saved, and a system history entry and a notification are written.
- Choosing Pending or In Progress on an overdue task keeps it Delayed.
- Moving the due date forward clears an automatic delay.

### Assignment

- A task has one primary assignee, who defaults to the creator. Collaborators are extra.
- Administrators and executives are never assignees, collaborators or project members.
- Interns can assign only themselves.

### Departments

A task's department follows its assignee, or its creator if unassigned. That drives two rules:

- **Department walls:** members see only their own department's tasks inside their projects.
- **Department managers** see and manage every task in their department.

### History

- Every change to status, progress, remarks, assignment or details writes a `task_updates` row.
- Triggers reject `UPDATE` and `DELETE` on that table, and the model refuses them too.

### Project health

Health is cached on the project and evaluated in this order:

1. Project status Completed → **Completed**
2. Project status On Hold or Cancelled → **On Hold**
3. Every non-cancelled task completed → **Completed**
4. Project past its due date → **Delayed**
5. Delayed share of open tasks ≥ the delayed threshold → **Delayed**
6. Delayed share ≥ the at-risk threshold → **At Risk**
7. Behind the time-based expected progress → **At Risk**
8. Due soon with low progress → **At Risk**
9. Otherwise → **On Track**

### Deletion

- Projects and tasks are soft-deleted, and their history is kept.
- Deleting a task removes everything nested under it.

### Sign-in

- Sign-in is throttled to 20 attempts per minute per IP.
- Accounts lock after N failures for M minutes (both set in Settings).
- "Locked" and "deactivated" are only revealed to someone who entered the right password.
- Deactivating a user or resetting their password deletes their sessions and rotates their "remember me" token.

## Code map

| Concern | Location |
|---|---|
| Enums (status, priority, roles, notification types…) | `app/Enums/` |
| Models and visibility scopes (`visibleTo`, `involving`) | `app/Models/` |
| Authorization | `app/Policies/` (+ `Gate::before` for admins in `AppServiceProvider`) |
| Task rules, roll-up, delays | `app/Services/TaskService.php` |
| Projects, team, suggestions | `app/Services/ProjectService.php` |
| Health | `app/Services/ProjectHealthService.php` |
| Comments, reviews, focus, working sessions | `app/Services/CollaborationService.php` |
| Deadlines and the briefing | `app/Services/DeadlineService.php`, `DigestService.php` |
| Who gets notified | `app/Services/Notifier.php`, `app/Notifications/AppNotification.php` |
| Files (type sniffing, private disk) | `app/Services/AttachmentService.php`, `AttachmentController` |
| Settings (typed, cached) | `app/Support/Settings.php` |
| Audit trail | `app/Support/Audit.php` |
| Numbering and tree order | `app/Support/TaskTree.php` |
| Screens | `app/Livewire/**` + `resources/views/livewire/**` |
| Shared UI | `resources/views/components/` (badge, avatar, task-row, modal…) |
| Scheduler and commands | `routes/console.php` |
| Login | `app/Http/Controllers/Auth/LoginController.php` |

## Data model

Users and organisation:

- `departments`
- `users` (role, department, lockout fields, email preferences)

Projects:

- `projects` (soft deletes; progress and health cached)
- `project_members` (role: manager or member)
- `project_suggestions`

Tasks:

- `tasks` (project, parent, depth, category, status, assignee, department, dates; soft deletes)
- `task_collaborators`
- `task_updates` (append-only)

Collaboration:

- `remarks` (reviewed_by / reviewed_at)
- `task_comments`
- `attachments` (polymorphic: task, comment, remark, suggestion)
- `task_focus`
- `focus_dismissals`
- `active_task_sessions`

System:

- `notifications` (Laravel; `type` = notification kind)
- `activity_log` (spatie)
- `settings`
- the standard Laravel tables: sessions, cache, jobs

## Tests

`php artisan test` runs the feature suites, each against a fresh in-memory SQLite database:

| Suite | Covers |
|---|---|
| `TaskRulesTest` | Status rules, roll-up, delays, history, deletion |
| `AccessTest` | Every role, plus department walls |
| `AuthTest` | Sign-in, lockout, throttling, session revocation |
| `HealthTest` | All health rules |
| `AutomationTest` | Deadline checks, the briefing, mentions, email opt-in, working sessions, focus |
| `ScreensTest` | Livewire interactions |
| `PagesTest` | Every page, as every role |
