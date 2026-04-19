# Laravel Attachments

[![Latest Version on Packagist](https://img.shields.io/packagist/v/codeitamarjr/laravel-attachments.svg)](https://packagist.org/packages/codeitamarjr/laravel-attachments)
[![Tests](https://github.com/codeitamarjr/Laravel-Attachments/actions/workflows/tests.yml/badge.svg)](https://github.com/codeitamarjr/Laravel-Attachments/actions/workflows/tests.yml)
[![License](https://img.shields.io/github/license/codeitamarjr/Laravel-Attachments)](LICENSE)

`codeitamarjr/laravel-attachments` adds a small attachment layer/model on top of Laravel filesystem.

It gives you:

- A polymorphic `attachments` table for any Model
- A `HasAttachments` trait with explicit single-file and multi-file collection helpers
- An `AttachmentService` for storing, replacing, and deleting files
- Public/private visibility handling with URL abstraction

## Why This Package Exists

In many Laravel applications, user file uploads end up being handled:

- store the file in one place
- save metadata somewhere else
- manually wire the metadata back to a model
- remember to clean up storage when the model or file is replaced or deleted

This package creates the `attachments` table and the `Attachment` model, and it gives you a reusable way to attach files to any model and persist their metadata on the Attachment model, updating and deleting files being handled by the Trait and Service.

## Quick Start

```bash
composer require codeitamarjr/laravel-attachments
php artisan vendor:publish --tag=attachments-migrations
php artisan migrate
```

Sample usage:

```php
class Invoice extends Model implements Attachable
{
    use HasAttachments;
}

// Store a new file in the "document" collection for the invoice model, associating the uploader by their authenticated ID:
$attachments->store($invoice, $file, 'document', auth()->id());
```

## Contents

- [Laravel Attachments](#laravel-attachments)
  - [Why This Package Exists](#why-this-package-exists)
  - [Quick Start](#quick-start)
  - [Contents](#contents)
  - [Requirements](#requirements)
  - [Configuration](#configuration)
  - [Basic Usage](#basic-usage)
  - [Storing Files](#storing-files)
  - [Replacing Files](#replacing-files)
  - [Deleting Files](#deleting-files)
  - [Collection Semantics](#collection-semantics)
  - [Attachment Model](#attachment-model)
  - [Testing](#testing)
  - [Changelog](#changelog)
  - [License](#license)

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13

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
ATTACHMENTS_DISK=public // Optional, but defaults to public if not set. Make sure the selected disk is properly configured in config/filesystems.php and exposed in your application when applicable, Private attachments require a filesystem driver that supports Laravel temporary URLs.
ATTACHMENTS_VISIBILITY=public // Optional, but defaults to public if not set. Can be overridden per attachment when storing.
ATTACHMENTS_UPLOADER_MODEL="App\\Models\\User" // Optional, but defaults to User if not set
ATTACHMENTS_UPLOADER_FOREIGN_KEY=uploaded_by // Nullable by default, but required if you set an uploader model
ATTACHMENTS_DIRECTORY=attachments // Base directory for all attachments in the selected disk
ATTACHMENTS_PRIVATE_URL_TTL=5 // Minutes for the temporary URL to remain valid
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
        return $this->firstAttachmentUrl('document');
    }
}
```

Any Eloquent model can use the package, such as `Invoice`, `User`, `Post`, `Product` ...
Models that use the package should implement the `CodeItamarJr\Attachments\Contracts\Attachable` contract. The `HasAttachments` trait already provides the required methods.

The trait adds:

- `attachments()` for the full morph-many relationship
- `attachmentsFor($collection)` for all attachments in a collection
- `singleAttachment($collection)` for single-file collections by convention
- `attachment($collection)` for single-file collections by convention
- `firstAttachment($collection)` for the first attachment model in a collection
- `lastAttachment($collection)` for the last attachment model in a collection
- `attachmentAt($collection, $position)` for the Nth attachment model in a collection
- `firstAttachmentUrl($collection, $expiresAt = null)` for the first attachment URL in a collection
- `lastAttachmentUrl($collection, $expiresAt = null)` for the last attachment URL in a collection
- `attachmentUrlAt($collection, $position, $expiresAt = null)` for the Nth attachment URL in a collection

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

`store()` appends a new attachment to the selected collection. This makes multi-file collections a first-class feature.

If you do not want to associate the attachment with an uploader, you can omit the fourth argument:

```php
$attachments->store($invoice, $file, 'document');
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

Store multiple files in the same collection:

```php
$attachments->store($invoice, $scanA, 'supporting-documents', auth()->id());
$attachments->store($invoice, $scanB, 'supporting-documents', auth()->id());

$invoice->attachmentsFor('supporting-documents')->get();
$invoice->firstAttachment('supporting-documents');
$invoice->lastAttachment('supporting-documents');
$invoice->attachmentAt('supporting-documents', 2);
$invoice->firstAttachmentUrl('supporting-documents');
$invoice->lastAttachmentUrl('supporting-documents');
$invoice->attachmentUrlAt('supporting-documents', 2);
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

`replace()` replaces the whole target collection. Any existing attachments in that collection are deleted before the new file is stored.

Replace a single attachment inside a multi-file collection:

```php
$attachments->replaceById($invoice, $attachmentId, $file, auth()->id());
```

Use this when a collection contains multiple files, such as a gallery or supporting documents list, and only one specific attachment should be replaced.

## Deleting Files

Delete one collection:

```php
$attachments->delete($invoice, 'document');
```

Delete one attachment inside a multi-file collection:

```php
$attachments->deleteById($invoice, $attachmentId);
```

Delete all attachments for a model:

```php
$attachments->delete($invoice, null);
```

Models using `HasAttachments` also clean up their stored files automatically when they are force-deleted.

## Collection Semantics

Collections can be used in two ways:

- Single-file collections, such as `logo`, `avatar`, or `signed-copy`
- Multi-file collections, such as `documents`, `receipts`, or `gallery`

Recommended conventions:

- Use `store()` to append files to a collection
- Use `attachmentsFor()` when you want all files in a collection
- Use `singleAttachment()` or `attachment()` when the collection is meant to behave like a single-slot attachment
- Use `firstAttachment()`, `lastAttachment()`, or `attachmentAt()` when you need specific items from a multi-file collection
- Use `firstAttachmentUrl()`, `lastAttachmentUrl()`, or `attachmentUrlAt()` when you need specific URLs from a multi-file collection
- Use `replace()` when the collection should behave like a single-slot attachment and older files should be removed
- Use `replaceById()` when a multi-file collection should keep the rest of its files while replacing only one attachment
- Use `deleteById()` when a multi-file collection should keep the rest of its files while deleting only one attachment

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

## Testing

Run the package test suite from the package directory:

```bash
composer install
composer test
```

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for release-oriented package notes.

## License

MIT. Please see [LICENSE](LICENSE) for more information.
