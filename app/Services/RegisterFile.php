<?php

declare(strict_types=1);

namespace App\Services;

final readonly class RegisterFile
{
    public function __construct(
        public string $path,
        public bool $downloaded,
    ) {}
}
