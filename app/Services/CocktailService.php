<?php
declare(strict_types=1);

namespace Kami\Cocktail\Services;

use Throwable;
use Illuminate\Support\Str;
use Kami\Cocktail\Models\Tag;
use Illuminate\Log\LogManager;
use Kami\Cocktail\Models\User;
use Kami\Cocktail\Models\Cocktail;
use Intervention\Image\ImageManager;
use Illuminate\Database\DatabaseManager;
use Kami\Cocktail\Models\CocktailFavorite;
use Illuminate\Filesystem\FilesystemManager;
use Kami\Cocktail\Models\CocktailIngredient;
use Kami\Cocktail\Exceptions\CocktailException;

class CocktailService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly LogManager $log,
        private readonly ImageManager $image,
        private readonly FilesystemManager $filesystem,
    ) {
    }

    /**
     * Create a new cocktail
     *
     * @param string $name
     * @param string $instructions
     * @param array $ingredients
     * @param int $userId
     * @param string|null $description
     * @param string|null $garnish
     * @param string|null $cocktailSource
     * @param string|null $imageAsBase64
     * @param array<string> $tags
     * @return \Kami\Cocktail\Models\Cocktail
     */
    public function createCocktail(
        string $name,
        string $instructions,
        array $ingredients,
        int $userId,
        ?string $description = null,
        ?string $garnish = null,
        ?string $cocktailSource = null,
        ?string $imageAsBase64 = null,
        array $tags = [],
    ): Cocktail
    {
        $this->db->beginTransaction();

        try {
            $cocktail = new Cocktail();
            $cocktail->name = $name;
            $cocktail->instructions = $instructions;
            $cocktail->description = $description;
            $cocktail->garnish = $garnish;
            $cocktail->source = $cocktailSource;
            $cocktail->user_id = $userId;
            $cocktail->save();

            foreach($ingredients as $ingredient) {
                $cIngredient = new CocktailIngredient();
                $cIngredient->ingredient_id = $ingredient['ingredient_id'];
                $cIngredient->amount = $ingredient['amount'];
                $cIngredient->units = $ingredient['units'];
                $cIngredient->sort = $ingredient['sort'] ?? 0;

                $cocktail->ingredients()->save($cIngredient);
            }

            $dbTags = [];
            foreach($tags as $tagName) {
                $tag = new Tag();
                $tag->name = $tagName;
                $tag->save();
                $dbTags[] = $tag->id;
            }

            $cocktail->tags()->attach($dbTags);

        } catch (Throwable $e) {
            $this->log->error('[COCKTAIL_SERVICE] ' . $e->getMessage());
            $this->db->rollBack();

            throw new CocktailException('Error occured while creating a cocktail!', 0, $e);
        }

        $this->db->commit();

        if ($imageAsBase64) {
            try {
                $image = $this->image->make($imageAsBase64);
                $imageName = sprintf('%s_%s.jpg', $cocktail->id, Str::slug($name));

                if ($this->filesystem->disk('public')->put('cocktails/' . $imageName, (string) $image->encode('jpg'))) {
                    $cocktail->image = $imageName;
                    $cocktail->save();
                }
            } catch (Throwable $e) {
                $this->log->error('[COCKTAIL_SERVICE] File upload error. ' . $e->getMessage());
            }
        }

        $this->log->info('[COCKTAIL_SERVICE] Cocktail "' . $name . '" created with id: ' . $cocktail->id);

        // Refresh model for response
        $cocktail->refresh();
        // Upsert scout index
        $cocktail->searchable();

        return $cocktail;
    }

    /**
     * Return all cocktails that user can create with
     * ingredients in his shelf
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection<\Kami\Cocktail\Models\Cocktail>
     */
    public function getCocktailsByUserIngredients(int $userId)
    {
        // Cocktails with possible ingredients
        // SELECT c.id, c.name, count(*) as total FROM cocktails AS c
        // INNER JOIN cocktail_ingredients AS ci ON ci.cocktail_id = c.id
        // INNER JOIN ingredients AS i ON i.id = ci.ingredient_id
        // WHERE ci.ingredient_id IN (SELECT ingredient_id FROM user_ingredients WHERE user_id = 2)
        // GROUP BY c.id, c.name
        // HAVING total <= (SELECT COUNT(*) FROM user_ingredients WHERE user_id = 2)
        // ORDER BY total DESC
        // LIMIT 10;

        // Cocktails strictly available
        // https://stackoverflow.com/questions/19930070/mysql-query-to-select-all-except-something
        // SELECT c.*
        // FROM cocktails c
        // JOIN cocktail_ingredients ci ON ci.cocktail_id = c.id
        // JOIN ingredients i ON i.id = ci.ingredient_id
        // GROUP
        //     BY c.id
        // HAVING SUM(CASE WHEN i.id IN (SELECT ingredient_id FROM user_ingredients WHERE user_id = 2) THEN 1 ELSE 0 END) = COUNT(*);

        $cocktailIds = $this->db->table('cocktails AS c')
            ->select('c.id')
            ->join('cocktail_ingredients AS ci', 'ci.cocktail_id', '=', 'c.id')
            ->join('ingredients AS i', 'i.id', '=', 'ci.ingredient_id')
            ->groupBy('c.id')
            ->havingRaw('SUM(CASE WHEN i.id IN (SELECT ingredient_id FROM user_ingredients WHERE user_id = ?) THEN 1 ELSE 0 END) = COUNT(*)', [$userId])
            ->get()
            ->pluck('id');

        return Cocktail::find($cocktailIds);
    }

    /**
     * Toggle user favorite cocktail
     *
     * @param \Kami\Cocktail\Models\User $user
     * @param int $cocktailId
     * @return bool
     */
    public function toggleFavorite(User $user, int $cocktailId): bool
    {
        $cocktail = Cocktail::find($cocktailId);

        $existing = CocktailFavorite::where('cocktail_id', $cocktailId)->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->delete();

            return false;
        }

        $cocktailFavorite = new CocktailFavorite();
        $cocktailFavorite->cocktail_id = $cocktail->id;

        $user->favorites()->save($cocktailFavorite);

        return true;
    }
}
