<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Providers;

use App\Extension\BasePluginServiceProvider;
use Plugins\G7\Plugin\Custom\BoardComments\Plugin;
use Plugins\G7\Plugin\Custom\BoardComments\Services\CommentLikeService;

class BoardCommentsServiceProvider extends BasePluginServiceProvider
{
    protected string $pluginIdentifier = Plugin::IDENTIFIER;

    public function register(): void
    {
        parent::register();

        $this->app->singleton(CommentLikeService::class);
    }
}
