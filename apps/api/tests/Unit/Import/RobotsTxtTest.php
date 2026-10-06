<?php

namespace Tests\Unit\Import;

use App\Support\Import\RobotsTxt;
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
        $robots = "User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nDisallow: /private\nDisallow:\n\nUser-agent: zdravje360-directoryimport\nDisallow: /";

        $this->assertFalse(RobotsTxt::allows($robots, self::AGENT, '/mk/record/121/962'));
        $this->assertTrue(RobotsTxt::allows("User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nDisallow: /private", self::AGENT, '/XML/a.xml'));
        $this->assertFalse(RobotsTxt::allows("User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nDisallow: /private", self::AGENT, '/private/a'));
    }

    public function test_wildcards_and_end_anchors(): void
    {
        $this->assertFalse(RobotsTxt::allows("User-agent: *\nDisallow: /*.xml", self::AGENT, '/XML/LekariLista_SitePzz.xml'));
        $this->assertFalse(RobotsTxt::allows("User-agent: *\nDisallow: /*.pdf$", self::AGENT, '/upload/records/962/x.pdf'));
        $this->assertTrue(RobotsTxt::allows("User-agent: *\nDisallow: /*.pdf$", self::AGENT, '/upload/records/962/x.pdf.html'));
        $this->assertTrue(RobotsTxt::allows("User-agent: *\nDisallow: /XML$", self::AGENT, '/XML/a.xml'));
        // The more specific Allow (longer pattern) wins over the Disallow.
        $this->assertTrue(RobotsTxt::allows("User-agent: *\nDisallow: /upload/\nAllow: /upload/*.pdf", self::AGENT, '/upload/records/x.pdf'));
        $this->assertFalse(RobotsTxt::allows("User-agent: *\nDisallow: /upload/\nAllow: /upload/*.pdf", self::AGENT, '/upload/records/x.doc'));
    }
}
