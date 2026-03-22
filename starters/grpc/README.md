# LatticePHP gRPC Starter

A starter application for building gRPC services with LatticePHP.

## Getting Started

1. Install dependencies:
   ```bash
   composer install
   ```

2. Generate PHP classes from proto files:
   ```bash
   protoc --php_out=app/Proto --grpc_out=app/Proto \
     --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
     proto/greeter.proto
   ```

3. Run the application:
   ```bash
   php bootstrap/app.php
   ```

## Structure

- `proto/` - Protocol Buffer definitions
- `app/Services/` - gRPC service implementations
- `app/AppModule.php` - Root application module with gRPC transport

## Example Service

The **GreeterService** implements two RPC methods:

- `SayHello` - Unary RPC that returns a greeting
- `SayHelloStream` - Server streaming RPC that returns multiple greetings

## Proto Definition

See `proto/greeter.proto` for the service and message definitions.
