<?php

namespace CodeItamarJr\Attachments\Tests;

use CodeItamarJr\Attachments\AttachmentServiceProvider;
use CodeItamarJr\Attachments\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $testRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRoot = sys_get_temp_dir().'/laravel-attachments-testbench';

        $this->recreateDirectories();
        $this->migrateTables();
        $this->registerTemporaryUrlBuilder();
    }

    protected function getPackageProviders($app): array
    {
        return [AttachmentServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('attachments.disk', 'attachments-public');
        $app['config']->set('attachments.directory', 'attachments');
        $app['config']->set('attachments.visibility', 'public');
        $app['config']->set('attachments.private_url_ttl', 5);

        $app['config']->set('filesystems.disks.attachments-public', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/laravel-attachments-testbench/public',
            'url' => 'https://example.test/storage/public',
            'visibility' => 'public',
            'throw' => true,
        ]);

        $app['config']->set('filesystems.disks.attachments-private', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/laravel-attachments-testbench/private',
            'url' => 'https://example.test/storage/private',
            'visibility' => 'private',
            'throw' => true,
        ]);
    }

    protected function migrateTables(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('collection')->default('default');
            $table->string('disk');
            $table->string('path');
            $table->string('visibility')->default('public');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('collection');
            $table->index('uploaded_by');
        });
    }

    protected function recreateDirectories(): void
    {
        foreach (['attachments-public', 'attachments-private'] as $disk) {
            $root = config("filesystems.disks.{$disk}.root");

            if (is_string($root) && is_dir($root)) {
                $this->deleteDirectory($root);
            }

            if (is_string($root) && ! is_dir($root)) {
                mkdir($root, 0777, true);
            }
        }
    }

    protected function registerTemporaryUrlBuilder(): void
    {
        Storage::disk('attachments-private')->buildTemporaryUrlsUsing(
            function (string $path, \DateTimeInterface $expiration, array $options): string {
                return sprintf(
                    'https://example.test/temp/%s?expires=%s',
                    ltrim($path, '/'),
                    $expiration->getTimestamp()
                );
            }
        );
    }

    protected function deleteDirectory(string $path): void
    {
        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $itemPath = $path.'/'.$item;

            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
