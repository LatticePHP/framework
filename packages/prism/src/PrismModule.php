<?php

declare(strict_types=1);

namespace Lattice\Prism;

use Lattice\Module\Attribute\Module;
use Lattice\Prism\Api\IngestAction;
use Lattice\Prism\Api\IssueDetailAction;
use Lattice\Prism\Api\IssueListAction;
use Lattice\Prism\Api\IssueResolveAction;
use Lattice\Prism\Api\ProjectListAction;
use Lattice\Prism\Api\StatsAction;
use Lattice\Prism\Auth\ApiKeyAuthenticator;
use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Fingerprint\Fingerprinter;
use Lattice\Prism\Storage\StorageInterface;

#[Module(
    providers: [
        PrismServiceProvider::class,
    ],
    controllers: [
        IngestAction::class,
        ProjectListAction::class,
        IssueListAction::class,
        IssueDetailAction::class,
        IssueResolveAction::class,
        StatsAction::class,
    ],
    exports: [
        StorageInterface::class,
        IssueRepository::class,
        Fingerprinter::class,
        ApiKeyAuthenticator::class,
    ],
)]
final class PrismModule
{
}
