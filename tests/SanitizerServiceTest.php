<?php

declare(strict_types=1);

namespace Tests;

use App\Services\SanitizerService;
use PHPUnit\Framework\TestCase;

final class SanitizerServiceTest extends TestCase
{
    public function testSanitizeTextCollapsesWhitespaceAndStripsTags(): void
    {
        $service = new SanitizerService();

        $input = "  <b>Hello</b>\n\tworld   ";
        $result = $service->sanitizeText($input);

        self::assertSame('Hello world', $result);
    }

    public function testSanitizeUrlAllowsRelativeAndAbsoluteUrls(): void
    {
        $service = new SanitizerService();

        self::assertSame('/dashboard', $service->sanitizeUrl('/dashboard'));
        self::assertSame('https://example.com/x', $service->sanitizeUrl('https://example.com/x'));
        self::assertSame('', $service->sanitizeUrl('javascript:alert(1)'));
    }
}
