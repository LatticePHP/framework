---
outline: deep
---

# File Storage

LatticePHP provides a file storage abstraction through the `lattice/filesystem` package with local and cloud drivers.

## Basic Usage

Use the `Storage` facade:

```php
use Lattice\Filesystem\Storage;

// Write a file
Storage::put('reports/monthly.csv', $csvContent);

// Read a file
$content = Storage::get('reports/monthly.csv');

// Check existence
if (Storage::exists('reports/monthly.csv')) { /* ... */ }

// Delete
Storage::delete('reports/monthly.csv');

// List files in a directory
$files = Storage::files('reports/');
```

## Drivers

| Driver | Class | Use Case |
|---|---|---|
| Local | `LocalFilesystem` | Local disk storage |
| InMemory | `InMemoryFilesystem` | Testing |

Configure in `config/filesystems.php`:

```php
return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
    ],
];
```

## FilesystemManager

For programmatic access with multiple disks:

```php
use Lattice\Filesystem\FilesystemManager;

$fs = new FilesystemManager($config);

// Use the default disk
$fs->put('file.txt', 'content');

// Use a specific disk
$fs->disk('uploads')->put('avatar.jpg', $imageData);
```

## Testing

Use `InMemoryFilesystem` in tests:

```php
use Lattice\Filesystem\InMemoryFilesystem;

$fs = new InMemoryFilesystem();
$fs->put('test.txt', 'hello');

$this->assertTrue($fs->exists('test.txt'));
$this->assertSame('hello', $fs->get('test.txt'));
```

## Next Steps

- [Configuration](configuration.md) -- filesystem environment variables
- [Mail](mail.md) -- file attachments in emails
