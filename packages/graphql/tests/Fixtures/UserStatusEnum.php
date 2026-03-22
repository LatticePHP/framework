<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Fixtures;

use Lattice\GraphQL\Attributes\EnumType;

#[EnumType(name: 'UserStatus', description: 'The status of a user account')]
enum UserStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';
    case Pending = 'pending';
}
