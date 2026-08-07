<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case PendingReview = 'pending_review';
    case Archived = 'archived';
    case Flagged = 'flagged';
}
