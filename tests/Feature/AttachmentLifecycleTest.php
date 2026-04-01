<?php

namespace CodeItamarJr\Attachments\Tests\Feature;

use Carbon\CarbonImmutable;
use CodeItamarJr\Attachments\Models\Attachment;
use CodeItamarJr\Attachments\Services\AttachmentService;
use CodeItamarJr\Attachments\Tests\Fixtures\PlainModel;
use CodeItamarJr\Attachments\Tests\Fixtures\TestUser;
use CodeItamarJr\Attachments\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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
}
