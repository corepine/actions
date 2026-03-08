<?php

declare(strict_types=1);

namespace Corepine\Actions\Console\Commands;

use Corepine\Actions\ActionsServiceProvider;
use Illuminate\Console\Command;

class InstallActionsCommand extends Command
{
    protected $signature = 'actions:install
        {--force : Overwrite any existing published files}
        {--migrate : Run database migrations after publishing}';

    protected $description = 'Install corepine/actions by publishing config and migrations';

    public function handle(): int
    {
        $this->comment('Installing corepine/actions...');

        $this->comment('Publishing configuration...');
        $this->publishTag('corepine-actions-config');

        $this->comment('Publishing migrations...');
        $this->publishTag('corepine-actions-migrations');

        if ($this->option('migrate')) {
            $this->comment('Running migrations...');
            $this->call('migrate');
        }

        $this->info('[✓] corepine/actions installed successfully.');

        return self::SUCCESS;
    }

    private function publishTag(string $tag): void
    {
        $params = [
            '--provider' => ActionsServiceProvider::class,
            '--tag' => $tag,
        ];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);
    }
}
