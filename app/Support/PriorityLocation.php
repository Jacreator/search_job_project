<?php

declare(strict_types=1);

namespace App\Support;

final readonly class PriorityLocation
{
    public function __construct(
        public ?string $region,
        public int $priority,
    ) {}
}
