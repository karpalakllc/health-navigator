<?php

namespace App\Policies;

use App\Policies\Concerns\AdminManagesDirectoryRecords;

class TriageFlowPolicy
{
    use AdminManagesDirectoryRecords;
}
