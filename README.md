# Laravel Attachments

Reusable polymorphic attachment handling for Laravel 11/12 projects.  
Provides a trait, service, migration stub, and configuration for storing files on any configured filesystem (including Cloudflare R2).

Repository: https://github.com/codeitamarjr/laravel-attachments

## Installation

1. **Require the package** (when using it as a standalone dependency):

   ```bash
   composer require codeitamarjr/laravel-attachments
   ```

   When using locally (e.g. inside QuickTapPay) you can also reference it with a path repository entry.

2. **Publish assets (optional)**

   ```bash
   php artisan vendor:publish --tag=attachments-config
   php artisan vendor:publish --tag=attachments-migrations
   ```

   The config file allows you to set the target filesystem disk and base directory. The migration stub creates the `attachments` table.

3. **Run migrations**

   ```bash
   php artisan migrate
   ```

## Usage

### Model Trait

Use the `HasAttachments` trait on any Eloquent model that needs attachments:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use CodeItamarJr\Attachments\Traits\HasAttachments;

class User extends Model
{
    use HasAttachments;

    protected $appends = ['avatar_url'];

    public function avatarAttachment(): MorphOne
    {
        return $this->attachment('avatar');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatarAttachment()->first()?->url();
    }
}
```

This adds the `attachments()` morph-many relation plus a helper `attachment($collection)` and `attachmentUrl($collection)`.

### Attachment Service

Inject the `AttachmentService` to store, replace, or delete attachments:

```php
use CodeItamarJr\Attachments\Services\AttachmentService;

class ProfileController extends Controller
{
    public function update(AttachmentService $attachments)
    {
        $user = request()->user();
        $file = request()->file('avatar');

        if ($file) {
            $attachments->replace($user, $file, 'avatar', $user->getKey());
        }
    }
}
```

### Collections

Attachments can be grouped by collection name (default `default`). For example, use `attachment('avatar')` or `attachmentUrl('avatar')` to reference a user's profile photo.

### Deleting

When a model using `HasAttachments` is force-deleted, associated attachments are automatically removed from both storage and the database.  
You can also delete explicitly:

```php
app(AttachmentService::class)->delete($user, 'avatar');
```

## Configuration

`config/attachments.php` exposes:

- `disk` – filesystem disk, defaults to `ATTACHMENTS_DISK` env or `FILESYSTEM_DISK`.
- `directory` – base directory on the disk (`attachments` by default).

## Testing

The package is compatible with `orchestra/testbench` for isolated package testing.  
To run tests (if added later):

```bash
cd laravel-attachments
composer install
./vendor/bin/pest
```

## License

MIT © 2025 Itamar Junior
