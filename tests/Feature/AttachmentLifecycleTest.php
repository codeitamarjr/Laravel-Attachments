<?php

namespace CodeItamarJr\Attachments\Tests\Feature;

use Carbon\CarbonImmutable;
use CodeItamarJr\Attachments\Models\Attachment;
use CodeItamarJr\Attachments\Services\AttachmentService;
use CodeItamarJr\Attachments\Tests\Fixtures\PlainModel;
use CodeItamarJr\Attachments\Tests\Fixtures\TestUser;
use CodeItamarJr\Attachments\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class AttachmentLifecycleTest extends TestCase
{
    public function test_it_stores_a_public_attachment_and_returns_a_public_url(): void
    {
        $user = TestUser::create(['name' => 'Public User']);
        $file = UploadedFile::fake()->image('avatar.jpg');

        $attachment = app(AttachmentService::class)->store($user, $file, 'avatar', $user->id);

        $this->assertSame('public', $attachment->visibility);
        $this->assertDatabaseHas('attachments', [
            'id' => $attachment->id,
            'attachable_id' => $user->id,
            'attachable_type' => TestUser::class,
            'collection' => 'avatar',
            'visibility' => 'public',
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('attachments-public')->assertExists($attachment->path);
        $this->assertSame(
            'https://example.test/storage/public/'.$attachment->path,
            $attachment->url()
        );
        $this->assertSame($attachment->url(), $user->attachmentUrl('avatar'));
    }

    public function test_it_stores_a_private_attachment_and_returns_a_temporary_url(): void
    {
        config()->set('attachments.disk', 'attachments-private');
        config()->set('attachments.visibility', 'private');

        $user = TestUser::create(['name' => 'Private User']);
        $file = UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf');
        $expiresAt = CarbonImmutable::parse('2026-01-01 12:00:00');

        $attachment = app(AttachmentService::class)->store($user, $file, 'passport', $user->id);

        $this->assertTrue($attachment->isPrivate());
        Storage::disk('attachments-private')->assertExists($attachment->path);
        $this->assertSame(
            'https://example.test/temp/'.$attachment->path.'?expires='.$expiresAt->getTimestamp(),
            $attachment->url($expiresAt)
        );
        $this->assertSame($attachment->url($expiresAt), $user->attachmentUrl('passport', $expiresAt));
    }

    public function test_replace_removes_the_previous_file_and_keeps_a_single_record(): void
    {
        $user = TestUser::create(['name' => 'Replace User']);
        $service = app(AttachmentService::class);

        $original = $service->store($user, UploadedFile::fake()->image('old.jpg'), 'avatar', $user->id);
        $replacement = $service->replace($user, UploadedFile::fake()->image('new.jpg'), 'avatar', $user->id);

        Storage::disk('attachments-public')->assertMissing($original->path);
        Storage::disk('attachments-public')->assertExists($replacement->path);
        $this->assertDatabaseMissing('attachments', ['id' => $original->id]);
        $this->assertDatabaseHas('attachments', ['id' => $replacement->id]);
        $this->assertSame(1, Attachment::query()->where('attachable_id', $user->id)->count());
    }

    public function test_delete_removes_the_file_and_database_record(): void
    {
        $user = TestUser::create(['name' => 'Delete User']);
        $service = app(AttachmentService::class);

        $attachment = $service->store($user, UploadedFile::fake()->image('avatar.jpg'), 'avatar', $user->id);

        $service->delete($user, 'avatar');

        Storage::disk('attachments-public')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_delete_with_null_collection_removes_all_attachments_for_a_model(): void
    {
        $user = TestUser::create(['name' => 'Delete All User']);
        $service = app(AttachmentService::class);

        $avatar = $service->store($user, UploadedFile::fake()->image('avatar.jpg'), 'avatar', $user->id);
        $passport = $service->store($user, UploadedFile::fake()->create('passport.pdf', 10), 'passport', $user->id);

        $service->delete($user, null);

        Storage::disk('attachments-public')->assertMissing($avatar->path);
        Storage::disk('attachments-public')->assertMissing($passport->path);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_soft_delete_keeps_attachments_but_force_delete_removes_them(): void
    {
        $user = TestUser::create(['name' => 'Soft Delete User']);
        $service = app(AttachmentService::class);
        $attachment = $service->store($user, UploadedFile::fake()->image('avatar.jpg'), 'avatar', $user->id);

        $user->delete();

        Storage::disk('attachments-public')->assertExists($attachment->path);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);

        $user->forceDelete();

        Storage::disk('attachments-public')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_invalid_visibility_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = TestUser::create(['name' => 'Invalid Visibility User']);

        app(AttachmentService::class)->store(
            $user,
            UploadedFile::fake()->image('avatar.jpg'),
            'avatar',
            $user->id,
            'internal'
        );
    }

    public function test_non_attachable_models_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $model = PlainModel::create(['name' => 'Plain Model']);

        app(AttachmentService::class)->store(
            $model,
            UploadedFile::fake()->image('avatar.jpg')
        );
    }

    public function test_uploader_relationship_uses_package_configuration(): void
    {
        config()->set('attachments.uploader_model', TestUser::class);
        config()->set('attachments.uploader_foreign_key', 'uploaded_by');

        $user = TestUser::create(['name' => 'Uploader User']);
        $attachment = app(AttachmentService::class)->store(
            $user,
            UploadedFile::fake()->image('avatar.jpg'),
            'avatar',
            $user->id
        );

        $this->assertSame($user->id, $attachment->uploader?->getKey());
        $this->assertSame(TestUser::class, $attachment->uploader()->getRelated()::class);
    }

    public function test_it_allows_null_uploaded_by_values(): void
    {
        $user = TestUser::create(['name' => 'Anonymous Upload User']);

        $attachment = app(AttachmentService::class)->store(
            $user,
            UploadedFile::fake()->image('avatar.jpg'),
            'avatar'
        );

        $this->assertNull($attachment->uploaded_by);
        $this->assertDatabaseHas('attachments', [
            'id' => $attachment->id,
            'uploaded_by' => null,
        ]);
    }

    public function test_it_supports_multiple_collections_on_the_same_model(): void
    {
        $user = TestUser::create(['name' => 'Collection User']);
        $service = app(AttachmentService::class);

        $avatar = $service->store($user, UploadedFile::fake()->image('avatar.jpg'), 'avatar', $user->id);
        $cover = $service->store($user, UploadedFile::fake()->image('cover.jpg'), 'cover', $user->id);

        $this->assertSame($avatar->id, $user->attachment('avatar')->first()?->id);
        $this->assertSame($cover->id, $user->attachment('cover')->first()?->id);
        $this->assertCount(2, $user->attachments()->get());
    }

    public function test_attachment_url_works_with_eager_loaded_and_lazy_loaded_relations(): void
    {
        $user = TestUser::create(['name' => 'Relation User']);
        $attachment = app(AttachmentService::class)->store(
            $user,
            UploadedFile::fake()->image('avatar.jpg'),
            'avatar',
            $user->id
        );

        $lazyUser = TestUser::query()->findOrFail($user->id);
        $eagerUser = TestUser::query()->with('attachments')->findOrFail($user->id);

        $expectedUrl = 'https://example.test/storage/public/'.$attachment->path;

        $this->assertSame($expectedUrl, $lazyUser->attachmentUrl('avatar'));
        $this->assertSame($expectedUrl, $eagerUser->attachmentUrl('avatar'));
    }

    public function test_private_attachments_throw_a_clear_error_when_temporary_urls_are_unsupported(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support temporary URLs');

        config()->set('attachments.disk', 'attachments-public');
        config()->set('attachments.visibility', 'private');

        $user = TestUser::create(['name' => 'Unsupported Temp URL User']);
        $attachment = app(AttachmentService::class)->store(
            $user,
            UploadedFile::fake()->create('passport.pdf', 10),
            'passport',
            $user->id
        );

        $attachment->url(now()->addMinutes(5));
    }

    public function test_uploader_relationship_fails_clearly_when_misconfigured(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Attachment uploader model is not configured.');

        config()->set('attachments.uploader_model', null);

        $user = TestUser::create(['name' => 'Broken Uploader Config User']);
        $attachment = app(AttachmentService::class)->store(
            $user,
            UploadedFile::fake()->image('avatar.jpg'),
            'avatar',
            $user->id
        );

        $attachment->uploader();
    }

    public function test_upgrade_migration_updates_legacy_attachments_tables(): void
    {
        $this->migrateLegacyAttachmentsTable();

        $this->assertFalse(Schema::hasColumn('attachments', 'visibility'));

        $migration = $this->loadPackageMigration('update_attachments_table.php.stub');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('attachments', 'visibility'));
        $columns = DB::select('PRAGMA table_info(attachments)');
        $visibilityColumn = collect($columns)->firstWhere('name', 'visibility');

        $this->assertNotNull($visibilityColumn);
    }
}
