<?php

declare(strict_types=1);

namespace Lattice\Auth\Http;

use Lattice\Auth\Hashing\HashManager;
use Lattice\Auth\Http\Dto\LoginDto;
use Lattice\Auth\Http\Dto\RefreshDto;
use Lattice\Auth\Http\Dto\RegisterDto;
use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Auth\Models\User;
use Lattice\Auth\Principal;
use Lattice\Contracts\Auth\TokenIssuerInterface;
use Lattice\Http\Response;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Post;

#[Controller('/api/auth')]
final class AuthController
{
    public function __construct(
        private readonly TokenIssuerInterface $issuer,
        private readonly HashManager $hasher,
        private readonly string $userModel = User::class,
    ) {}

    #[Post('/login')]
    public function login(#[Body] LoginDto $dto): Response
    {
        /** @var User|null $user */
        $user = ($this->userModel)::where('email', $dto->email)->first();

        if ($user === null || !$this->hasher->check($dto->password, $user->password)) {
            return Response::error('Invalid credentials', 401);
        }

        $principal = new Principal(
            id: (string) $user->id,
            type: 'user',
            roles: [$user->role ?? 'user'],
        );

        $tokenPair = $this->issuer->issueAccessToken($principal);

        return Response::json([
            'access_token' => $tokenPair->getAccessToken(),
            'refresh_token' => $tokenPair->getRefreshToken(),
            'token_type' => $tokenPair->getTokenType(),
            'expires_in' => $tokenPair->getExpiresIn(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    #[Post('/register')]
    public function register(#[Body] RegisterDto $dto): Response
    {
        /** @var User $user */
        $user = ($this->userModel)::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password, // Hashed by model mutator
            'role' => 'user',
        ]);

        $principal = new Principal(
            id: (string) $user->id,
            type: 'user',
            roles: ['user'],
        );

        $tokenPair = $this->issuer->issueAccessToken($principal);

        return Response::json([
            'access_token' => $tokenPair->getAccessToken(),
            'refresh_token' => $tokenPair->getRefreshToken(),
            'token_type' => $tokenPair->getTokenType(),
            'expires_in' => $tokenPair->getExpiresIn(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    #[Post('/refresh')]
    public function refresh(#[Body] RefreshDto $dto): Response
    {
        try {
            $tokenPair = $this->issuer->refreshAccessToken($dto->refresh_token);
        } catch (\Throwable) {
            return Response::error('Invalid refresh token', 401);
        }

        return Response::json([
            'access_token' => $tokenPair->getAccessToken(),
            'refresh_token' => $tokenPair->getRefreshToken(),
            'token_type' => $tokenPair->getTokenType(),
            'expires_in' => $tokenPair->getExpiresIn(),
        ]);
    }

    #[Get('/me')]
    #[UseGuards(guards: [JwtAuthenticationGuard::class])]
    public function me(#[CurrentUser] Principal $user): Response
    {
        /** @var User|null $dbUser */
        $dbUser = ($this->userModel)::find($user->getId());

        if ($dbUser === null) {
            return Response::error('User not found', 404);
        }

        return Response::json([
            'data' => [
                'id' => $dbUser->id,
                'name' => $dbUser->name,
                'email' => $dbUser->email,
                'role' => $dbUser->role,
            ],
        ]);
    }
}
