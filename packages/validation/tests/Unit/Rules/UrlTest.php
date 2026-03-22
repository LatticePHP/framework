<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\UrlDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_valid_url_passes(): void
    {
        $dto = new UrlDto(value: 'https://example.com');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_invalid_url_fails(): void
    {
        $dto = new UrlDto(value: 'not-a-url');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('Url', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_empty_string_fails(): void
    {
        $dto = new UrlDto(value: '');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }

    #[Test]
    public function test_http_url_passes(): void
    {
        $dto = new UrlDto(value: 'http://example.com/path?query=1');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }
}
