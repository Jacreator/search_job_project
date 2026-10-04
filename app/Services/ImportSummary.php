<?php

declare(strict_types=1);

namespace App\Services;

final readonly class ImportSummary
{
    public function __construct(
        public int $read,
        public int $ignored,
        public int $new,
        public int $existing,
        public int $movedToSkipped,
        public int $movedToPending,
    ) {}
}
