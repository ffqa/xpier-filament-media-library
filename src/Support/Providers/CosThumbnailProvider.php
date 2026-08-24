<?php

namespace Xpier\FilamentMediaLibrary\Support\Providers;

use Xpier\FilamentMediaLibrary\Support\ThumbnailProvider;

/**
 * Tencent COS on-the-fly thumbnail via imageMogr2.
 *
 * @see https://cloud.tencent.com/document/product/436/44880
 */
class CosThumbnailProvider implements ThumbnailProvider
{
    public function thumbnail(?string $url, int $maxEdge = 400, int $quality = 75): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        if (! config('filament-media-library.image_process', true)) {
            return $url;
        }

        if (! $this->isProcessable($url)) {
            return $url;
        }

        $maxEdge = max(32, min(2048, $maxEdge));
        $quality = max(20, min(100, $quality));
        $rule = "imageMogr2/thumbnail/{$maxEdge}x>/quality/{$quality}";

        return $this->withImageRule($url, $rule);
    }

    public function isProcessable(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return false;
        }

        if (str_contains($host, '.myqcloud.com') || str_contains($host, '.cos.')) {
            return true;
        }

        $configured = (string) config('filesystems.disks.s3.url', '');
        if ($configured !== '') {
            $configuredHost = strtolower((string) (parse_url($configured, PHP_URL_HOST) ?: ''));
            if ($configuredHost !== '' && $configuredHost === $host) {
                return true;
            }
        }

        return false;
    }

    protected function withImageRule(string $url, string $rule): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $query = $parts['query'] ?? '';
        $query = (string) preg_replace('/(^|&)(imageMogr2|imageView2)[^&]*/', '', $query);
        $query = trim($query, '&');
        $newQuery = $query === '' ? $rule : $query.'&'.$rule;

        $rebuilt = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }
        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= '?'.$newQuery;
        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }
}
