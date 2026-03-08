<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ValidationService;
use PHPUnit\Framework\TestCase;

final class ValidationServiceTest extends TestCase
{
    public function testValidateRequiredReturnsExpectedErrors(): void
    {
        $service = new ValidationService();

        $errors = $service->validateRequired([
            'title' => ' ',
            'slug' => 'hello-world',
        ], [
            'title' => 'Titel',
            'slug' => 'Slug',
        ]);

        self::assertSame(['Titel is verplicht.'], $errors);
    }

    public function testValidateLengthReturnsErrorForTooLongInput(): void
    {
        $service = new ValidationService();

        $error = $service->validateLength('abcdef', 'Titel', 5);

        self::assertSame('Titel mag maximaal 5 tekens bevatten.', $error);
    }
}
