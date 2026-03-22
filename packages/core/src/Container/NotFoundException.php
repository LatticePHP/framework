<?php

declare(strict_types=1);

namespace Lattice\Core\Container;

use Psr\Container\NotFoundExceptionInterface;

final class NotFoundException extends \RuntimeException implements NotFoundExceptionInterface {}
