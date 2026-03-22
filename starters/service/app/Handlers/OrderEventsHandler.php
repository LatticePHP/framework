<?php

declare(strict_types=1);

namespace App\Handlers;

use Lattice\Microservices\Attributes\CommandPattern;
use Lattice\Microservices\Attributes\EventPattern;
use Lattice\Microservices\Attributes\MessageController;

#[MessageController]
final class OrderEventsHandler
{
    #[EventPattern('order.created')]
    public function handleOrderCreated(mixed $data): void
    {
        // Process new order event
        // e.g., send confirmation email, update inventory
    }

    #[EventPattern('order.paid')]
    public function handleOrderPaid(mixed $data): void
    {
        // Process payment confirmation
        // e.g., trigger fulfillment workflow
    }

    #[EventPattern('order.shipped')]
    public function handleOrderShipped(mixed $data): void
    {
        // Process shipping notification
        // e.g., send tracking email to customer
    }

    #[CommandPattern('order.cancel')]
    public function handleCancelOrder(mixed $data): void
    {
        // Process order cancellation command
        // e.g., refund payment, update status
    }
}
