<?php

namespace Tests\Feature\Triage\Concerns;

use App\Models\TriageFlow;
use App\Models\TriageFlowReview;
use App\Services\Triage\V2\FlowImporter;
use App\Services\Triage\V2\FlowPublication;

trait PublishesGuidanceFlows
{
    protected function useFixtureFlows(): void
    {
        config(['triage.flows_path' => base_path('tests/Fixtures/triage')]);
    }

    /** Imports the fixture flows and publishes the given ones with an approved review. */
    protected function publishFixtureFlows(string ...$keys): void
    {
        $this->useFixtureFlows();
        app(FlowImporter::class)->import();

        foreach ($keys as $key) {
            $version = TriageFlow::query()->where('key', $key)->firstOrFail()->versions()->firstOrFail();
            $publication = app(FlowPublication::class);
            $publication->recordReview($version, [
                'decision' => TriageFlowReview::DECISION_APPROVED,
                'reviewed_on' => '2026-10-07',
                'note' => 'Reviewed for the test.',
            ], null);
            $publication->publish($version->fresh(), null);
        }
    }
}
