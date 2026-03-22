<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit;

use Lattice\DevTools\Template\TemplateEngine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TemplateEngineTest extends TestCase
{
    private TemplateEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new TemplateEngine();
    }

    #[Test]
    public function it_replaces_single_placeholder(): void
    {
        $result = $this->engine->render('Hello, {{name}}!', ['name' => 'World']);
        $this->assertSame('Hello, World!', $result);
    }

    #[Test]
    public function it_replaces_multiple_placeholders(): void
    {
        $template = '<?php namespace {{namespace}}; class {{className}} {}';
        $result = $this->engine->render($template, [
            'namespace' => 'App\\Http',
            'className' => 'UserController',
        ]);
        $this->assertSame('<?php namespace App\\Http; class UserController {}', $result);
    }

    #[Test]
    public function it_leaves_unknown_placeholders_intact(): void
    {
        $result = $this->engine->render('{{known}} and {{unknown}}', ['known' => 'yes']);
        $this->assertSame('yes and {{unknown}}', $result);
    }

    #[Test]
    public function it_handles_empty_variables(): void
    {
        $result = $this->engine->render('No placeholders here', []);
        $this->assertSame('No placeholders here', $result);
    }

    #[Test]
    public function it_handles_repeated_placeholders(): void
    {
        $result = $this->engine->render('{{x}} + {{x}} = 2{{x}}', ['x' => '1']);
        $this->assertSame('1 + 1 = 21', $result);
    }
}
