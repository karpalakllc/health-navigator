<?php

namespace Tests\Unit;

use App\Support\Ux\UxSchema;
use App\Support\Ux\UxTargetDescriber;
use PHPUnit\Framework\TestCase;

/**
 * The admin page shows target keys in words: every word the API accepts has
 * one, and no word is described that the tracker never sends.
 */
class UxTargetDescriberTest extends TestCase
{
    public function test_every_accepted_context_and_element_has_words(): void
    {
        foreach (UxSchema::TARGET_CONTEXTS as $context) {
            $this->assertTrue(UxTargetDescriber::knows($context), "No words for context „{$context}“.");
        }

        foreach (UxSchema::targetElements() as $element) {
            $this->assertNotSame($element, explode(' → ', UxTargetDescriber::describe('page/'.$element))[1], "No words for element „{$element}“.");
        }
    }

    public function test_it_describes_no_context_the_tracker_never_sends(): void
    {
        foreach (['product-card', 'filters'] as $unused) {
            $this->assertFalse(UxTargetDescriber::knows($unused));
        }

        $this->assertStringStartsWith('Картичка на лекар → наслов', UxTargetDescriber::describe('doctor-card/heading'));
    }
}
