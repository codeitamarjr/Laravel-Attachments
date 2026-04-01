# Laravel Attachments

[![Latest Version on Packagist](https://img.shields.io/packagist/v/codeitamarjr/laravel-attachments.svg)](https://packagist.org/packages/codeitamarjr/laravel-attachments)
[![Tests](https://github.com/codeitamarjr/Laravel-Attachments/actions/workflows/tests.yml/badge.svg)](https://github.com/codeitamarjr/Laravel-Attachments/actions/workflows/tests.yml)
[![License](https://img.shields.io/github/license/codeitamarjr/Laravel-Attachments)](LICENSE)

`codeitamarjr/laravel-attachments` adds a small attachment layer on top of Laravel's filesystem and Eloquent.

It gives you:

- A polymorphic `attachments` table for any Eloquent model
- A `HasAttachments` trait with relationship helpers
- An `AttachmentService` for storing, replacing, and deleting files
- Public/private visibility handling with URL abstraction
- Configurable storage disk and base directory

The package works with any Laravel filesystem disk. If your application uses S3, R2, or another adapter, install and configure that adapter in the host app as usual.

## Why This Package Exists

In many Laravel applications, file uploads end up being handled piecemeal:

- store the file in one place
- save metadata somewhere else
- manually wire the file back to a model
- remember to clean up storage when the model or file is replaced

This package exists to make files feel like a first-class part of your Eloquent models.
It gives you a reusable way to attach files to any model, persist their metadata, resolve URLs, replace them safely, and delete them cleanly without rebuilding that workflow in every project.

Useful for invoices, users, posts, products, businesses, and any other model that needs persisted file attachments.

## Quick Start

```bash
composer require codeitamarjr/laravel-attachments
php artisan vendor:publish --tag=attachments-migrations
php artisan migrate
```

```php
class Invoice extends Model implements Attachable
{
    use HasAttachments;
}

$attachments->store($invoice, $file, 'document', auth()->id());
```

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Upgrading](#upgrading)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
- [Storing Files](#storing-files)
- [Replacing Files](#replacing-files)
- [Deleting Files](#deleting-files)
- [Attachment Model](#attachment-model)
- [Testing](#testing)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [License](#license)

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13

## Installation

Install the package via Composer:

```bash
composer require codeitamarjr/laravel-attachments
```

Laravel will auto-discover the service provider.

Publish the configuration file if you want to override the defaults:

```bash
php artisan vendor:publish --tag=attachments-config
```

Publish the migration:

```bash
php artisan vendor:publish --tag=attachments-migrations
```

Run the migration:

```bash
php artisan migrate
```

## Upgrading

If you are upgrading from an older release:

1. Publish the latest package migrations:

```bash
php artisan vendor:publish --tag=attachments-migrations
```

2. Run your migrations:

```bash
php artisan migrate
```

The package now publishes a single upgrade migration stub, `update_attachments_table.php`, which:

- adds the `visibility` column for legacy installations
- removes the hard foreign key assumption on `uploaded_by`

That makes the package friendlier to applications with custom auth schemas.

## Configuration

The published `config/attachments.php` file exposes:

- `disk`: the filesystem disk used to store uploaded files
- `visibility`: the default visibility for stored attachments
- `uploader_model`: model used by the `uploader()` relationship
- `uploader_foreign_key`: attachments column used for the uploader relationship
- `directory`: the base directory inside that disk
- `private_url_ttl`: how long private temporary URLs should remain valid

By default the package reads:

```env
ATTACHMENTS_DISK=public
ATTACHMENTS_VISIBILITY=public
ATTACHMENTS_UPLOADER_MODEL="App\\Models\\User"
ATTACHMENTS_UPLOADER_FOREIGN_KEY=uploaded_by
ATTACHMENTS_DIRECTORY=attachments
ATTACHMENTS_PRIVATE_URL_TTL=5
```

`ATTACHMENTS_VISIBILITY` is the default visibility only. You can still store one attachment as `public` and another as `private` by passing the visibility explicitly when storing the file.

Example custom uploader configuration:

```php
'uploader_model' => App\Models\Admin::class,
'uploader_foreign_key' => 'uploaded_by',
```

## Basic Usage

Add the `HasAttachments` trait to any model that should own files:

```php
<?php

namespace App\Models;

use CodeItamarJr\Attachments\Contracts\Attachable;
use CodeItamarJr\Attachments\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Invoice extends Model implements Attachable
{
    use HasAttachments;

    public function documentAttachment(): MorphOne
    {
        return $this->attachment('document');
    }

    public function getDocumentUrlAttribute(): ?string
    {
        return $this->attachmentUrl('document');
    }
}
```

Any Eloquent model can use the package, such as `Invoice`, `User`, `Post`, `Product`, or `Business`.
Models that use the package should implement the `CodeItamarJr\Attachments\Contracts\Attachable` contract. The `HasAttachments` trait already provides the required methods.

The trait adds:

- `attachments()` for the full morph-many relationship
- `attachment($collection)` for a single collection entry
- `attachmentUrl($collection, $expiresAt = null)` for a resolved file URL

## Storing Files

Use `AttachmentService` to create a new attachment:

```php
use App\Models\Invoice;
use CodeItamarJr\Attachments\Services\AttachmentService;

public function storeInvoiceDocument(AttachmentService $attachments)
{
    $invoice = Invoice::findOrFail(request('invoice_id'));
    $file = request()->file('document');

    if (! $file) {
        return;
    }

    $attachments->store($invoice, $file, 'document', auth()->id());
}
```

Store a private attachment by overriding the default visibility:

```php
$attachments->store($invoice, $file, 'signed-copy', auth()->id(), 'private');
```

Store multiple named collections for the same model:

```php
$attachments->store($invoice, $documentFile, 'document', auth()->id());
$attachments->store($invoice, $receiptFile, 'receipt', auth()->id());
```

Stored files are organized using this pattern:

```text
{directory}/{model-name}/{model-id}/{collection}/{hashed-filename}
```

Example:

```text
attachments/invoice/15/document/8f9c0d....pdf
```

## Replacing Files

Replace the current file for a collection:

```php
$attachments->replace($invoice, $file, 'document', auth()->id());
```

This deletes the previous file in that collection before storing the new one.

## Deleting Files

Delete one collection:

```php
$attachments->delete($invoice, 'document');
```

Delete all attachments for a model:

```php
$attachments->delete($invoice, null);
```

Models using `HasAttachments` also clean up their stored files automatically when they are force-deleted.

## Attachment Model

Each attachment record stores:

- `collection`
- `disk`
- `path`
- `visibility`
- `filename`
- `mime_type`
- `size`
- `uploaded_by`

`uploaded_by` stores the uploader model's key, while `uploader()` resolves the related model instance using your package configuration.

The included `Attachment` model provides:

- `url()` which returns a normal URL for public files and a temporary URL for private files
- `temporaryUrl()` when you want to explicitly generate a signed temporary URL
- `isPublic()` and `isPrivate()` visibility helpers

## Notes

- The published migration stores `uploaded_by` as a nullable indexed column without assuming a specific users table schema.
- The `uploader()` relationship is configurable through `uploader_model` and `uploader_foreign_key`.
- Private attachments require a filesystem driver that supports Laravel temporary URLs. If the selected disk does not support them, the package throws a clear runtime exception when generating a private URL.
- If you use the `public` disk, remember to expose it in your application with Laravel's normal filesystem setup, such as `php artisan storage:link` when applicable.

## Testing

Run the package test suite from the package directory:

```bash
composer install
composer test
```

The package currently includes end-to-end coverage for:

- public and private uploads
- replacing and deleting files
- deleting all collections for a model
- soft delete vs force delete cleanup
- attachable contract enforcement
- uploader relation configuration
- legacy schema upgrade behavior

## Contributing

Contributions are welcome. Before opening a pull request:

```bash
composer install
composer test
```

Prefer small, focused changes with updated tests and README examples when public behavior changes.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for release-oriented package notes.

## License

MIT. Please see [LICENSE](LICENSE) for more information.
