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
}
