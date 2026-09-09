<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Services\NewsArticleService;
use Illuminate\Console\Command;

class GenerateNewsCovers extends Command
{
    protected $signature = 'news:generate-covers
                            {--limit=20 : Max number of posts to process this run}
                            {--publish : Also mark each post published (status=published, published_at=now, staggered a minute apart) once its cover is generated}';

    protected $description = 'Generate an AI cover illustration for blog posts that do not have one yet';

    public function handle(NewsArticleService $service): int
    {
        $limit = (int) $this->option('limit');
        $shouldPublish = (bool) $this->option('publish');

        $posts = BlogPost::whereNull('cover_image')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($posts->isEmpty()) {
            $this->info('Nothing to do — every post already has a cover image.');

            return 0;
        }

        $this->info("Generating covers for {$posts->count()} post(s)...");

        $done = 0;
        $failed = 0;
        $publishAt = now();

        foreach ($posts as $post) {
            $url = $service->generateCoverImage($post);

            if (!$url) {
                $failed++;
                $this->warn("  #{$post->id} {$post->slug}: FAILED, leaving cover_image unset");
                continue;
            }

            $post->cover_image = $url;

            if ($shouldPublish) {
                $post->status = 'published';
                $post->published_at = $publishAt;
                $publishAt = $publishAt->copy()->addMinute();
            }

            $post->save();

            $done++;
            $this->line("  #{$post->id} {$post->slug}: OK -> {$url}" . ($shouldPublish ? ' (published)' : ''));
        }

        $this->info("Done: {$done} generated, {$failed} failed.");

        return 0;
    }
}
