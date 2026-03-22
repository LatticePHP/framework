<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Unit;

use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Query;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Header;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Tests\Fixtures\UserController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;

final class ParameterBindingTest extends TestCase
{
    public function testBodyAttribute(): void
    {
        $method = new ReflectionMethod(UserController::class, 'create');
        $params = $method->getParameters();

        $dataParam = $this->findParameter($params, 'data');
        $attrs = $dataParam->getAttributes(Body::class);

        $this->assertCount(1, $attrs);
    }

    public function testQueryAttribute(): void
    {
        $method = new ReflectionMethod(UserController::class, 'index');
        $params = $method->getParameters();

        $limitParam = $this->findParameter($params, 'limit');
        $attrs = $limitParam->getAttributes(Query::class);

        $this->assertCount(1, $attrs);
        $query = $attrs[0]->newInstance();
        $this->assertSame('limit', $query->name);
    }

    public function testQueryAttributeWithoutName(): void
    {
        $query = new Query();
        $this->assertNull($query->name);
    }

    public function testParamAttribute(): void
    {
        $method = new ReflectionMethod(UserController::class, 'show');
        $params = $method->getParameters();

        $idParam = $this->findParameter($params, 'id');
        $attrs = $idParam->getAttributes(Param::class);

        $this->assertCount(1, $attrs);
        $param = $attrs[0]->newInstance();
        $this->assertSame('id', $param->name);
    }

    public function testParamAttributeWithoutName(): void
    {
        $param = new Param();
        $this->assertNull($param->name);
    }

    public function testHeaderAttribute(): void
    {
        $method = new ReflectionMethod(UserController::class, 'update');
        $params = $method->getParameters();

        $requestIdParam = $this->findParameter($params, 'requestId');
        $attrs = $requestIdParam->getAttributes(Header::class);

        $this->assertCount(1, $attrs);
        $header = $attrs[0]->newInstance();
        $this->assertSame('X-Request-Id', $header->name);
    }

    public function testCurrentUserAttribute(): void
    {
        $method = new ReflectionMethod(UserController::class, 'create');
        $params = $method->getParameters();

        $userParam = $this->findParameter($params, 'user');
        $attrs = $userParam->getAttributes(CurrentUser::class);

        $this->assertCount(1, $attrs);
    }

    public function testAllBindingAttributesTargetParameter(): void
    {
        $classes = [Body::class, Query::class, Param::class, Header::class, CurrentUser::class];

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(\Attribute::class);
            $this->assertCount(1, $attributes, "$class should have Attribute");
            $attr = $attributes[0]->newInstance();
            $this->assertSame(\Attribute::TARGET_PARAMETER, $attr->flags, "$class should target PARAMETER");
        }
    }

    /**
     * @param ReflectionParameter[] $params
     */
    private function findParameter(array $params, string $name): ReflectionParameter
    {
        foreach ($params as $param) {
            if ($param->getName() === $name) {
                return $param;
            }
        }
        $this->fail("Parameter '$name' not found");
    }
}
