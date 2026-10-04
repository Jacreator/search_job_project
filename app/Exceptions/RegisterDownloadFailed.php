<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class RegisterDownloadFailed extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("Could not download the sponsor register from GOV.UK: {$reason}");
    }
}
