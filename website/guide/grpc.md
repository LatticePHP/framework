---
outline: deep
---

# gRPC

LatticePHP provides gRPC support through the `lattice/grpc` package. Define services using PHP attributes that map to Protocol Buffer definitions.

## Defining Services

Annotate service classes with `#[GrpcService]` and methods with `#[GrpcMethod]`:

```php
<?php
declare(strict_types=1);

use Lattice\Grpc\Attributes\GrpcService;
use Lattice\Grpc\Attributes\GrpcMethod;

#[GrpcService(name: 'greeter.Greeter')]
final class GreeterService
{
    #[GrpcMethod]
    public function sayHello(array $request): array
    {
        return ['message' => 'Hello, ' . ($request['name'] ?? 'World') . '!'];
    }

    #[GrpcMethod]
    public function sayHelloStream(array $request): iterable
    {
        $count = $request['count'] ?? 3;
        for ($i = 0; $i < $count; $i++) {
            yield ['message' => "Hello #{$i}: " . ($request['name'] ?? 'World')];
        }
    }
}
```

## Proto Definition

Define your service contract in Protocol Buffers:

```protobuf
syntax = "proto3";
package greeter;

service Greeter {
    rpc SayHello (HelloRequest) returns (HelloReply);
    rpc SayHelloStream (HelloStreamRequest) returns (stream HelloReply);
}

message HelloRequest {
    string name = 1;
}

message HelloStreamRequest {
    string name = 1;
    int32 count = 2;
}

message HelloReply {
    string message = 1;
}
```

Generate PHP classes from proto:

```bash
protoc --php_out=app/Proto --grpc_out=app/Proto \
  --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
  proto/greeter.proto
```

## Bootstrap

Enable gRPC transport in your bootstrap:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withModules([AppModule::class])
    ->withGrpc()
    ->create();
```

## Module Registration

```php
#[Module(
    providers: [GreeterService::class],
)]
final class AppModule {}
```

## RPC Patterns

| Pattern | PHP Return Type | Proto Return |
|---|---|---|
| Unary | `array` | Single message |
| Server streaming | `iterable` (yield) | `stream Message` |

## Testing

Use `InMemoryGrpcTransport` for testing:

```php
use Lattice\Grpc\Testing\InMemoryGrpcTransport;

$transport = new InMemoryGrpcTransport();
$response = $transport->call('greeter.Greeter/SayHello', ['name' => 'Alice']);
$this->assertSame('Hello, Alice!', $response['message']);
```

## Next Steps

- [Starter Kits](starters.md) -- the gRPC starter template
- [Microservices](microservices.md) -- message-based patterns
- [Runtime](runtime.md) -- RoadRunner with gRPC workers
