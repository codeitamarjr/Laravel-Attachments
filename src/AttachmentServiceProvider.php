<?php

namespace CodeItamarJr\Attachments;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use CodeItamarJr\Attachments\Services\AttachmentService;

class AttachmentServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/attachments.php', 'attachments');

        $this->app->singleton(AttachmentService::class, function ($app) {
            return new AttachmentService();
        });

        $this->app->alias(AttachmentService::class, 'attachments.service');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/attachments.php' => config_path('attachments.php'),
        ], 'attachments-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations/create_attachments_table.php.stub' => 'create_attachments_table.php',
            __DIR__ . '/../database/migrations/update_attachments_table.php.stub' => 'update_attachments_table.php',
        ], 'attachments-migrations');
    }

    public function provides(): array
    {
        return [AttachmentService::class, 'attachments.service'];
    }
}
