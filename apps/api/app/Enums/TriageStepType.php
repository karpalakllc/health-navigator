<?php

namespace App\Enums;

enum TriageStepType: string
{
    case SingleSelect = 'single_select';
    case MultiSelect = 'multi_select';
}
