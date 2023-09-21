<?php

namespace Kami\Cocktail\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Kami\Cocktail\Import\FromCollection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Kami\Cocktail\Import\DuplicateActionsEnum;

class ImportCollection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly array $source, private readonly int $userId, private readonly int $barId, private readonly DuplicateActionsEnum $duplicateActions)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(FromCollection $collectionImporter): void
    {
        $collectionImporter->process($this->source, $this->userId, $this->barId, $this->duplicateActions);
    }
}
