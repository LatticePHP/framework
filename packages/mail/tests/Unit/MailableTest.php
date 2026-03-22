<?php

declare(strict_types=1);

namespace Lattice\Mail\Tests\Unit;

use Lattice\Mail\Mailable;
use PHPUnit\Framework\TestCase;

final class MailableTest extends TestCase
{
    public function test_to_accepts_single_address(): void
    {
        $mailable = $this->createMailable();
        $mailable->to('user@example.com');

        $this->assertSame(['user@example.com'], $mailable->getTo());
    }

    public function test_to_accepts_array_of_addresses(): void
    {
        $mailable = $this->createMailable();
        $mailable->to(['a@example.com', 'b@example.com']);

        $this->assertSame(['a@example.com', 'b@example.com'], $mailable->getTo());
    }

    public function test_to_merges_multiple_calls(): void
    {
        $mailable = $this->createMailable();
        $mailable->to('a@example.com')->to('b@example.com');

        $this->assertSame(['a@example.com', 'b@example.com'], $mailable->getTo());
    }

    public function test_cc_and_bcc(): void
    {
        $mailable = $this->createMailable();
        $mailable->cc('cc@example.com')->bcc('bcc@example.com');

        $this->assertSame(['cc@example.com'], $mailable->getCc());
        $this->assertSame(['bcc@example.com'], $mailable->getBcc());
    }

    public function test_subject(): void
    {
        $mailable = $this->createMailable();
        $mailable->subject('Hello World');

        $this->assertSame('Hello World', $mailable->getSubject());
    }

    public function test_from_with_name(): void
    {
        $mailable = $this->createMailable();
        $mailable->from('noreply@example.com', 'App');

        $this->assertSame(['address' => 'noreply@example.com', 'name' => 'App'], $mailable->getFrom());
    }

    public function test_from_without_name(): void
    {
        $mailable = $this->createMailable();
        $mailable->from('noreply@example.com');

        $this->assertSame(['address' => 'noreply@example.com', 'name' => null], $mailable->getFrom());
    }

    public function test_reply_to(): void
    {
        $mailable = $this->createMailable();
        $mailable->replyTo('reply@example.com');

        $this->assertSame('reply@example.com', $mailable->getReplyTo());
    }

    public function test_reply_to_defaults_to_null(): void
    {
        $mailable = $this->createMailable();

        $this->assertNull($mailable->getReplyTo());
    }

    public function test_attach(): void
    {
        $mailable = $this->createMailable();
        $mailable->attach('/path/to/file.pdf', 'invoice.pdf');

        $this->assertSame([['path' => '/path/to/file.pdf', 'name' => 'invoice.pdf']], $mailable->getAttachments());
    }

    public function test_attach_without_name(): void
    {
        $mailable = $this->createMailable();
        $mailable->attach('/path/to/file.pdf');

        $this->assertSame([['path' => '/path/to/file.pdf', 'name' => null]], $mailable->getAttachments());
    }

    public function test_html_content(): void
    {
        $mailable = $this->createMailable();
        $mailable->html('<h1>Hello</h1>');

        $this->assertSame('<h1>Hello</h1>', $mailable->getHtml());
    }

    public function test_text_content(): void
    {
        $mailable = $this->createMailable();
        $mailable->text('Hello plain text');

        $this->assertSame('Hello plain text', $mailable->getText());
    }

    public function test_fluent_chaining(): void
    {
        $mailable = $this->createMailable();
        $result = $mailable
            ->to('user@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Test')
            ->from('from@example.com', 'Sender')
            ->replyTo('reply@example.com')
            ->html('<p>Body</p>')
            ->text('Body')
            ->attach('/file.pdf');

        $this->assertSame($mailable, $result);
        $this->assertSame(['user@example.com'], $mailable->getTo());
        $this->assertSame('Test', $mailable->getSubject());
    }

    public function test_defaults_are_empty(): void
    {
        $mailable = $this->createMailable();

        $this->assertSame([], $mailable->getTo());
        $this->assertSame([], $mailable->getCc());
        $this->assertSame([], $mailable->getBcc());
        $this->assertSame('', $mailable->getSubject());
        $this->assertSame(['address' => '', 'name' => null], $mailable->getFrom());
        $this->assertSame('', $mailable->getHtml());
        $this->assertSame('', $mailable->getText());
        $this->assertSame([], $mailable->getAttachments());
    }

    private function createMailable(): Mailable
    {
        return new class extends Mailable {
            public function build(): void
            {
                $this->subject('Built Subject')
                    ->html('<p>Built content</p>');
            }
        };
    }
}
