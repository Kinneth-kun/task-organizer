<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * People search for @mention autocomplete.
 */
class LookupController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $users = User::query()->active()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('username', 'like', "{$term}%")))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'username', 'avatar_color', 'job_title']);

        return response()->json($users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'initials' => $u->initials(),
            'color' => $u->avatar_color,
            'job_title' => $u->job_title,
        ]));
    }
}
