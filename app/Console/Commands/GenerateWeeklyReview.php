<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Services\WeeklyReviewService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateWeeklyReview extends Command
{
    protected $signature = 'blog:generate-weekly-review {--week= : Specific week to review (YYYY-MM-DD format, any date in the week)} {--draft : Save as draft instead of published}';
    protected $description = 'Generate weekly review blog post covering discounts, store reviews, and category reviews from the past calendar week';

    private WeeklyReviewService $reviewService;

    public function __construct(WeeklyReviewService $reviewService)
    {
        parent::__construct();
        $this->reviewService = $reviewService;
    }

    public function handle()
    {
        if (!$this->reviewService->isConfigured()) {
            $this->error('WeeklyReviewService is not configured. Please set OPENAI_API_KEY environment variable.');
            return 1;
        }

        $weekInput = $this->option('week');
        $weekStart = null;

        if ($weekInput) {
            try {
                $date = Carbon::parse($weekInput);
                $weekStart = $date->copy()->startOfWeek();
            } catch (\Exception $e) {
                $this->error("Invalid date format: {$weekInput}. Please use YYYY-MM-DD format.");
                return 1;
            }
        } else {
            $weekStart = now()->subWeek()->startOfWeek();
        }

        $weekEnd = (clone $weekStart)->endOfWeek();

        $this->info("Generating weekly review for week: {$weekStart->format('Y-m-d')} to {$weekEnd->format('Y-m-d')}");
        $this->newLine();

        $existingPost = BlogPost::where('slug', 'like', '%' . $weekStart->format('Y-m-d') . '%')
            ->orWhere('title', 'like', '%' . $weekStart->format('m d') . '%')
            ->first();

        if ($existingPost) {
            if (!$this->confirm("A blog post for this week already exists (ID: {$existingPost->id}). Do you want to generate a new one?", false)) {
                $this->info('Generation cancelled.');
                return 0;
            }
        }

        $this->info('Collecting discount data and preparing review...');
        $this->line('This may take a few minutes...');
        $this->newLine();

        try {
            $discounts = $this->reviewService->getDiscountsForWeek($weekStart, $weekEnd);
//            $reviewImage = $this->reviewService->generateReviewImage($weekStart, $discounts);
            $reviewImage = '';

            $result = $this->reviewService->generateWeeklyReview($weekStart);

            if (!$result) {
                $this->error('Failed to generate weekly review. Check logs for details.');
                return 1;
            }

            $this->info('Generating review image...');
            $reviewImage = $this->reviewService->generateReviewImage($weekStart, $discounts);

            if ($reviewImage) {
                $this->info("Review image generated: {$reviewImage}");
            } else {
                $this->warn('Review image generation failed, but continuing...');
            }

            $status = $this->option('draft') ? 'draft' : 'published';

            $blogPost = BlogPost::create([
                'title' => $result['title'],
                'slug' => $result['slug'],
                'content' => $result['content'],
                'meta_title' => $result['meta_title'],
                'meta_description' => $result['meta_description'],
                'status' => $status,
                'published_at' => $status === 'published' ? $result['published_at'] : null,
                'review_image' => $reviewImage,
            ]);

            $this->newLine();
            $this->info("✓ Successfully generated weekly review blog post!");
            $this->newLine();
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $blogPost->id],
                    ['Title', $blogPost->title],
                    ['Slug', $blogPost->slug],
                    ['Status', $blogPost->status],
                    ['Published At', $blogPost->published_at ? $blogPost->published_at->format('Y-m-d H:i:s') : 'Not published'],
                    ['Content Length', mb_strlen(strip_tags($blogPost->content)) . ' characters'],
                    ['Review Image', $blogPost->review_image ? $blogPost->review_image : 'Not generated'],
                ]
            );

            $this->newLine();
            $this->info("Blog post saved with ID: {$blogPost->id}");

            return 0;

        } catch (\Exception $e) {
            $this->error("Error generating weekly review: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}

