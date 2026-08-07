<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Misinformation = 'misinformation';
    case OffTopic = 'off_topic';
    case AutoFlagged = 'auto_flagged';
    case Other = 'other';
}
