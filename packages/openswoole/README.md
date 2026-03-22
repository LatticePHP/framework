# lattice/openswoole

Experimental OpenSwoole integration for the LatticePHP framework.

> **Note:** This package is experimental and not yet functional. It serves as a skeleton for future OpenSwoole support.

## Status

This package is a placeholder. Attempting to instantiate `OpenSwooleWorker` will throw a `RuntimeException`.

## Configuration

The `OpenSwooleConfig` value object defines the intended configuration shape:

- `host` - Bind address (default: `0.0.0.0`)
- `port` - Listen port (default: `9501`)
- `workerNum` - Number of worker processes (default: `4`)
- `enableCoroutine` - Enable coroutine support (default: `true`)

## Installation

```bash
composer require lattice/openswoole
```
