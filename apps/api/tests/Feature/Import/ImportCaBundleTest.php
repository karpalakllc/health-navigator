<?php

namespace Tests\Feature\Import;

use App\Support\Import\SourceFetcher;
use App\Support\Import\SourcePolicy;
use App\Support\Licences\KomoraLicenceFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * IMPORT_CA_BUNDLE: extra public CA certificates (an intermediate a source
 * host fails to send — arhiva.fzo.org.mk) are ADDED to the system trust
 * store for the import fetchers only. Verification is never turned off.
 */
class ImportCaBundleTest extends TestCase
{
    private const SHIPPED = 'resources/tls/import-extra-ca.crt';

    /** @var list<mixed> the Guzzle `verify` option of every request */
    private array $verify = [];

    /** @var resource|null */
    private $server = null;

    private ?string $dir = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'import.disk' => 'local',
            'import.request_delay_seconds' => 0,
            'import.contact' => 'data@example.test',
            'import.fzom.files' => ['pzz' => 'https://registry.test/XML/pzz.xml'],
            'licences.komora.list_url' => 'https://lkm.example/mk/record/121/962/lista',
            'licences.komora.request_delay_ms' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        if ($this->dir !== null) {
            array_map('unlink', glob($this->dir.'/*') ?: []);
            @rmdir($this->dir);
        }

