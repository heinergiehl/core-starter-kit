<?php

namespace App\Console\Commands;

use App\Domain\Content\Services\MarkdownBlogSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BlogSyncContent extends Command
{
    protected $signature = 'blog:sync-content
        {--path=content/blog : Relative or absolute root that contains article folders}
        {--dry-run : Preview creates, updates, and archives without writing to the database}
        {--archive-missing : Archive markdown-managed posts whose source files no longer exist}
        {--create-only : Only create missing markdown posts; skip updates to existing markdown-managed posts}
        {--publish : Force imported markdown posts to published status}
        {--publish-now : Force imported markdown posts live immediately, overriding future published_at values}
        {--author= : Fallback author email when a file omits author_email}';

    protected $description = 'Sync multilingual markdown blog content into the database';

    public function __construct(
        public MarkdownBlogSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->synchronizer->sync(
            path: (string) $this->option('path'),
            dryRun: (bool) $this->option('dry-run'),
            archiveMissing: (bool) $this->option('archive-missing'),
            fallbackAuthorEmail: $this->option('author') ? (string) $this->option('author') : null,
            createOnly: (bool) $this->option('create-only'),
            forcePublish: (bool) $this->option('publish'),
            publishNow: (bool) $this->option('publish-now'),
        );

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if ($result['errors'] !== []) {
            $this->newLine();
            $this->line("Scanned path: {$result['root_path']}");
            $this->line("Markdown files discovered: {$result['discovered']}");

            return self::FAILURE;
        }

        if ($result['dry_run']) {
            $this->warn('Dry run mode enabled. No database changes were written.');
        }

        if (($result['discovered'] === 0) && ($result['changes'] === []) && ($result['warnings'] === [])) {
            $this->info("No markdown blog files found in [{$result['root_path']}].");

            return self::SUCCESS;
        }

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->table(
            ['Action', 'Locale', 'Source Path', 'Title'],
            collect($result['changes'])
                ->map(static fn (array $change): array => [
                    Str::headline($change['action']),
                    strtoupper($change['locale']),
                    $change['source_path'],
                    $change['title'],
                ])
                ->all()
        );

        $this->newLine();
        $this->line("Scanned path: {$result['root_path']}");
        $this->line("Markdown files discovered: {$result['discovered']}");
        $this->line("Created: {$result['created']}");
        $this->line("Updated: {$result['updated']}");
        $this->line("Skipped: {$result['skipped']}");
        $this->line("Unchanged: {$result['unchanged']}");
        $this->line("Archived: {$result['archived']}");

        $this->newLine();
        $this->info($result['dry_run'] ? 'Markdown sync preview complete.' : 'Markdown blog content synced successfully.');

        return self::SUCCESS;
    }
}
