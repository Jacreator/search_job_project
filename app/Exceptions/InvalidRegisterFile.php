<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidRegisterFile extends RuntimeException
{
    public static function missing(string $path): self
    {
        return new self("The register file [{$path}] does not exist.");
    }

    /**
     * @param  list<string>  $headers
     */
    public static function missingHeaders(array $headers): self
    {
        return new self('The register file is missing required headers: '.implode(', ', $headers).'.');
    }
}
