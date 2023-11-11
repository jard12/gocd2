<?php

declare(strict_types=1);

namespace Kami\Cocktail\Console\Commands;

use ZipArchive;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Kami\Cocktail\Models\Cocktail;
use Illuminate\Support\Facades\File;
use Kami\Cocktail\Models\Ingredient;
use Kami\Cocktail\Exceptions\ExportFileNotCreatedException;

class BarDataFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bar:export-recipes {barId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all the recipes from a bar';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $barId = (int) $this->argument('barId');

        File::ensureDirectoryExists(storage_path('bar-assistant/backups'));
        $filename = storage_path(sprintf('bar-assistant/backups/%s_%s.zip', Carbon::now()->format('Ymdhi'), 'recipes'));

        $zip = new ZipArchive();

        if ($zip->open($filename, ZipArchive::CREATE) !== true) {
            $message = sprintf('Error creating zip archive with filepath "%s"', $filename);

            throw new ExportFileNotCreatedException($message);
        }

        $this->dumpCocktails($barId, $zip);
        $this->dumpIngredients($barId, $zip);

        $zip->close();

        return Command::SUCCESS;
    }

    private function dumpCocktails(int $barId, ZipArchive &$zip): void
    {
        $cocktails = Cocktail::with(['ingredients.ingredient', 'ingredients.substitutes', 'images' => function ($query) {
            $query->orderBy('sort');
        }, 'glass', 'method', 'tags'])->where('bar_id', $barId)->get();

        foreach ($cocktails as $cocktail) {
            $data = $cocktail->share();

            $cocktailYaml = Yaml::dump($data, 8, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            $zip->addFromString('cocktails/' . $data['_id'] . '.yml', $cocktailYaml);

            $i = 1;
            foreach ($cocktail->images as $img) {
                $zip->addFile($img->getPath(), 'cocktails/images/' . $data['_id'] . '-' . $i . '.' . $img->file_extension);
                $i++;
            }
        }
    }

    private function dumpIngredients(int $barId, ZipArchive &$zip): void
    {
        $ingredients = Ingredient::with(['images' => function ($query) {
            $query->orderBy('sort');
        }])->where('bar_id', $barId)->get();

        foreach ($ingredients as $ingredient) {
            $data = $ingredient->share();

            $ingredientYaml = Yaml::dump($data, 8, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            $zip->addFromString('ingredients/' . $data['_id'] . '.yml', $ingredientYaml);

            $i = 1;
            foreach ($ingredient->images as $img) {
                $zip->addFile($img->getPath(), 'ingredients/images/' . $data['_id'] . '-' . $i . '.' . $img->file_extension);
                $i++;
            }
        }
    }
}
