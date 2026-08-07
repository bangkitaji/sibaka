<?php

declare(strict_types=1);

namespace App\Enums;

enum ModerationAction: string
{
    case RemoveContent = 'remove_content';
    case SuspendUser = 'suspend_user';
    case IssueWarning = 'issue_warning';
    case Dismiss = 'dismiss';
}
