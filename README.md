# Laravel Attachments

`codeitamarjr/laravel-attachments` adds a small attachment layer on top of Laravel's filesystem and Eloquent.

It gives you:

- A polymorphic `attachments` table for any Eloquent model
- A `HasAttachments` trait with relationship helpers
- An `AttachmentService` for storing, replacing, and deleting files
- Public/private visibility handling with URL abstraction
- Configurable storage disk and base directory

The package works with any Laravel filesystem disk. If your application uses S3, R2, or another adapter, install and configure that adapter in the host app as usual.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

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

## Configuration

The published `config/attachments.php` file exposes:

- `disk`: the filesystem disk used to store uploaded files
- `visibility`: the default visibility for stored attachments
- `directory`: the base directory inside that disk
- `private_url_ttl`: how long private temporary URLs should remain valid

By default the package reads:

```env
ATTACHMENTS_DISK=public
ATTACHMENTS_VISIBILITY=public
ATTACHMENTS_DIRECTORY=attachments
ATTACHMENTS_PRIVATE_URL_TTL=5
```

## Basic Usage

Add the `HasAttachments` trait to any model that should own files:

```php
<?php

namespace App\Models;

use CodeItamarJr\Attachments\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class User extends Model
{
    use HasAttachments;

    public function avatarAttachment(): MorphOne
    {
        return $this->attachment('avatar');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->attachmentUrl('avatar');
    }
}
```

The trait adds:

- `attachments()` for the full morph-many relationship
- `attachment($collection)` for a single collection entry
- `attachmentUrl($collection, $expiresAt = null)` for a resolved file URL

## Storing Files

Use `AttachmentService` to create a new attachment:

```php
use CodeItamarJr\Attachments\Services\AttachmentService;

public function storeAvatar(AttachmentService $attachments)
{
    $user = request()->user();
    $file = request()->file('avatar');

    if (! $file) {
        return;
    }

    $attachments->store($user, $file, 'avatar', $user->getKey());
}
```

Store a private attachment by overriding the default visibility:

```php
$attachments->store($user, $file, 'passport', $user->getKey(), 'private');
```

Stored files are organized using this pattern:

```text
{directory}/{model-name}/{model-id}/{collection}/{hashed-filename}
```

Example:

```text
attachments/user/15/avatar/8f9c0d....jpg
```

## Replacing Files

Replace the current file for a collection:

```php
$attachments->replace($user, $file, 'avatar', $user->getKey());
```

This deletes the previous file in that collection before storing the new one.

## Deleting Files

Delete one collection:

```php
$attachments->delete($user, 'avatar');
```

Delete all attachments for a model:

```php
$attachments->delete($user, null);
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

The included `Attachment` model provides:

- `url()` which returns a normal URL for public files and a temporary URL for private files
- `temporaryUrl()` when you want to explicitly generate a signed temporary URL
- `isPublic()` and `isPrivate()` visibility helpers

## Notes

- The published migration creates an `uploaded_by` foreign key that references the `users` table.
- Private attachments require a filesystem driver that supports Laravel temporary URLs.
- If you use the `public` disk, remember to expose it in your application with Laravel's normal filesystem setup, such as `php artisan storage:link` when applicable.

## Roadmap

The package is usable now, but it would benefit from package-level tests, CI, and a changelog before a broader public release.

## License

MIT. Please see [LICENSE](LICENSE) for more information.
