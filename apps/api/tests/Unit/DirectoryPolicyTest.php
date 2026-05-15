<?php

namespace Tests\Unit;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_can_view_but_not_mutate_directory_records(): void
    {
        $moderator = User::factory()->moderator()->create();
        $doctor = Doctor::factory()->create();
        $facility = Facility::factory()->create();

        $this->assertTrue($moderator->can('viewAny', Doctor::class));
        $this->assertTrue($moderator->can('view', $doctor));
        $this->assertFalse($moderator->can('create', Doctor::class));
        $this->assertFalse($moderator->can('update', $doctor));
        $this->assertFalse($moderator->can('delete', $doctor));

        $this->assertTrue($moderator->can('view', $facility));
        $this->assertFalse($moderator->can('update', $facility));
    }

    public function test_admin_can_mutate_directory_records(): void
    {
        $admin = User::factory()->admin()->create();
        $doctor = Doctor::factory()->create();

        $this->assertTrue($admin->can('update', $doctor));
        $this->assertTrue($admin->can('create', Doctor::class));
    }

    public function test_moderator_can_still_moderate_reviews(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->assertTrue($moderator->can('viewAny', Review::class));
    }
}