        parent::tearDown();
    }

    private function recordVerify(): void
    {
        Http::fake(function (Request $request, array $options) {
            $this->verify[] = $options['verify'] ?? null;

            return Http::response('', 404);
        });
    }

    private function fetchBoth(): void
    {
        try {
            app(SourceFetcher::class)->fetch('fzom', 'pzz', 'https://registry.test/XML/pzz.xml', null);
        } catch (Throwable) {
            // 404: only the request options matter here.
        }

        try {
            app(KomoraLicenceFetcher::class)->fetch();
        } catch (Throwable) {
        }
    }

    public function test_the_shipped_certificate_is_the_fzom_intermediate_and_still_valid(): void
    {
        $pem = (string) file_get_contents(base_path(self::SHIPPED));
        $cert = openssl_x509_read($pem);
        $this->assertNotFalse($cert);
        $info = (array) openssl_x509_parse($cert);

        $this->assertSame('GeoTrust TLS RSA CA G1', $info['subject']['CN'] ?? null);
        $this->assertSame('DigiCert Global Root G2', $info['issuer']['CN'] ?? null);
        $this->assertSame('c06e307f7cfc1d32fa72a4c033c87b90019af216f0775d64978a2eca6c8a230e', openssl_x509_fingerprint($cert, 'sha256'));
        $this->assertGreaterThan(time(), (int) $info['validTo_time_t'], 'Renew the shipped intermediate (docs/data-import.md §3).');
        $this->assertSame(self::SHIPPED, config('import.ca_bundle'), 'Shipped and used by default.');
    }

    public function test_both_fetchers_verify_against_the_system_store_plus_the_extra_bundle(): void
    {
        config(['import.ca_bundle' => self::SHIPPED]);
        $this->recordVerify();

        $this->fetchBoth();

        $this->assertGreaterThanOrEqual(4, count($this->verify), 'robots.txt and the file (ФЗОМ), robots.txt and the list page (Комора)');

        foreach ($this->verify as $verify) {
            $this->assertIsString($verify);
            $this->assertFileExists($verify);
            $bundle = (string) file_get_contents($verify);
            $this->assertStringContainsString(trim((string) preg_replace('/^#.*\n/m', '', (string) file_get_contents(base_path(self::SHIPPED)))), $bundle);
            // The system roots are still there: a bundle of the extra certificate alone would break every other host.
            $this->assertGreaterThan(50, substr_count($bundle, 'BEGIN CERTIFICATE'));
        }
    }

    public function test_without_a_bundle_the_default_verification_applies_and_a_bad_bundle_is_refused(): void
    {
        config(['import.ca_bundle' => '']);
        $this->recordVerify();
        $this->fetchBoth();
        $this->assertNotContains(false, $this->verify);
        $this->assertSame([], SourcePolicy::tlsOptions());

        config(['import.ca_bundle' => 'resources/tls/missing.pem']);
        $this->expectException(RuntimeException::class);
        SourcePolicy::tlsOptions();
    }

    /**
     * A local TLS server that, like arhiva.fzo.org.mk, sends its certificate
     * without the intermediate: refused by default, accepted with the
     * intermediate in IMPORT_CA_BUNDLE, refused with an unrelated one.
     */
    public function test_a_server_with_an_incomplete_chain_is_trusted_only_with_its_intermediate(): void
    {
        if (! $this->openssl('version')) {
            $this->markTestSkipped('The openssl binary is needed for the local TLS server.');
        }

        $this->dir = sys_get_temp_dir().'/import-ca-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $d = $this->dir;
        $this->assertTrue($this->openssl("req -x509 -newkey rsa:2048 -nodes -keyout {$d}/root.key -out {$d}/root.pem -days 2 -subj /CN=Test-Root -addext basicConstraints=critical,CA:TRUE -addext keyUsage=critical,keyCertSign,cRLSign"));
        $this->assertTrue($this->openssl("req -newkey rsa:2048 -nodes -keyout {$d}/int.key -out {$d}/int.csr -subj /CN=Test-Intermediate"));
        file_put_contents("{$d}/int.ext", "basicConstraints=critical,CA:TRUE,pathlen:0\nkeyUsage=critical,keyCertSign,cRLSign\n");
        $this->assertTrue($this->openssl("x509 -req -in {$d}/int.csr -CA {$d}/root.pem -CAkey {$d}/root.key -CAcreateserial -out {$d}/int.pem -days 2 -extfile {$d}/int.ext"));
        $this->assertTrue($this->openssl("req -newkey rsa:2048 -nodes -keyout {$d}/leaf.key -out {$d}/leaf.csr -subj /CN=localhost"));
        file_put_contents("{$d}/leaf.ext", "subjectAltName=DNS:localhost\nextendedKeyUsage=serverAuth\n");
        $this->assertTrue($this->openssl("x509 -req -in {$d}/leaf.csr -CA {$d}/int.pem -CAkey {$d}/int.key -CAcreateserial -out {$d}/leaf.pem -days 2 -extfile {$d}/leaf.ext"));
        $this->assertTrue($this->openssl("req -x509 -newkey rsa:2048 -nodes -keyout {$d}/other.key -out {$d}/other.pem -days 2 -subj /CN=Unrelated-CA -addext basicConstraints=critical,CA:TRUE"));

        $port = $this->startServer("{$d}/leaf.pem", "{$d}/leaf.key");
        $url = "https://localhost:{$port}/";

        config(['import.ca_bundle' => '']);
        $this->assertTlsFails($url);

        config(['import.ca_bundle' => "{$d}/other.pem"]);
        $this->assertTlsFails($url);

        config(['import.ca_bundle' => "{$d}/int.pem"]);
        $this->assertTrue(Http::withOptions(SourcePolicy::tlsOptions())->timeout(10)->get($url)->successful());
    }

    private function assertTlsFails(string $url): void
    {
        try {
            Http::withOptions(SourcePolicy::tlsOptions())->timeout(10)->get($url);
            $this->fail('The incomplete chain was accepted.');
        } catch (ConnectionException $exception) {
            $this->assertStringContainsStringIgnoringCase('certificate', $exception->getMessage());
        }
    }

    private function openssl(string $arguments): bool
    {
        exec('openssl '.$arguments.' 2>/dev/null', $output, $status);

        return $status === 0;
    }

    private function startServer(string $cert, string $key): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $this->server = proc_open(['openssl', 's_server', '-accept', '127.0.0.1:'.$port, '-cert', $cert, '-key', $key, '-www', '-quiet'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);

        for ($i = 0; $i < 50; $i++) {
            $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);

            if ($probe !== false) {
                fclose($probe);

                return $port;
            }

            usleep(100_000);
        }

        $this->fail('The local TLS server did not start.');
    }
}
