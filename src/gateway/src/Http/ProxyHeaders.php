<?php

declare(strict_types=1);

namespace App\Http;

final class ProxyHeaders
{
    /**
     * @param array<string, list<string>> $headers
     *
     * @return array<string, list<string>>
     */
    public function filter(array $headers): array
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        $excluded = [
            'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
            'te', 'trailer', 'transfer-encoding', 'upgrade', 'host', 'content-length',
            'forwarded', 'via', 'x-real-ip',
        ];

        foreach ($headers['connection'] ?? [] as $connection) {
            $excluded = [...$excluded, ...array_map('trim', explode(',', strtolower($connection)))];
        }

        foreach (array_keys($headers) as $name) {
            if (in_array($name, $excluded, true)
                || str_starts_with($name, 'x-forwarded-')
                || str_starts_with($name, 'access-control-')
            ) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }

    public function location(string $location, string $upstream, string $path, string $publicOrigin, string $prefix): string
    {
        if (str_starts_with($location, '//')) {
            $location = parse_url($upstream, PHP_URL_SCHEME).':'.$location;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            if ($location !== $upstream && !str_starts_with($location, $upstream.'/')
                && !str_starts_with($location, $upstream.'?') && !str_starts_with($location, $upstream.'#')
            ) {
                return $location;
            }

            $location = substr($location, strlen($upstream));
        } elseif ('' !== $location && '/' !== $location[0]) {
            $basePath = explode('?', $path, 2)[0];
            $location = match ($location[0]) {
                '?' => $basePath.$location,
                '#' => $path.$location,
                default => substr($basePath, 0, strrpos($basePath, '/') + 1).$location,
            };
        }

        // Resolve relative redirects before prefixing, so ../ cannot escape the service.
        $parts = preg_split('/(?=[?#])/', $location, 2);
        $segments = [];

        foreach (explode('/', $parts[0]) as $segment) {
            if ('..' === $segment) {
                array_pop($segments);
            } elseif ('.' !== $segment && '' !== $segment) {
                $segments[] = $segment;
            }
        }

        $resolved = '/'.implode('/', $segments);

        if ('/' !== $resolved && str_ends_with($parts[0], '/')) {
            $resolved .= '/';
        }

        return $publicOrigin.$prefix.$resolved.($parts[1] ?? '');
    }
}
