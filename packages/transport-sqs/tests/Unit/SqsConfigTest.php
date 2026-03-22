<?php

declare(strict_types=1);

namespace Lattice\Transport\Sqs\Tests\Unit;

use Lattice\Transport\Sqs\SqsConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqsConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = new SqsConfig();

        $this->assertSame('us-east-1', $config->region);
        $this->assertSame('', $config->queueUrl);
        $this->assertNull($config->accessKey);
        $this->assertNull($config->secretKey);
        $this->assertNull($config->endpoint);
    }

    #[Test]
    public function customValues(): void
    {
        $config = new SqsConfig(
            region: 'eu-west-1',
            queueUrl: 'https://sqs.eu-west-1.amazonaws.com/123456789/my-queue',
            accessKey: 'AKIAIOSFODNN7EXAMPLE',
            secretKey: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        );

        $this->assertSame('eu-west-1', $config->region);
        $this->assertSame('https://sqs.eu-west-1.amazonaws.com/123456789/my-queue', $config->queueUrl);
        $this->assertSame('AKIAIOSFODNN7EXAMPLE', $config->accessKey);
        $this->assertSame('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', $config->secretKey);
    }

    #[Test]
    public function detectsFifoQueue(): void
    {
        $fifo = new SqsConfig(queueUrl: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue.fifo');
        $standard = new SqsConfig(queueUrl: 'https://sqs.us-east-1.amazonaws.com/123456789/my-queue');

        $this->assertTrue($fifo->isFifoQueue());
        $this->assertFalse($standard->isFifoQueue());
    }

    #[Test]
    public function detectsLocalStack(): void
    {
        $localstack = new SqsConfig(
            endpoint: 'http://localhost:4566',
        );
        $aws = new SqsConfig();

        $this->assertTrue($localstack->isLocalStack());
        $this->assertFalse($aws->isLocalStack());
    }

    #[Test]
    public function localStackConfig(): void
    {
        $config = new SqsConfig(
            region: 'us-east-1',
            queueUrl: 'http://localhost:4566/000000000000/test-queue',
            accessKey: 'test',
            secretKey: 'test',
            endpoint: 'http://localhost:4566',
        );

        $this->assertTrue($config->isLocalStack());
        $this->assertSame('http://localhost:4566', $config->endpoint);
    }
}
