<?php

declare(strict_types=1);

namespace App\Enums;

enum SponsorStatus: string
{
    case Pending = 'pending';
    case ChDone = 'ch_done';
    case Done = 'done';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
