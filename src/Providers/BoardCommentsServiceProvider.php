<?php

namespace Plugins\Custom\BoardComments\Providers;

use Illuminate\Support\ServiceProvider;
use Plugins\Custom\BoardComments\Console\Commands\MigrateCommand;

/**
 * 0.2.0: artisan 명령 등록 (custom-board_comments:migrate).
 */
class BoardCommentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MigrateCommand::class]);
        }
    }
}
