<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ExportFailed extends RuntimeException
{
    public static function cannotWrite(string $path): self
    {
        return new self("The export file [{$path}] could not be written.");
    }
}
