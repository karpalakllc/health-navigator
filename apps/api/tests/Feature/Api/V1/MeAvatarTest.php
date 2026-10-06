<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ForumContentStatus;
use App\Enums\UserKind;
use App\Models\ForumPost;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeS3MediaDisk;
use Tests\TestCase;

class MeAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media.disk' => 'public']);
    }

    public function test_client_cannot_upload_avatar_before_message_threshold(): void
    {
        SiteSetting::current()->update(['profile_avatar_min_messages' => 10]);

        $user = User::factory()->create(['user_kind' => UserKind::Client]);
        $token = $user->createToken('test')->plainTextToken;

        ForumPost::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => ForumContentStatus::Approved,
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $this->withToken($token)
            ->postJson('/api/v1/me/avatar', ['avatar' => $file])
            ->assertForbidden();
    }

    public function test_client_can_upload_avatar_after_message_threshold(): void
    {
        SiteSetting::current()->update(['profile_avatar_min_messages' => 2]);

        $user = User::factory()->create(['user_kind' => UserKind::Client]);
        $token = $user->createToken('test')->plainTextToken;

        ForumPost::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => ForumContentStatus::Approved,
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $this->withToken($token)
            ->postJson('/api/v1/me/avatar', ['avatar' => $file])
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', fn (?string $url) => filled($url));

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $this->assertStringEndsWith('.webp', $user->avatar_path);
    }

    public function test_avatar_upload_lands_on_object_storage_with_public_cacheable_headers(): void
    {
        $s3 = FakeS3MediaDisk::install(['url' => 'https://media.zdravje360.mk']);
        config(['media.visibility' => 'public', 'media.cache_control' => 'public, max-age=31536000, immutable']);
        SiteSetting::current()->update(['profile_avatar_min_messages' => 0]);

        $user = User::factory()->create([
            'user_kind' => UserKind::Client,
            'avatar_path' => 'media/users/avatars/old.webp',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200)])
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', 'https://media.zdravje360.mk/'.$user->fresh()->avatar_path);

        $puts = $s3->commands('PutObject');
        $this->assertCount(1, $puts);
        $this->assertSame($user->fresh()->avatar_path, $puts[0]['Key']);
        $this->assertSame('public-read', $puts[0]['ACL']);
        $this->assertSame('image/webp', $puts[0]['ContentType']);
        $this->assertSame('public, max-age=31536000, immutable', $puts[0]['CacheControl']);

        $this->assertSame(['media/users/avatars/old.webp'], array_map(
            fn ($command) => $command['Key'],
            $s3->commands('DeleteObject'),
        ));
    }

    public function test_me_includes_avatar_initials_and_profile_meta(): void
    {
        $user = User::factory()->create([
            'user_kind' => UserKind::Client,
            'name' => 'Martin Ilievski',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.avatar_initials', 'MI')
            ->assertJsonPath('data.user.profile_avatar.min_messages', 10);
    }

    public function test_zero_threshold_is_reported_as_zero_and_unlocks_upload(): void
    {
        SiteSetting::current()->update(['profile_avatar_min_messages' => 0]);

        $user = User::factory()->create(['user_kind' => UserKind::Client]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.profile_avatar.min_messages', 0)
            ->assertJsonPath('data.user.profile_avatar.can_change', true);
    }

    public function test_undecodable_upload_is_a_422_and_keeps_the_previous_avatar(): void
    {
        [$user, $token, $oldPath] = $this->userWithExistingAvatar();

        // Valid PNG header (so it passes the `image` rule and getimagesize),
        // corrupt pixel data (so GD cannot decode it).
        $file = UploadedFile::fake()->createWithContent('avatar.png', self::pngHeader(64, 64).'garbage');

        $this->withToken($token)
            ->postJson('/api/v1/me/avatar', ['avatar' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertSame($oldPath, $user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_decompression_bomb_is_rejected_before_decoding(): void
    {
        [$user, $token, $oldPath] = $this->userWithExistingAvatar();

        // A few hundred bytes declaring 20000×20000 px (~1.6 GB once decoded).
        $file = UploadedFile::fake()->createWithContent('avatar.png', self::pngHeader(20000, 20000).str_repeat("\0", 64));

        $this->withToken($token)
            ->postJson('/api/v1/me/avatar', ['avatar' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertSame($oldPath, $user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_successful_replacement_deletes_the_previous_avatar(): void
    {
        [$user, $token, $oldPath] = $this->userWithExistingAvatar();

        $this->withToken($token)
            ->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('avatar.jpg', 100, 100)])
            ->assertOk();

        $newPath = $user->fresh()->avatar_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    /**
     * @return array{0: User, 1: string, 2: string}
     */
    private function userWithExistingAvatar(): array
    {
        SiteSetting::current()->update(['profile_avatar_min_messages' => 0]);

        $oldPath = 'media/users/avatars/old.webp';
        Storage::disk('public')->put($oldPath, 'old-avatar');

        $user = User::factory()->create([
            'user_kind' => UserKind::Client,
            'avatar_path' => $oldPath,
        ]);

        return [$user, $user->createToken('test')->plainTextToken, $oldPath];
    }

    private static function pngHeader(int $width, int $height): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n"
            .pack('N', strlen($ihdr)).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
    }

    public function test_a_concurrent_upload_does_not_orphan_the_other_requests_file(): void
    {
        SiteSetting::current()->update(['profile_avatar_min_messages' => 0]);
        $dir = trim((string) config('media.directory'), '/').'/users/avatars';
        Storage::disk('public')->put("{$dir}/old.webp", 'old');
        $user = User::factory()->create(['user_kind' => UserKind::Client, 'avatar_path' => "{$dir}/old.webp"]);
        Sanctum::actingAs($user);

        // A parallel request swapped in its avatar after this request loaded
        // the user: the in-memory avatar_path is stale.
        Storage::disk('public')->put("{$dir}/concurrent.webp", 'concurrent');
        User::query()->whereKey($user->id)->update(['avatar_path' => "{$dir}/concurrent.webp"]);
        Storage::disk('public')->delete("{$dir}/old.webp");

        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg', 200, 200)])
            ->assertOk();

        $current = $user->fresh()->avatar_path;
        $this->assertSame([$current], Storage::disk('public')->allFiles());
    }
}
