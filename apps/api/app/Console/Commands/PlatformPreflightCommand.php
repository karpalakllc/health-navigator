<?php

namespace App\Console\Commands;

use App\Http\Middleware\TrustWebTierClientIp;
use Illuminate\Console\Command;

/**
 * Refuses a staging/production deploy whose configuration is known-unsafe.
 *
 * Every check here is a mistake that does not surface as an error at runtime:
 * a log mailer swallows password resets, a sync queue runs mail inline, a "*"
 * proxy list lets any client choose its own rate-limit bucket, an http
 * frontend URL puts plaintext links in every email. Run it after setting the
 * environment and before `migrate` / sending traffic (infra/deploy.md).
 *
 * Reads config() only, so it reports what the application will actually use
 * — including after `config:cache`, when .env is no longer loaded.
 */
class PlatformPreflightCommand extends Command
{
    protected $signature = 'platform:preflight {--json : Print the findings as JSON}';

    protected $description = 'Check that the deployed configuration is safe for staging/production';

    /** Environments where findings are reported but never fail the command. */
    private const RELAXED_ENVIRONMENTS = ['local', 'testing'];

    /** Values that make TRUSTED_PROXIES trust every hop. */
    private const TRUST_EVERYONE = ['*', '**', '0.0.0.0/0', '::/0'];

    /** Stores that are per-process or per-host, so rate limits stop being shared. */
    private const UNSHARED_CACHE_STORES = ['array', 'file', 'null'];

    private const SHARED_CACHE_STORES = ['redis', 'database', 'memcached', 'dynamodb'];

    /** @var list<array{level: string, check: string, message: string}> */
    private array $findings = [];

    public function handle(): int
    {
        $this->findings = [];
        $this->runChecks();

        $errors = $this->ofLevel('error');
        $warnings = $this->ofLevel('warning');
        $enforced = ! $this->laravel->environment(self::RELAXED_ENVIRONMENTS);
        $failed = $enforced && $errors !== [];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'environment' => $this->laravel->environment(),
                'enforced' => $enforced,
                'passed' => ! $failed,
                'errors' => $errors,
                'warnings' => $warnings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        $this->report($errors, $warnings, $enforced);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function runChecks(): void
    {
        $this->checkApp();
        $this->checkTrustedProxies();
        $this->checkWebTier();
        $this->checkFrontendUrls();
        $this->checkMail();
        $this->checkQueueAndCache();
        $this->checkSessionAndAuth();
        $this->checkCors();
        $this->checkSeeding();
        $this->checkMedia();
        $this->checkSearch();
        $this->checkMonitoring();
    }

    private function checkApp(): void
    {
        if (blank(config('app.key'))) {
            $this->addError('app.key', 'APP_KEY is not set. Generate one per environment with `php artisan key:generate --show` and store it in the secret manager — never generate it during a build.');
        }

        if (config('app.debug') === true) {
            $this->addError('app.debug', 'APP_DEBUG is true. Debug pages leak environment variables, queries and stack traces to every visitor.');
        }

        if ($problem = $this->publicUrlProblem(config('app.url'))) {
            $this->addError('app.url', "APP_URL {$problem}. Trusted-host checks and signed links are built from it.");
        }
    }

    private function checkTrustedProxies(): void
    {
        $proxies = config('trustedproxy.proxies');
        $list = is_array($proxies)
            ? $proxies
            : array_map('trim', explode(',', (string) $proxies));
        $list = array_values(array_filter($list, fn ($proxy) => filled($proxy)));

        if ($list === []) {
            $this->addError('trustedproxy.proxies', 'TRUSTED_PROXIES is not set. Behind an edge every IP rate limit then keys on the load balancer, and a few failed logins lock out everyone. List the CIDR ranges of the API\'s edge/load balancer.');

            return;
        }

        $everyone = array_intersect($list, self::TRUST_EVERYONE);

        if ($everyone !== []) {
            $this->addError('trustedproxy.proxies', 'TRUSTED_PROXIES contains "'.implode('", "', $everyone).'", which trusts every hop: the client-supplied leftmost X-Forwarded-For entry becomes $request->ip(), so any caller picks its own rate-limit bucket. List the exact CIDR ranges instead.');
        }
    }

    private function checkWebTier(): void
    {
        // Never echo the value: preflight output ends up in deploy logs.
        if (strlen((string) config('zdravje.web_tier.secret')) < TrustWebTierClientIp::MIN_SECRET_LENGTH) {
            $this->addError('zdravje.web_tier.secret', 'WEB_TIER_SECRET must be set (at least '.TrustWebTierClientIp::MIN_SECRET_LENGTH.' characters) and match the web tier\'s. Without it the API cannot tell visitors apart behind the web tier, so every server-rendered page and relayed sign-in shares one rate-limit bucket.');
        }
    }

    private function checkFrontendUrls(): void
    {
        foreach (['zdravje.frontend_url' => 'FRONTEND_URL', 'zdravje.web_public_url' => 'WEB_PUBLIC_URL'] as $key => $variable) {
            if ($problem = $this->publicUrlProblem(config($key))) {
                $this->addError($key, "{$variable} {$problem}. Password-reset, verification and moderation emails link to it.");
            }
        }
    }

    private function checkMail(): void
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->addError('mail.default', "MAIL_MAILER is \"{$mailer}\": no mail is delivered, so users cannot reset passwords or verify their address.");
        }

