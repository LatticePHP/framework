<?php

declare(strict_types=1);

namespace Lattice\Mail;

final class PendingMail
{
    /** @var string[] */
    private array $cc = [];

    /** @var string[] */
    private array $bcc = [];

    /**
     * @param string|string[] $to
     */
    public function __construct(
        private readonly MailManager $manager,
        private readonly string|array $to,
    ) {}

    /**
     * @param string|string[] $address
     */
    public function cc(string|array $address): static
    {
        $this->cc = array_merge($this->cc, (array) $address);
        return $this;
    }

    /**
     * @param string|string[] $address
     */
    public function bcc(string|array $address): static
    {
        $this->bcc = array_merge($this->bcc, (array) $address);
        return $this;
    }

    public function send(Mailable $mailable): void
    {
        $mailable->to($this->to);

        if ($this->cc !== []) {
            $mailable->cc($this->cc);
        }

        if ($this->bcc !== []) {
            $mailable->bcc($this->bcc);
        }

        $this->manager->send($mailable);
    }

    public function queue(Mailable $mailable, ?string $queue = null): void
    {
        $mailable->to($this->to);

        if ($this->cc !== []) {
            $mailable->cc($this->cc);
        }

        if ($this->bcc !== []) {
            $mailable->bcc($this->bcc);
        }

        $this->manager->queue($mailable, $queue);
    }
}
