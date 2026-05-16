<?php

namespace Tests\Feature\Api\V1;

use Database\Seeders\ForumSeeder;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumSeededTopicTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_hydration_topic_is_reachable(): void
    {
        $this->seed([PlatformUserSeeder::class, ForumSeeder::class]);

        $this->getJson('/api/v1/forum/categories/ishrana-i-zhivot/topics/hidratacija-vo-letni-denovi')
            ->assertOk()
            ->assertJsonPath('data.topic.slug', 'hidratacija-vo-letni-denovi')
            ->assertJsonPath('data.topic.author.name', fn ($name) => is_string($name) && $name !== '');
    }
}
