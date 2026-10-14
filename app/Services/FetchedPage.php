<?php

declare(strict_types=1);

namespace App\Services;

final readonly class FetchedPage
{
    /**
     * @param  string  $url  The address the page was finally served from, after redirects.
     */
    public function __construct(
        public string $url,
        public int $status,
        public string $body,
    ) {}
}
