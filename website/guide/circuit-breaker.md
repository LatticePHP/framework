---
outline: deep
---

# Circuit Breaker

LatticePHP includes a circuit breaker pattern for protecting your application from cascading failures when calling external services.

## How It Works

The circuit breaker monitors calls to external services and trips (opens) when failures exceed a threshold. While open, calls fail immediately without attempting the real operation, giving the failing service time to recover.

```
Closed (normal)  ->  Open (failing fast)  ->  Half-Open (testing)  ->  Closed
     |                     |                        |
  failures > threshold   timeout expires      test call succeeds
```

## The `#[CircuitBreaker]` Attribute

Apply the attribute to any method that calls an external service:

```php
use Lattice\Core\CircuitBreaker\Attributes\CircuitBreakerAttribute;

final class PaymentGateway
{
    #[CircuitBreakerAttribute(service: 'stripe', fallback: 'fallbackCharge')]
    public function charge(float $amount, string $token): array
    {
        // Call Stripe API
        return $this->http->post('https://api.stripe.com/v1/charges', [
            'amount' => $amount,
            'source' => $token,
        ]);
    }

    public function fallbackCharge(float $amount, string $token): array
    {
        // Queue the charge for later retry
        $this->queue->dispatch(new RetryCharge($amount, $token));
        return ['status' => 'queued', 'message' => 'Payment will be retried'];
    }
}
```

When the circuit opens (too many failures to Stripe), calls to `charge()` skip the HTTP request entirely and call `fallbackCharge()` instead.

## Circuit States

| State | Behavior |
|---|---|
| **Closed** | Normal operation. Calls go through. Failures are counted. |
| **Open** | All calls fail immediately with `CircuitOpenException` or call the fallback method. |
| **Half-Open** | One test call is allowed through. If it succeeds, the circuit closes. If it fails, it re-opens. |

## Configuration

```php
use Lattice\Core\CircuitBreaker\CircuitBreaker;

$breaker = new CircuitBreaker(
    failureThreshold: 5,        // Open after 5 failures
    recoveryTimeout: 30,        // Try again after 30 seconds
    halfOpenMaxAttempts: 1,     // Allow 1 test call in half-open state
);
```

## Programmatic Usage

Without the attribute:

```php
$breaker = new CircuitBreaker(failureThreshold: 3, recoveryTimeout: 60);

try {
    $result = $breaker->call('payment-service', function () {
        return $this->http->post('https://api.stripe.com/v1/charges', [...]);
    });
} catch (CircuitOpenException $e) {
    // Circuit is open -- service is down, use fallback
    return $this->queueForRetry();
}
```

## Next Steps

- [HTTP Client](http-client.md) -- making external HTTP calls
- [Observability](observability.md) -- monitoring circuit breaker state
- [Queues & Jobs](queues.md) -- retry patterns for failed calls
