<?php

declare(strict_types=1);

namespace Lattice\Transport\Kafka\Tests\Unit;

use Lattice\Transport\Kafka\KafkaConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KafkaConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = new KafkaConfig();

        $this->assertSame(['localhost:9092'], $config->brokers);
        $this->assertSame('default', $config->groupId);
        $this->assertSame('', $config->topic);
        $this->assertSame('plaintext', $config->securityProtocol);
    }

    #[Test]
    public function customValues(): void
    {
        $config = new KafkaConfig(
            brokers: ['kafka-1:9092', 'kafka-2:9092', 'kafka-3:9092'],
            groupId: 'order-service',
            topic: 'orders',
            securityProtocol: 'ssl',
        );

        $this->assertSame(['kafka-1:9092', 'kafka-2:9092', 'kafka-3:9092'], $config->brokers);
        $this->assertSame('order-service', $config->groupId);
        $this->assertSame('orders', $config->topic);
        $this->assertSame('ssl', $config->securityProtocol);
    }

    #[Test]
    public function brokerListReturnsCommaSeparated(): void
    {
        $config = new KafkaConfig(brokers: ['kafka-1:9092', 'kafka-2:9092']);

        $this->assertSame('kafka-1:9092,kafka-2:9092', $config->getBrokerList());
    }

    #[Test]
    public function brokerListWithSingleBroker(): void
    {
        $config = new KafkaConfig(brokers: ['localhost:9092']);

        $this->assertSame('localhost:9092', $config->getBrokerList());
    }

    #[Test]
    public function isSecureWithPlaintext(): void
    {
        $config = new KafkaConfig(securityProtocol: 'plaintext');

        $this->assertFalse($config->isSecure());
    }

    #[Test]
    public function isSecureWithSsl(): void
    {
        $config = new KafkaConfig(securityProtocol: 'ssl');

        $this->assertTrue($config->isSecure());
    }

    #[Test]
    public function isSecureWithSaslSsl(): void
    {
        $config = new KafkaConfig(securityProtocol: 'sasl_ssl');

        $this->assertTrue($config->isSecure());
    }

    #[Test]
    public function isSecureWithSaslPlaintext(): void
    {
        $config = new KafkaConfig(securityProtocol: 'sasl_plaintext');

        $this->assertTrue($config->isSecure());
    }
}
