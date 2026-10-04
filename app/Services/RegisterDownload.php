<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RegisterDownloadFailed;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the latest sponsor register CSV from GOV.UK.
 */
final class RegisterDownload
{
    private const ASSET_HOST = 'assets.publishing.service.gov.uk';

    private const MAX_BYTES = 50 * 1024 * 1024;

    public function __construct(
        #[Config('sponsor-finder.register_content_url')] private readonly string $contentUrl,
    ) {}

    /**
     * Save the latest register into the directory and return its path.
     * A register already saved there is reused, not downloaded again.
     *
     * @throws RegisterDownloadFailed
     */
    public function latest(string $directory): RegisterFile
    {
        $attachment = $this->latestAttachment();
        $path = rtrim($directory, '/').'/'.$this->fileName($attachment['filename']);

        if (is_file($path)) {
            return new RegisterFile($path, downloaded: false);
        }

        $this->download($attachment['url'], $path);

        return new RegisterFile($path, downloaded: true);
    }

    /**
     * Find the register CSV in the GOV.UK content API response for the publication.
     *
     * @return array{url: string, filename: string}
     *
     * @throws RegisterDownloadFailed
     */
    private function latestAttachment(): array
    {
        try {
            $attachments = Http::timeout(10)
                ->retry(2, 500)
                ->acceptJson()
                ->get($this->contentUrl)
                ->throw()
                ->json('details.attachments');
        } catch (ConnectionException|RequestException $exception) {
            throw RegisterDownloadFailed::because('the publication page could not be loaded.');
        }

        foreach (is_array($attachments) ? $attachments : [] as $attachment) {
            if (! is_array($attachment) || ($attachment['content_type'] ?? null) !== 'text/csv') {
                continue;
            }

            $url = $attachment['url'] ?? null;
            $filename = $attachment['filename'] ?? null;

            if (! is_string($url) || ! is_string($filename) || ! $this->isAssetUrl($url)) {
                continue;
            }

            return ['url' => $url, 'filename' => $filename];
        }

        throw RegisterDownloadFailed::because('no register CSV was found on the publication page.');
    }

    private function isAssetUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_HOST) === self::ASSET_HOST;
    }

    /**
     * Name the file after the register date, for example "register-2026-10-02.csv".
     */
    private function fileName(string $filename): string
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})\.csv$/i', $filename, $matches) === 1) {
            return "register-{$matches[1]}.csv";
        }

        return (string) preg_replace('/[^A-Za-z0-9._-]/', '_', basename($filename));
    }

    /**
     * Stream the CSV to a temporary file, then move it into place, so a failed
     * download never leaves a partial register behind.
     *
     * @throws RegisterDownloadFailed
     */
    private function download(string $url, string $path): void
    {
        $partial = $path.'.part';

        try {
            Http::timeout(120)
                ->retry(2, 1000)
                ->sink($partial)
                ->get($url)
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            @unlink($partial);

            throw RegisterDownloadFailed::because('the register CSV could not be downloaded.');
        }

        $size = (int) filesize($partial);

        if ($size === 0 || $size > self::MAX_BYTES) {
            @unlink($partial);

            throw RegisterDownloadFailed::because('the register CSV was empty or too large.');
        }

        rename($partial, $path);
    }
}
