<?php

declare(strict_types=1);

namespace Kami\Cocktail\Http\Controllers;

use Throwable;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Http\JsonResponse;
use Kami\Cocktail\Scraper\Manager;
use Kami\Cocktail\Import\FromArray;
use Kami\Cocktail\Jobs\ImportCollection;
use Kami\Cocktail\Http\Requests\ImportRequest;
use Kami\Cocktail\Import\DuplicateActionsEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Kami\Cocktail\Http\Resources\CocktailResource;

class ImportController extends Controller
{
    public function cocktail(ImportRequest $request, FromArray $arrayImporter): JsonResponse|JsonResource
    {
        $dataToImport = [];
        $type = $request->get('type', 'url');
        $save = $request->get('save', false);
        $source = $request->post('source');
        $duplicateAction = DuplicateActionsEnum::from((int) $request->post('duplicate_actions', '0'));

        if ($type === 'url') {
            $request->validate(['source' => 'url']);
            try {
                $scraper = Manager::scrape($source);
            } catch (Throwable $e) {
                abort(400, $e->getMessage());
            }

            $dataToImport = $scraper->toArray();
        }

        if ($type === 'json') {
            if (!is_array($source)) {
                if (!$source = json_decode($source, true)) {
                    abort(400, 'Unable to parse the JSON string');
                }
            }

            $dataToImport = $source;
        }

        if ($type === 'yaml' || $type === 'yml') {
            try {
                $dataToImport = Yaml::parse($source);
            } catch (Throwable) {
                abort(400, sprintf('Unable to parse the YAML string'));
            }
        }

        if ($type === 'collection') {
            if (!is_array($source)) {
                if (!$source = json_decode($source, true)) {
                    abort(400, 'Unable to parse the JSON string');
                }
            }

            if (count($source['cocktails']) === 0) {
                abort(400, sprintf('No cocktails found'));
            }

            ImportCollection::dispatch($source, $request->user()->id, bar()->id, $duplicateAction);

            return response()->json([
                'data' => ['status' => 'started']
            ]);
        }

        if ($save) {
            $cocktail = $arrayImporter->process($dataToImport, $request->user()->id, bar()->id);
            $cocktail->load(['ingredients.ingredient', 'images' => function ($query) {
                $query->orderBy('sort');
            }, 'tags', 'glass', 'ingredients.substitutes', 'method', 'createdUser', 'updatedUser', 'collections', 'utensils']);
            $dataToImport = new CocktailResource($cocktail);
        }

        return response()->json([
            'data' => $dataToImport
        ]);
    }
}
