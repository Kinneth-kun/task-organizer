<?php

namespace App\Enums;

enum NotificationType: string
{
    use HasOptions;

    case TaskAssigned = 'task_assigned';
    case CollaboratorAdded = 'collaborator_added';
    case ProjectMemberAdded = 'project_member_added';
    case DeadlineApproaching = 'deadline_approaching';
    case TaskDelayed = 'task_delayed';
    case TaskCompleted = 'task_completed';
    case Mentioned = 'mentioned';
    case CommentAdded = 'comment_added';
    case TaskUpdated = 'task_updated';
    case ProjectCompleted = 'project_completed';
    case SuggestionAdded = 'suggestion_added';
    case AttachmentAdded = 'attachment_added';
    case RemarkReviewed = 'remark_reviewed';
    case DailyDigest = 'daily_digest';

    public function label(): string
    {
        return match ($this) {
            self::TaskAssigned => 'Assigned to you',
            self::CollaboratorAdded => 'Added as collaborator',
            self::ProjectMemberAdded => 'Added to project',
            self::DeadlineApproaching => 'Deadline approaching',
            self::TaskDelayed => 'Task delayed',
            self::TaskCompleted => 'Task completed',
            self::Mentioned => 'Mentioned you',
            self::CommentAdded => 'New comment',
            self::TaskUpdated => 'Task updated',
            self::ProjectCompleted => 'Project completed',
            self::SuggestionAdded => 'New suggestion',
            self::AttachmentAdded => 'File attached',
            self::RemarkReviewed => 'Remark reviewed',
            self::DailyDigest => 'Daily briefing',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TaskAssigned => 'user-plus',
            self::CollaboratorAdded => 'users',
            self::ProjectMemberAdded => 'folder',
            self::DeadlineApproaching => 'clock',
            self::TaskDelayed => 'alert',
            self::TaskCompleted, self::ProjectCompleted => 'check-circle',
            self::Mentioned => 'at',
            self::CommentAdded, self::SuggestionAdded => 'chat',
            self::TaskUpdated => 'pencil',
            self::AttachmentAdded => 'paperclip',
            self::RemarkReviewed => 'eye',
            self::DailyDigest => 'sun',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TaskAssigned, self::ProjectMemberAdded, self::TaskUpdated => 'indigo',
            self::CollaboratorAdded, self::DailyDigest => 'violet',
            self::DeadlineApproaching, self::SuggestionAdded => 'amber',
            self::TaskDelayed => 'red',
            self::TaskCompleted, self::ProjectCompleted, self::RemarkReviewed => 'emerald',
            self::Mentioned, self::CommentAdded => 'sky',
            self::AttachmentAdded => 'slate',
        };
    }
}
