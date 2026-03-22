<?php

declare(strict_types=1);

namespace Lattice\OAuth;

interface ClientInterface
{
    public function getId(): string;

    public function getSecret(): string;

    public function getName(): string;

    /** @return array<string> */
    public function getRedirectUris(): array;

    /** @return array<string> */
    public function getGrantTypes(): array;

    /** @return array<string> */
    public function getScopes(): array;
}
