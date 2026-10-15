<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores files for tasks, comments, remarks (proof of work) and suggestions on the configured
 * private disk (local or S3). The real content type is checked, not just the extension.
 */
class AttachmentService
{
    /** Content types each allowed extension may really contain. */
    private const MIME_FAMILIES = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    /** Validation rules for one upload field (used by the Livewire forms). */
    public static function rules(): array
    {
        return ['file', 'max:'.config('organizer.attachments.max_kb'), 'mimes:'.implode(',', config('organizer.attachments.extensions'))];
    }

    public static function accept(): string
    {
        return implode(',', array_map(fn ($ext) => '.'.$ext, config('organizer.attachments.extensions')));
    }

    public function store(UploadedFile $file, Model $attachable, User $user, ?string $description = null): Attachment
    {
        $this->check($file);

        $extension = strtolower($file->getClientOriginalExtension());
        $disk = config('organizer.attachments.disk');
        $directory = 'attachments/'.now()->format('Y/m');
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, ['disk' => $disk]);

        $attachment = Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'user_id' => $user->id,
            'original_name' => $this->safeName($file->getClientOriginalName()),
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $this->sniff($file),
            'size' => $file->getSize(),
            'description' => $description,
        ]);

        Audit::log('attachment.added', "Uploaded \"{$attachment->original_name}\"", $attachable, ['attachment_id' => $attachment->id], $user);

        return $attachment;
    }

    public function delete(Attachment $attachment, User $actor): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        Audit::log('attachment.deleted', "Deleted \"{$attachment->original_name}\"", $attachment->attachable, [], $actor);
        $attachment->delete();
    }

    private function check(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = config('organizer.attachments.extensions');

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'The file must be one of: '.implode(', ', $allowed).'.']);
        }

        if ($file->getSize() > config('organizer.attachments.max_kb') * 1024) {
            throw ValidationException::withMessages(['file' => 'The file may not be larger than 10 MB.']);
        }

        $mime = $this->sniff($file);
        if (! in_array($mime, self::MIME_FAMILIES[$extension] ?? [], true)) {
            throw ValidationException::withMessages(['file' => "\"{$file->getClientOriginalName()}\" does not look like a real .{$extension} file."]);
        }
    }

    /** The content type from the file's bytes (falls back to the upload's own report off local disk). */
    private function sniff(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path && is_readable($path)) {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return (string) $file->getMimeType();
    }

    private function safeName(string $name): string
    {
        $name = trim(preg_replace('/[^\pL\pN ._()-]+/u', '-', $name) ?? '', ' .-');

        return Str::limit($name !== '' ? $name : 'file', 200, '');
    }
}
