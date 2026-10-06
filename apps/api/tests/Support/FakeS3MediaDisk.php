<?php

namespace Tests\Support;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Storage;

/**
 * The real "s3" media disk — Laravel's driver, Flysystem's S3 adapter and the
 * AWS SDK's command building — with only the HTTP transport replaced. Every
 * command the SDK would have sent is recorded, so a test can assert the exact
 * PutObject parameters (ACL, Content-Type, Cache-Control) a bucket would see.
 * Storage::fake() cannot do that: it swaps in a local disk, which drops them.
 */
final class FakeS3MediaDisk
{
    /** @var list<CommandInterface> */
    private array $commands = [];

    private bool $failWrites = false;

    /** @param  array<string, mixed>  $overrides  s3 disk config, as config/filesystems.php reads it */
    public static function install(array $overrides = []): self
    {
        $fake = new self;

        config([
            'media.disk' => 's3',
            'filesystems.disks.s3' => array_merge([
                'driver' => 's3',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'region' => 'eu-central-1',
                'bucket' => 'zdravje-media',
                'url' => null,
                'endpoint' => null,
                'use_path_style_endpoint' => false,
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
                'handler' => fn (CommandInterface $command): PromiseInterface => $fake->handle($command),
            ], $overrides),
        ]);

        Storage::forgetDisk('s3');

        return $fake;
    }

    public function failWrites(): self
    {
        $this->failWrites = true;

        return $this;
    }

    /** @return list<CommandInterface> */
    public function commands(string $name): array
    {
        return array_values(array_filter(
            $this->commands,
            fn (CommandInterface $command): bool => $command->getName() === $name,
        ));
    }

    private function handle(CommandInterface $command): PromiseInterface
    {
        $this->commands[] = $command;

        if ($this->failWrites && $command->getName() === 'PutObject') {
            return Create::rejectionFor(new S3Exception('Access Denied', $command));
        }

        return Create::promiseFor(new Result([]));
    }
}
