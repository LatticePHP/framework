<?php

declare(strict_types=1);

namespace Lattice\Mail;

abstract class Mailable
{
    /** @var string[] */
    protected array $to = [];

    /** @var string[] */
    protected array $cc = [];

    /** @var string[] */
    protected array $bcc = [];

    protected string $subject = '';

    protected string $fromAddress = '';

    protected ?string $fromName = null;

    protected ?string $replyToAddress = null;

    protected string $htmlContent = '';

    protected string $textContent = '';

    /** @var array<int, array{path: string, name: ?string}> */
    protected array $attachments = [];

    abstract public function build(): void;

    public function to(string|array $address): static
    {
        $this->to = array_merge($this->to, (array) $address);
        return $this;
    }

    public function cc(string|array $address): static
    {
        $this->cc = array_merge($this->cc, (array) $address);
        return $this;
    }

    public function bcc(string|array $address): static
    {
        $this->bcc = array_merge($this->bcc, (array) $address);
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function from(string $address, ?string $name = null): static
    {
        $this->fromAddress = $address;
        $this->fromName = $name;
        return $this;
    }

    public function replyTo(string $address): static
    {
        $this->replyToAddress = $address;
        return $this;
    }

    public function attach(string $path, ?string $name = null): static
    {
        $this->attachments[] = ['path' => $path, 'name' => $name];
        return $this;
    }

    public function html(string $content): static
    {
        $this->htmlContent = $content;
        return $this;
    }

    public function text(string $content): static
    {
        $this->textContent = $content;
        return $this;
    }

    /** @return string[] */
    public function getTo(): array
    {
        return $this->to;
    }

    /** @return string[] */
    public function getCc(): array
    {
        return $this->cc;
    }

    /** @return string[] */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /** @return array{address: string, name: ?string} */
    public function getFrom(): array
    {
        return ['address' => $this->fromAddress, 'name' => $this->fromName];
    }

    public function getReplyTo(): ?string
    {
        return $this->replyToAddress;
    }

    public function getHtml(): string
    {
        return $this->htmlContent;
    }

    public function getText(): string
    {
        return $this->textContent;
    }

    /** @return array<int, array{path: string, name: ?string}> */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
