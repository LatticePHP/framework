<?php

declare(strict_types=1);

namespace Lattice\Contracts\Auth;

interface TokenPairInterface
{
    public function getAccessToken(): string;

    public function getRefreshToken(): string;

    public function getExpiresIn(): int;

    public function getTokenType(): string;
}
