# LatticePHP Workflow Starter

A starter application demonstrating workflow orchestration with LatticePHP.

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

- `app/Workflows/` - Workflow definitions with `#[Workflow]` attributes
- `app/Activities/` - Activity implementations with `#[Activity]` attributes
- `app/Http/` - HTTP endpoints for starting and querying workflows

## Example Workflow

The **OrderFulfillmentWorkflow** demonstrates a two-step saga:

1. **PaymentActivity** - Processes payment and returns a transaction ID
2. **ShippingActivity** - Creates a shipment and returns tracking info

## API Endpoints

- `POST /workflows/order-fulfillment` - Start a new order fulfillment workflow
- `GET /workflows/order-fulfillment/{id}` - Query workflow status
- `POST /workflows/order-fulfillment/{id}/cancel` - Signal workflow cancellation
