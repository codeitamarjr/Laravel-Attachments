# QuickTapPay Attachments

Reusable polymorphic attachment handling for Laravel 11/12 projects.  
Provides a trait, service, migration stub, and configuration for storing files on any configured filesystem (including Cloudflare R2).

## Installation

1. **Require the package** (when using it as a standalone dependency):

   ```bash
   composer require quicktappay/attachments
   ```

   In this repository, the package is linked via a path repository under `packages/quicktappay/attachments`.

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
use QuickTapPay\Attachments\Traits\HasAttachments;

class Business extends Model
{
    use HasAttachments;
}
```

This adds the `attachments()` morph-many relation plus a helper `attachment($collection)` and `attachmentUrl($collection)`.

### Attachment Service

Inject the `AttachmentService` to store, replace, or delete attachments:

```php
use QuickTapPay\Attachments\Services\AttachmentService;

class BusinessController extends Controller
{
    public function update(AttachmentService $attachments)
    {
        $business = Business::findOrFail(1);
        $file = request()->file('logo');

        $attachments->replace($business, $file, 'logo', auth()->id());
    }
}
```

### Collections

Attachments can be grouped by collection name (default `default`). Call `attachment('logo')` or `attachmentUrl('documents')` to fetch specific collections.

### Deleting

When a model using `HasAttachments` is force-deleted, associated attachments are automatically removed from both storage and the database.  
You can also delete explicitly:

```php
app(AttachmentService::class)->delete($business, 'logo');
```

## Configuration

`config/attachments.php` exposes:

- `disk` – filesystem disk, defaults to `ATTACHMENTS_DISK` env or `FILESYSTEM_DISK`.
- `directory` – base directory on the disk (`attachments` by default).

## Testing

The package is compatible with `orchestra/testbench` for isolated package testing.  
To run tests (if added later):

```bash
cd packages/quicktappay/attachments
composer install
./vendor/bin/pest
```

## License

MIT © QuickTapPay
