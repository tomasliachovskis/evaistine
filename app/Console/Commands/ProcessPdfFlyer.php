<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\PdfFlyerProcessingService;
use Illuminate\Console\Command;

class ProcessPdfFlyer extends Command
{
    protected $signature = 'flyers:process-pdf {pdf : Path to PDF file} {--store= : Store name (e.g., ŠILAS)} {--page= : Process only specific page number (e.g., --page=3)}';
    protected $description = 'Process discount flyer PDF and extract discount information using OpenAI Vision API';

    private PdfFlyerProcessingService $processingService;

    public function __construct(PdfFlyerProcessingService $processingService)
    {
        parent::__construct();
        $this->processingService = $processingService;
    }

    public function handle()
    {
        $pdfPath = $this->argument('pdf');
        $storeName = $this->option('store');

        if (!$storeName) {
            $this->error('Store name is required. Use --store option (e.g., --store=ŠILAS)');
            return 1;
        }

        if (!file_exists($pdfPath)) {
            $this->error("PDF file not found: {$pdfPath}");
            return 1;
        }

        $store = Store::where('name', $storeName)->first();

        if (!$store) {
            $this->error("Store not found: {$storeName}");
            $this->info('Available stores: ' . Store::pluck('name')->implode(', '));
            return 1;
        }

        $pageNumber = $this->option('page') ? (int)$this->option('page') : null;

        $this->info("Processing PDF flyer for store: {$store->name}");
        $this->info("PDF file: {$pdfPath}");
        if ($pageNumber) {
            $this->info("Processing only page: {$pageNumber}");
        }
        $this->info("Note: Detailed progress is logged. Check logs with: tail -f storage/logs/laravel.log");
        $this->newLine();

        try {
            $this->info("Starting PDF processing...");
            $result = $this->processingService->processPdf($pdfPath, $store, $pageNumber);

            if ($result['success']) {
                $this->info("Successfully processed PDF flyer!");
                $this->info("Extracted discounts: {$result['total_extracted']}");
                $this->info("Saved to database: {$result['count']}");
                $this->newLine();
                $this->info('Next step: Run "php artisan discounts:process" to finalize the discounts.');
                return 0;
            } else {
                $this->error("Failed to process PDF: {$result['message']}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error processing PDF: {$e->getMessage()}");
            $this->newLine();
            $this->error("Stack trace:");
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
