<?php

namespace Kami\Cocktail\Console\Commands;

use Illuminate\Console\Command;
use Kami\Cocktail\Services\ImportService;

class BarImportFromFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bar:import-zip {filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $service = resolve(ImportService::class);

        $service->importFromZipFile(storage_path('ba_export_2023-01-23-04-49-41.zip'));

        return Command::SUCCESS;
    }
}