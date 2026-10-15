<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every in-app notification. The bell and notification centre read `data`; people who opted in
 * also get an email (queued). The database copy is written immediately, without a queue worker.
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $url  Site-relative link ("/tasks/12"), so it works on any domain.
     * @param  array{type: string, id: int}|null  $subject
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public NotificationType $type,
        public string $title,
        public ?string $message = null,
        public ?string $url = null,
        public ?int $actorId = null,
        public ?string $actorName = null,
        public ?array $subject = null,
        public array $extra = [],
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsEmailFor($this->type) && $notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, string> */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /** Stored in notifications.type, so notifications can be filtered by kind. */
    public function databaseType(object $notifiable): string
    {
        return $this->type->value;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'subject_type' => $this->subject['type'] ?? null,
            'subject_id' => $this->subject['id'] ?? null,
            ...$this->extra,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hi '.$notifiable->firstName().',')
            ->line($this->title);

        if ($this->message) {
            $mail->line($this->message);
        }

        foreach ($this->extra['lines'] ?? [] as $line) {
            $mail->line('• '.$line);
        }

        if ($this->url) {
            $mail->action('Open in '.config('app.name'), url($this->url));
        }

        return $mail->line('You can change which emails you receive on your profile page.');
    }
}
