<?php

namespace App\Observers;

use App\Models\TriageFlow;

class TriageFlowObserver
{
    public function saving(TriageFlow $flow): void
    {
        if (! $flow->is_published) {
            return;
        }

        TriageFlow::query()
            ->where('id', '!=', $flow->id)
            ->where('is_published', true)
            ->update(['is_published' => false]);
    }
}
