<?php

namespace QuickTapPay\Attachments;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use QuickTapPay\Attachments\Services\AttachmentService;

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

    public function boot(Filesystem $filesystem): void
    {
        $this->publishes([
            __DIR__ . '/../config/attachments.php' => config_path('attachments.php'),
        ], 'attachments-config');

        if (! class_exists('CreateAttachmentsTable')) {
            $timestamp = date('Y_m_d_His', time());
            $this->publishes([
                __DIR__ . '/../database/migrations/create_attachments_table.php.stub' => database_path("migrations/{$timestamp}_create_attachments_table.php"),
            ], 'attachments-migrations');
        }

    }

    public function provides(): array
    {
        return [AttachmentService::class, 'attachments.service'];
    }
}
