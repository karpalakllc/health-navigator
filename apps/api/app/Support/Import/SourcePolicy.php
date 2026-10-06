<?php

namespace App\Support\Import;

use Illuminate\Http\Client\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * What every source download shares (ФЗОМ, Лекарска комора):
 *
 * - one User-Agent: the product token (IMPORT_USER_AGENT) plus a contact
 *   (IMPORT_CONTACT, an e-mail address; a leading „mailto:“ is tolerated);
 * - only https URLs on the configured hosts, never an IP address, and
 *   redirects only within those hosts (a link on a source page cannot send
 *   us to an internal address);
 * - robots.txt: a 4xx answer means "no rules", a 5xx (or anything else
 *   unusable) means we cannot tell — so we do not fetch.
 */
final class SourcePolicy
{
    public static function agentToken(): string
    {
        $token = trim((string) config('import.user_agent'));

        return (string) (strtok($token, '/ (') ?: 'Zdravje360-DirectoryImport');
    }

    public static function userAgent(): string
    {
        $contact = preg_replace('/^mailto:/i', '', trim((string) config('import.contact')));

        return sprintf('%s (+mailto:%s)', trim((string) config('import.user_agent')), $contact);
    }

    /**
     * @param  list<string>  $allowedHosts
     */
    public static function assertFetchable(string $url, array $allowedHosts): void
    {
        if (! self::isFetchable($url, $allowedHosts)) {
            throw new RuntimeException(sprintf('Refusing to fetch %s: only https URLs on %s are allowed.', $url, implode(', ', $allowedHosts)));
        }
    }

    /**
     * @param  list<string>  $allowedHosts
     */
    public static function isFetchable(string $url, array $allowedHosts): bool
    {
        $parts = parse_url($url);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && $host !== ''
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && filter_var($host, FILTER_VALIDATE_IP) === false
            && in_array($host, array_map('strtolower', $allowedHosts), true);
    }

    /**
     * Guzzle redirect options: at most three hops, https, same hosts.
     *
     * @param  list<string>  $allowedHosts
     * @return array{allow_redirects: array<string, mixed>}
     */
    public static function redirectOptions(array $allowedHosts): array
    {
        return ['allow_redirects' => [
            'max' => 3,
            'strict' => true,
            'referer' => false,
            'protocols' => ['https'],
            'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri) use ($allowedHosts): void {
                self::assertFetchable((string) $uri, $allowedHosts);
            },
        ]];
    }

    /**
     * The robots.txt body to apply ('' = no rules).
     */
    public static function robotsBody(Response $response, string $host): string
    {
        return match (true) {
            $response->successful() => $response->body(),
            $response->status() >= 400 && $response->status() < 500 => '',
            default => throw new RuntimeException("robots.txt of {$host} answered HTTP {$response->status()}; not fetching."),
        };
    }
}
