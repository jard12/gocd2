<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Tests\TestCase;
use Kami\Cocktail\Models\Bar;
use Kami\Cocktail\Models\User;
use Kami\Cocktail\Models\Cocktail;
use Kami\Cocktail\Models\Collection;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CollectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_user_collections_response(): void
    {
        $this->setupBar();

        Collection::factory()->count(10)->create([
            'bar_membership_id' => 1,
        ]);

        $response = $this->getJson('/api/collections?bar_id=1');

        $response->assertOk();
        $response->assertJson(
            fn (AssertableJson $json) =>
            $json
                ->has('data', 10)
                ->etc()
        );
    }

    public function test_show_user_collection_response(): void
    {
        $this->setupBar();

        $collection = Collection::factory()->create([
            'name' => 'TEST',
            'description' => 'Description',
            'bar_membership_id' => 1,
        ]);

        $response = $this->getJson('/api/collections/' . $collection->id);

        $response->assertOk();
        $response->assertJson(
            fn (AssertableJson $json) =>
            $json
                ->has('data')
                ->where('data.id', $collection->id)
                ->where('data.name', 'TEST')
                ->where('data.description', 'Description')
                ->where('data.cocktails', [])
                ->etc()
        );
    }

    public function test_create_collection_response(): void
    {
        $bar = $this->setupBar();

        $cocktail = Cocktail::factory()->create(['bar_id' => $bar->id]);
        $response = $this->postJson('/api/collections?bar_id=1', [
            'name' => 'TEST',
            'description' => 'Description',
            'cocktails' => [$cocktail->id]
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->headers->get('Location'));
        $response->assertJson(
            fn (AssertableJson $json) =>
            $json
                ->has('data')
                ->has('data.id')
                ->where('data.name', 'TEST')
                ->where('data.description', 'Description')
                ->where('data.cocktails', [$cocktail->id])
                ->etc()
        );
    }

    public function test_create_collection_does_not_add_cocktail_from_another_bar_response(): void
    {
        $bar = $this->setupBar();
        $anotherBar = Bar::factory()->create(['created_user_id' => auth()->user()->id]);

        $cocktail1 = Cocktail::factory()->create(['bar_id' => $bar->id]);
        $cocktail2 = Cocktail::factory()->create(['bar_id' => $anotherBar->id]);
        $response = $this->postJson('/api/collections?bar_id=1', [
            'name' => 'TEST',
            'description' => 'Description',
            'cocktails' => [$cocktail1->id, $cocktail2->id]
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->headers->get('Location'));
        $response->assertJson(
            fn (AssertableJson $json) =>
            $json
                ->has('data')
                ->has('data.id')
                ->where('data.name', 'TEST')
                ->where('data.description', 'Description')
                ->where('data.cocktails', [$cocktail1->id])
                ->etc()
        );
    }

    public function test_update_collections_response(): void
    {
        $this->setupBar();

        $model = Collection::factory()->create([
            'name' => 'TEST',
            'description' => 'Description',
            'bar_membership_id' => 1,
        ]);

        $response = $this->putJson('/api/collections/' . $model->id, [
            'name' => 'TEST 2',
            'description' => 'Description 2',
        ]);

        $response->assertSuccessful();
        $response->assertJson(
            fn (AssertableJson $json) =>
            $json
                ->has('data')
                ->where('data.id', $model->id)
                ->where('data.name', 'TEST 2')
                ->where('data.description', 'Description 2')
                ->where('data.cocktails', [])
                ->etc()
        );
    }

    public function test_delete_collection_response(): void
    {
        $this->setupBar();

        $model = Collection::factory()->create([
            'name' => 'TEST',
            'description' => 'Description',
            'bar_membership_id' => 1,
        ]);

        $response = $this->delete('/api/collections/' . $model->id);

        $response->assertNoContent();

        $this->assertDatabaseMissing('collections', ['id' => $model->id]);
    }

    public function test_add_cocktail_to_collection(): void
    {
        $bar = $this->setupBar();

        $cocktail = Cocktail::factory()->create(['bar_id' => $bar->id]);
        $collection = Collection::factory()->create([
            'name' => 'TEST',
            'description' => 'Description',
            'bar_membership_id' => 1,
        ]);

        $response = $this->putJson('/api/collections/' . $collection->id . '/cocktails/' . $cocktail->id);

        $response->assertSuccessful();
        $response->assertJson(
            fn (AssertableJson $json) =>
            $json
                ->has('data')
                ->where('data.id', $collection->id)
                ->where('data.name', 'TEST')
                ->where('data.description', 'Description')
                ->where('data.cocktails', [$cocktail->id])
                ->etc()
        );
    }

    public function test_list_shared_collections_response(): void
    {
        $this->setupBar();

        Collection::factory()->count(5)->create([
            'bar_membership_id' => 1,
        ]);
        Collection::factory()->count(3)->create([
            'bar_membership_id' => 1,
            'is_bar_shared' => true
        ]);

        $response = $this->getJson('/api/collections/shared?bar_id=1');

        $response->assertOk();
        $response->assertJson(
            fn (AssertableJson $json) =>
            $json
                ->has('data', 3)
                ->etc()
        );
    }
}
