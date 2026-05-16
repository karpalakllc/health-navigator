<?php

namespace Tests\Unit;

use App\Support\OfficeHours;
use PHPUnit\Framework\TestCase;

class OfficeHoursTest extends TestCase
{
    public function test_converts_between_map_and_rows(): void
    {
        $map = [
            'Пон' => '08:00–14:00',
            'Вто' => '08:00–14:00',
        ];

        $rows = OfficeHours::toRows($map);

        $this->assertCount(2, $rows);
        $this->assertSame($map, OfficeHours::fromRows($rows));
    }
}
