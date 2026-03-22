<?php

declare(strict_types=1);

namespace Lattice\Mail\Transport;

use Lattice\Mail\Mailable;
use PHPUnit\Framework\Assert;

final class InMemoryTransport implements MailTransportInterface
{
    /** @var list<Mailable> */
    private array $sent = [];

    public function send(Mailable $mailable): void
    {
        $mailable->build();
        $this->sent[] = $mailable;
    }

    /** @return list<Mailable> */
    public function getSent(): array
    {
        return $this->sent;
    }

    public function assertSent(string $mailableClass): void
    {
        $found = false;
        foreach ($this->sent as $mailable) {
            if ($mailable instanceof $mailableClass) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Expected [{$mailableClass}] to have been sent, but it was not.");
    }

    public function assertNotSent(string $mailableClass): void
    {
        foreach ($this->sent as $mailable) {
            if ($mailable instanceof $mailableClass) {
                Assert::fail("Unexpected [{$mailableClass}] was sent.");
            }
        }

        Assert::assertTrue(true);
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, "Expected {$count} mailable(s) to be sent, got " . count($this->sent) . '.');
    }
}
