<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures\Framework;

use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\Request;
use Lattice\Http\Resource;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;
use Lattice\Routing\Attributes\Query;

#[Controller('/api/test')]
final class TestController
{
    public function __construct(
        private readonly TestService $service,
    ) {}

    // Unprotected health check
    #[Get('/health')]
    public function health(): array
    {
        return ['status' => 'ok'];
    }

    // Protected route — requires valid bearer token
    #[Get('/protected')]
    #[UseGuards(guards: [TestAuthGuard::class])]
    public function protected(#[CurrentUser] PrincipalInterface $user): array
    {
        return ['user_id' => $user->getId()];
    }

    // With constructor-based body DTO
    #[Post('/contacts')]
    public function create(#[Body] CreateTestContactDto $dto): Response
    {
        $contact = TestContact::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'status' => $dto->status,
        ]);

        return ResponseFactory::created(
            TestContactResource::make($contact)->toArray()
        );
    }

    // With property-based body DTO (the DtoMapper bug test)
    #[Post('/contacts-prop')]
    public function createProp(#[Body] PropertyBasedDto $dto): array
    {
        return ['name' => $dto->name, 'email' => $dto->email];
    }

    // With path param (colon-style)
    #[Get('/contacts/:id')]
    public function show(#[Param] int $id): array
    {
        $contact = TestContact::findOrFail($id);
        return TestContactResource::make($contact)->toArray();
    }

    // With query params — auto-inject Request
    #[Get('/contacts')]
    public function index(Request $request): array
    {
        $status = $request->getQuery('status');
        $query = TestContact::query();
        if ($status !== null) {
            $query->where('status', $status);
        }
        $contacts = $query->get();

        return TestContactResource::collection($contacts);
    }

    // Validation error route
    #[Post('/validate')]
    public function validate(#[Body] CreateTestContactDto $dto): array
    {
        return ['ok' => true];
    }

    // Exception route
    #[Get('/error')]
    public function error(): never
    {
        throw new \RuntimeException('Test error');
    }

    // Delete with void return (204)
    #[Delete('/contacts/:id')]
    public function delete(#[Param] int $id): void
    {
        TestContact::findOrFail($id)->delete();
    }

    // Constructor injection test
    #[Get('/greet/:name')]
    public function greet(#[Param] string $name): array
    {
        return ['greeting' => $this->service->greet($name)];
    }

    // String path param test
    #[Get('/contacts/by-email/:email')]
    public function findByEmail(#[Param] string $email): array
    {
        return ['email' => $email];
    }
}
