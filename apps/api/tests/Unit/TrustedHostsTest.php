<?php

namespace Tests\Unit;

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

/**
 * The Host allowlist is what stops a forged host being used to mint a
 * verification link on a domain someone else controls.
 *
 * It cannot be exercised the ordinary way: TrustHosts::shouldSpecifyTrustedHosts()
 * returns false under `local` and while running tests, so a feature test proves
 * nothing about it — the middleware disables itself before doing any work. This
 * subclass forces it on so the behaviour is actually asserted somewhere.
 */
class TrustedHostsTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    private function middleware(): TrustHosts
    {
        return new class($this->app) extends TrustHosts
        {
            protected function shouldSpecifyTrustedHosts(): bool
            {
                return true;
            }
        };
    }

    private function handle(string $host): Request
    {
        config(['app.url' => 'https://zdravje360.mk']);

        $request = Request::create('https://zdravje360.mk/api/v1/health');
        $request->headers->set('Host', $host);
        $request->server->set('HTTP_HOST', $host);

        $this->middleware()->handle($request, fn (Request $r) => $r);

        return $request;
    }

    public function test_the_canonical_host_is_accepted(): void
    {
        $request = $this->handle('zdravje360.mk');

        $this->assertSame('zdravje360.mk', $request->getHost());
    }

    public function test_a_subdomain_of_the_canonical_host_is_accepted(): void
    {
        $request = $this->handle('www.zdravje360.mk');

        $this->assertSame('www.zdravje360.mk', $request->getHost());
    }

    public function test_a_forged_host_is_rejected(): void
    {
        // Without this, URL::temporarySignedRoute() would build a verification
        // link on the attacker's domain and the platform would mail it out.
        $this->expectException(SuspiciousOperationException::class);

        $this->handle('evil.example.net')->getHost();
    }

    public function test_a_lookalike_suffix_is_rejected(): void
    {
        $this->expectException(SuspiciousOperationException::class);

        $this->handle('zdravje360.mk.evil.example.net')->getHost();
    }
}
