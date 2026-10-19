# Task Organizer

A role-based project and task management system that combines **TaskFlow** (Laravel + Filament) and
**to-do-system** (Next.js) into one Laravel application.

- **Projects:** health scoring, Kanban board, team roles and suggestions.
- **Tasks:** standalone tasks, subtasks up to three levels deep with automatic progress roll-up, append-only history and overdue tracking.
- **Daily report:** every update of the day grouped by project, marked reported once it has been covered in the daily meeting, with a backlog of earlier unreported updates, CSV export, print view and a "Latest updates" slide in meeting mode.
- **Collaboration:** remarks with proof of work, comments with @mentions, file attachments, Focus of the Day and "currently working on".
- **Activity Logs:** a table of your notifications with optional email, and a weekday 8:00 am briefing.
- **Oversight:** an executive dashboard, meeting mode, user and department management, settings, an audit trail and CSV exports.

**Stack:** PHP 8.2+ · Laravel 12 · Livewire 4 (Alpine.js) · Tailwind CSS 4 · MySQL 8 (SQLite for tests) ·
spatie/laravel-activitylog.

## Run it locally

Requirements: PHP 8.2+ with `pdo_mysql`, `fileinfo` and `mbstring` · Composer 2 · Node 20+ · MySQL 8 (or MariaDB 10.6+).

1. Install the PHP and JavaScript dependencies:

   ```bash
   composer install
   ```

   ```bash
   npm install
   ```

2. Copy `.env.example` to `.env`, then set the `DB_*` values and `ADMIN_USERNAME`, `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
3. Create an empty database called `task_organizer`, then run:

   ```bash
   php artisan key:generate
   ```

   ```bash
   php artisan migrate --seed
   ```

4. Build the assets and start the app:

   ```bash
   npm run build
   ```

   ```bash
   php artisan serve
   ```

Open <http://127.0.0.1:8000> and sign in with the administrator from `.env`.

For development with hot reload, run this instead of `serve` + `build`:

```bash
composer dev
```

It starts the server, a queue worker, the log viewer and Vite together.

## Roles

| Role | Can |
|---|---|
| **Administrator** | Everything: users, departments, settings, meeting mode, every project and task |
| **Executive** | See every project and task, the executive dashboard and the audit trail. Changes only the tasks they are on |
| **Department supervisor** | Manage every project and task in their own department, and mark its updates reported in the daily report |
| **Member** | Work on projects they own or belong to, and tasks they're assigned to or collaborate on |
| **Intern** | Work only on tasks assigned to them. Can't create projects or assign other people |

## Scripts

| Command | What it does |
|---|---|
| `php artisan test` | The test suite (SQLite in memory) |
| `DB_CONNECTION=mysql DB_DATABASE=task_organizer_test php artisan test` | The same suite against MySQL. Create the empty `task_organizer_test` database first; it is wiped on every run |
| `php artisan tasks:check-deadlines` | Sends deadline reminders, refreshes project health |
| `php artisan digest:send --dry-run` | Previews the 8:00 am briefing. Also takes `--user=` and `--force` |
| `php artisan active-tasks:expire` | Ends "currently working" sessions older than 8 hours |
| `php artisan schedule:run` | Runs whatever is due. Production needs this every minute (see deployment) |

## Documentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): business rules, data model, code map, and where each feature came from
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md): deploying to a VPS, shared hosting, Laravel Cloud or AWS
