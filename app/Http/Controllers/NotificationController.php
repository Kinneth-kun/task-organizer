<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Mark one notification read and follow its link. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Only site-relative links are followed.
        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : redirect()->route('notifications.index');
    }
}
