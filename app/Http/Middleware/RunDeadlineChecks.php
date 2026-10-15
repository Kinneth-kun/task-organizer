<?php

namespace App\Http\Middleware;

use App\Services\DeadlineService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Keeps delays and reminders current on hosts without the scheduler cron: after a signed-in
 * request, runs the deadline check in the background at most once every 5 minutes.
 */
class RunDeadlineChecks
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('organizer.inline_deadline_checks') && $request->user() && $request->isMethod('GET')) {
            defer(fn () => app(DeadlineService::class)->runThrottled(300));
        }

        return $response;
    }
}