        if (blank(config('mail.from.address'))) {
            $this->addError('mail.from.address', 'MAIL_FROM_ADDRESS is not set.');
        }

        if ($mailer === 'smtp') {
            $scheme = config('mail.mailers.smtp.scheme');

            if (filled($scheme) && ! in_array($scheme, ['smtp', 'smtps'], true)) {
                $this->addError('mail.mailers.smtp.scheme', "MAIL_SCHEME \"{$scheme}\" is rejected by Symfony Mailer. Use \"smtp\" (STARTTLS, usually port 587) or \"smtps\" (implicit TLS, port 465).");
            }
        }
    }

    private function checkQueueAndCache(): void
    {
        $queue = (string) config('queue.default');

        if (in_array($queue, ['sync', 'null'], true)) {
            $this->addError('queue.default', "QUEUE_CONNECTION is \"{$queue}\". Use redis (or database) with a running worker so mail and indexing do not run inside — or vanish from — the request.");
        }

        $store = (string) config('cache.default');

        if (in_array($store, self::UNSHARED_CACHE_STORES, true)) {
            $this->addError('cache.default', "CACHE_STORE is \"{$store}\", which is not shared between processes or hosts: rate limits and the login lockout reset per worker. Use redis (or database).");
        } elseif (! in_array($store, self::SHARED_CACHE_STORES, true)) {
            $this->addWarning('cache.default', "CACHE_STORE is \"{$store}\". Confirm it is shared across every API process — rate limits depend on it.");
        }
    }

    private function checkSessionAndAuth(): void
    {
        if (config('session.secure') !== true) {
            $this->addError('session.secure', 'SESSION_SECURE_COOKIE is not true, so the admin session cookie can be sent over plain HTTP.');
        }

        if (config('sanctum.expiration') === null) {
            $this->addError('sanctum.expiration', 'SANCTUM_TOKEN_EXPIRATION_MINUTES is null: API tokens never expire.');
        }
    }

    private function checkCors(): void
    {
        $origins = array_values(array_filter((array) config('cors.allowed_origins'), fn ($origin) => filled($origin)));

        if ($origins === []) {
            $this->addError('cors.allowed_origins', 'No CORS origins are allowed, so every browser call from the web app is blocked. Set CORS_ALLOWED_ORIGINS.');

            return;
        }

        foreach ($origins as $origin) {
            if ($origin === '*') {
                $this->addError('cors.allowed_origins', 'CORS allows every origin ("*"). List the web origins in CORS_ALLOWED_ORIGINS.');
            } elseif ($this->isLocalHost(parse_url((string) $origin, PHP_URL_HOST))) {
                $this->addError('cors.allowed_origins', "CORS allows the local origin {$origin}. Only the deployed web origins belong here.");
            }
        }
    }

    private function checkSeeding(): void
    {
        $email = (string) config('zdravje.admin.email');

        if (blank($email) || str_ends_with(strtolower($email), '.test')) {
            $this->addError('zdravje.admin.email', 'PLATFORM_ADMIN_EMAIL is unset or the .test development default. Use a real mailbox you control — password resets go there.');
        }

        if (config('zdravje.seed.local_demo') === true) {
            $this->addError('zdravje.seed.local_demo', 'SEED_LOCAL_DEMO is true: seeding would create demo staff and member accounts with weak, published passwords.');
        }
    }

    private function checkMedia(): void
    {
        $disk = (string) config('media.disk');

        if (config("filesystems.disks.{$disk}") === null) {
            $this->addError('media.disk', "MEDIA_DISK \"{$disk}\" is not a configured filesystem disk.");

            return;
        }

        if ($disk === 'public') {
            $this->addWarning('media.disk', 'MEDIA_DISK is "public" (local storage). On an ephemeral PaaS filesystem every uploaded logo and avatar is lost on redeploy — use object storage, or a persistent volume plus `php artisan storage:link`.');
        }
    }

    private function checkSearch(): void
    {
        if (config('scout.driver') !== 'meilisearch') {
            return;
        }

        $host = config('scout.meilisearch.host');

        if (blank($host) || $this->isLocalHost(parse_url((string) $host, PHP_URL_HOST))) {
            $this->addError('scout.meilisearch.host', 'SCOUT_DRIVER is meilisearch but MEILISEARCH_HOST is unset or points at localhost.');
        }

        if (blank(config('scout.meilisearch.key'))) {
            $this->addError('scout.meilisearch.key', 'SCOUT_DRIVER is meilisearch but MEILISEARCH_KEY is not set.');
        }
    }

    private function checkMonitoring(): void
    {
        if (blank(config('sentry.dsn'))) {
            $this->addWarning('sentry.dsn', 'SENTRY_LARAVEL_DSN is not set; production errors will go unnoticed.');
        }
    }

    /** Why a URL is unfit to be the public origin, or null when it is fine. */
    private function publicUrlProblem(mixed $url): ?string
    {
        if (blank($url)) {
            return 'is not set';
        }

        $parts = parse_url((string) $url);

        if (($parts['scheme'] ?? null) !== 'https') {
            return "\"{$url}\" is not https";
        }

        if ($this->isLocalHost($parts['host'] ?? null)) {
            return "\"{$url}\" points at a local host";
        }

        return null;
    }

    private function isLocalHost(mixed $host): bool
    {
        if (! is_string($host) || $host === '') {
            return true;
        }

        $host = strtolower(trim($host, '[]'));

        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }

    private function addError(string $check, string $message): void
    {
        $this->findings[] = ['level' => 'error', 'check' => $check, 'message' => $message];
    }

    private function addWarning(string $check, string $message): void
    {
        $this->findings[] = ['level' => 'warning', 'check' => $check, 'message' => $message];
    }

    /** @return list<array{check: string, message: string}> */
    private function ofLevel(string $level): array
    {
        return array_values(array_map(
            fn (array $finding) => ['check' => $finding['check'], 'message' => $finding['message']],
            array_filter($this->findings, fn (array $finding) => $finding['level'] === $level),
        ));
    }

    /**
     * @param  list<array{check: string, message: string}>  $errors
     * @param  list<array{check: string, message: string}>  $warnings
     */
    private function report(array $errors, array $warnings, bool $enforced): void
    {
        $environment = $this->laravel->environment();

        foreach ($errors as $finding) {
            $this->components->error("[{$finding['check']}] {$finding['message']}");
        }

        foreach ($warnings as $finding) {
            $this->components->warn("[{$finding['check']}] {$finding['message']}");
        }

        $summary = sprintf('%d error(s), %d warning(s) for APP_ENV=%s.', count($errors), count($warnings), $environment);

        if (! $enforced) {
            $this->components->info('Preflight not enforced in local/testing: '.$summary);
        } elseif ($errors !== []) {
            $this->components->error('Preflight FAILED: '.$summary.' Fix the errors before migrating or sending traffic.');
        } else {
            $this->components->info('Preflight passed: '.$summary);
        }
    }
}
