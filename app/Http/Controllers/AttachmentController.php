<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a private file after checking the viewer may open the record it belongs to.
 * Images and PDFs open inline; everything else (or ?download=1) downloads.
 */
class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $inline = ! $request->boolean('download') && ($attachment->isImage() || $attachment->isPdf());
        $headers = [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        return $inline
            ? $disk->response($attachment->path, $attachment->original_name, $headers)
            : $disk->download($attachment->path, $attachment->original_name, $headers);
    }
}
