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
     * Every Guzzle option the import fetchers share: redirects (above) and
     * TLS (below).
     *
     * @param  list<string>  $allowedHosts
     * @return array<string, mixed>
     */
    public static function requestOptions(array $allowedHosts): array
    {
        return self::redirectOptions($allowedHosts) + self::tlsOptions();
    }

    /**
     * TLS for the import fetchers: the system trust store, plus the extra
     * public CA certificates of IMPORT_CA_BUNDLE (`import.ca_bundle`) — an
     * intermediate a source host fails to send (arhiva.fzo.org.mk). The two
     * are written into one PEM file (Guzzle's `verify` replaces the store,
     * it does not add to it), cached by content under storage/framework.
     * Certificate verification is never turned off: a missing or unreadable
     * bundle is an error, not a fallback.
     *
     * @return array{verify?: string}
     */
    public static function tlsOptions(): array
    {
        $extra = trim((string) config('import.ca_bundle'));

        return $extra === '' ? [] : ['verify' => self::combinedCaBundle($extra)];
    }

    private static function combinedCaBundle(string $extra): string
    {
        $path = str_starts_with($extra, '/') ? $extra : base_path($extra);
        $pem = is_file($path) && is_readable($path) ? (string) file_get_contents($path) : '';
        $count = preg_match_all('/-----BEGIN CERTIFICATE-----[\s\S]+?-----END CERTIFICATE-----/', $pem, $blocks);

        if ($count === 0 || $count === false) {
            throw new RuntimeException("IMPORT_CA_BUNDLE {$extra} is not a readable PEM file with a certificate.");
        }

        foreach ($blocks[0] as $block) {
            if (openssl_x509_read($block) === false) {
                throw new RuntimeException("IMPORT_CA_BUNDLE {$extra} holds a certificate that cannot be read.");
            }
        }

        $system = self::systemCaFile();
        $combined = rtrim((string) file_get_contents($system))."\n\n# IMPORT_CA_BUNDLE {$extra}\n".implode("\n", $blocks[0])."\n";
        $target = storage_path('framework/cache/import-ca-'.hash('sha256', $combined).'.pem');

        if (! is_file($target)) {
            $temporary = $target.'.'.bin2hex(random_bytes(4));
            file_put_contents($temporary, $combined);
            rename($temporary, $target);
        }

        return $target;
    }

    /**
     * The CA file curl would use by default (php.ini curl.cainfo, then
     * openssl.cafile, then OpenSSL's default / SSL_CERT_FILE).
     */
    private static function systemCaFile(): string
    {
        $locations = openssl_get_cert_locations();
        $candidates = [
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            getenv((string) ($locations['default_cert_file_env'] ?? 'SSL_CERT_FILE')),
            $locations['default_cert_file'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No system CA bundle found to add IMPORT_CA_BUNDLE to (set curl.cainfo in php.ini).');
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
