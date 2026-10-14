<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PageText;
use App\Support\TermMatcher;
use DOMDocument;
use Illuminate\Container\Attributes\Config;

/**
 * Finds a sponsor's careers page: links on the homepage first, then common paths.
 * Only fetches the sponsor's own site and the listed job board hosts.
 */
final class CareersPageFinder
{
    /**
     * Tried on the sponsor's website when no homepage link works.
     */
    public const FALLBACK_PATHS = ['/careers', '/jobs', '/join-us', '/work-with-us'];

    /**
     * At most this many homepage links are tried, so a page full of matching links
     * does not turn into dozens of requests.
     */
    public const MAX_LINKS = 5;

    /**
     * The longest URL the `careers_url` column holds.
     */
    private const MAX_URL_LENGTH = 2048;

    /**
     * @param  list<string>  $linkTerms
     * @param  list<string>  $jobBoardHosts
     */
    public function __construct(
        private readonly PageFetcher $fetcher,
        #[Config('sponsor-finder.careers_link_terms')] private readonly array $linkTerms,
        #[Config('sponsor-finder.job_board_hosts')] private readonly array $jobBoardHosts,
    ) {}

    /**
     * The first candidate that answers 200, or null when none does.
     */
    public function find(string $website): ?CareersPage
    {
        $origin = self::origin($website);

        if ($origin === null) {
            return null;
        }

        $homepage = $this->fetcher->get($website);
        $candidates = [];

        if ($homepage !== null && $homepage->status === 200) {
            $sites = array_values(array_unique([self::site($website), self::site($homepage->url)]));
            $candidates = $this->links($homepage->body, $homepage->url, $sites);
            // A link back to the homepage itself (a logo, or "#careers" on a one-page site) is not a careers page.
            $homepages = [rtrim($website, '/'), rtrim($homepage->url, '/')];
            $candidates = array_values(array_filter(
                $candidates,
                fn (string $url): bool => ! in_array(rtrim($url, '/'), $homepages, true),
            ));
        }

        $candidates = array_slice($candidates, 0, self::MAX_LINKS);

        foreach (self::FALLBACK_PATHS as $path) {
            $candidates[] = $origin.$path;
        }

        foreach (array_unique($candidates) as $url) {
            $page = $this->fetcher->get($url);

            if ($page !== null && $page->status === 200) {
                return new CareersPage($url, PageText::from($page->body));
            }
        }

        return null;
    }

    /**
     * Links whose text or path matches a careers term, or that point at a job board,
     * in page order. Links to other sites are left out.
     *
     * @param  list<string>  $sites
     * @return list<string>
     */
    private function links(string $html, string $baseUrl, array $sites): array
    {
        $document = new DOMDocument;

        if (! @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return [];
        }

        $links = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            $url = self::resolve($baseUrl, trim($anchor->getAttribute('href')));

            if ($url === null || strlen($url) > self::MAX_URL_LENGTH) {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $path = (string) parse_url($url, PHP_URL_PATH);
            $onJobBoard = $this->isJobBoard($host);

            if (! $onJobBoard && ! self::onSite($host, $sites)) {
                continue;
            }

            if ($onJobBoard
                || TermMatcher::contains((string) $anchor->textContent, $this->linkTerms)
                || TermMatcher::contains(str_replace(['/', '_'], ' ', $path), $this->linkTerms)) {
                $links[] = $url;
            }
        }

        return array_values(array_unique($links));
    }

    private function isJobBoard(string $host): bool
    {
        foreach ($this->jobBoardHosts as $board) {
            if ($host === $board || str_ends_with($host, '.'.$board)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The host is the sponsor's site, "www." or not, or one of its subdomains.
     *
     * @param  list<string>  $sites
     */
    private static function onSite(string $host, array $sites): bool
    {
        $host = self::withoutWww($host);

        foreach ($sites as $site) {
            if ($site !== '' && ($host === $site || str_ends_with($host, '.'.$site))) {
                return true;
            }
        }

        return false;
    }

    private static function site(string $url): string
    {
        return self::withoutWww(strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    private static function withoutWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Scheme, host, and port of an http or https URL, or null.
     */
    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        return $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * An absolute http or https URL for a link, without its fragment, or null for anything else
     * (mailto, tel, javascript, or an empty or fragment-only link).
     */
    private static function resolve(string $base, string $href): ?string
    {
        $href = self::withoutFragment($href);

        if ($href === '') {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1) {
            return preg_match('#^https?://#i', $href) === 1 ? $href : null;
        }

        $origin = self::origin($base);

        if ($origin === null) {
            return null;
        }

        if (str_starts_with($href, '//')) {
            return strtolower((string) parse_url($base, PHP_URL_SCHEME)).':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $basePath = (string) parse_url($base, PHP_URL_PATH);

        if (str_starts_with($href, '?')) {
            return $origin.($basePath === '' ? '/' : $basePath).$href;
        }

        $directory = substr($basePath, 0, (int) strrpos('/'.$basePath, '/') - 1);

        return $origin.rtrim($directory, '/').'/'.$href;
    }

    private static function withoutFragment(string $url): string
    {
        $position = strpos($url, '#');

        return $position === false ? $url : substr($url, 0, $position);
    }
}
