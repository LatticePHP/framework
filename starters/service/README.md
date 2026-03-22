# LatticePHP Microservice Starter

A starter application for building event-driven microservices with LatticePHP.

## Getting Started

1. Install dependencies:
   ```bash
   composer install
   ```

2. Run the application:
   ```bash
   php bootstrap/app.php
   ```

## Structure

- `app/Handlers/` - Message handlers with `#[EventPattern]` and `#[CommandPattern]` attributes
- `app/Http/` - HTTP health/status endpoints

## Message Patterns

The **OrderEventsHandler** demonstrates event and command handling:

- `order.created` - Reacts to new order events
- `order.paid` - Reacts to payment confirmation events
- `order.shipped` - Reacts to shipping events
- `order.cancel` - Handles order cancellation commands

## API Endpoints

- `GET /health` - Health check
- `GET /status` - Service status and registered handlers
