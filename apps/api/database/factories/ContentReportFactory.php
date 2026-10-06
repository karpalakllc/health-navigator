<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\ContentReport;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<ContentReport>
 */
class ContentReportFactory extends Factory
{
    protected $model = ContentReport::class;

    public function definition(): array
    {
        return [
            'reportable_type' => Review::class,
            'reportable_id' => Review::factory()->approved(),
            'user_id' => User::factory(),
            'reason' => ReportReason::Spam,
            'note' => null,
            'status' => ReportStatus::Open,
        ];
    }

    public function about(Model $reportable): static
    {
        return $this->state(fn () => [
            'reportable_type' => $reportable::class,
            'reportable_id' => $reportable->getKey(),
        ]);
    }
}
