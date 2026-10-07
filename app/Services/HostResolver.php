<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Looks up a host's IPv4 addresses. Its own class so tests can replace the DNS lookup.
 */
class HostResolver
{
    /**
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : $addresses;
    }
}
