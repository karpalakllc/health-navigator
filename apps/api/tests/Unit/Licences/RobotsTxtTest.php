<?php

namespace Tests\Unit\Licences;

use App\Support\Licences\RobotsTxt;
use PHPUnit\Framework\TestCase;

class RobotsTxtTest extends TestCase
{
    private const AGENT = 'Zdravje360-DirectoryImport';

    public function test_an_empty_disallow_allows_everything(): void
    {
        $this->assertTrue(RobotsTxt::allows("User-agent: *\nDisallow:\nSitemap: https://example.test/sitemap.xml", self::AGENT, '/upload/records/962/a.pdf'));
        $this->assertTrue(RobotsTxt::allows('', self::AGENT, '/anything'));
    }

    public function test_disallow_prefixes_apply_and_the_longest_rule_wins(): void
    {
        $robots = "User-agent: *\nDisallow: /upload/\nAllow: /upload/records/";

        $this->assertFalse(RobotsTxt::allows($robots, self::AGENT, '/upload/documents/x.pdf'));
        $this->assertTrue(RobotsTxt::allows($robots, self::AGENT, '/upload/records/962/x.pdf'));
        $this->assertTrue(RobotsTxt::allows($robots, self::AGENT, '/mk/record/121/962'));
    }

    public function test_a_group_naming_our_agent_overrides_the_star_group(): void
    {
        $robots = "User-agent: *\nDisallow:\n\nUser-agent: zdravje360-directoryimport\nDisallow: /";

        $this->assertFalse(RobotsTxt::allows($robots, self::AGENT, '/mk/record/121/962'));
    }

    public function test_wildcards_are_read_conservatively(): void
    {
        $this->assertFalse(RobotsTxt::allows("User-agent: *\nDisallow: /*.pdf$", self::AGENT, '/upload/records/962/x.pdf'));
        $this->assertFalse(RobotsTxt::allows("User-agent: *\nDisallow: /upload/\nAllow: /upload/*.pdf", self::AGENT, '/upload/records/x.pdf'));
    }
}
