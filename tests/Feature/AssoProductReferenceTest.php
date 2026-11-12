<?php

namespace Tests\Feature;

use App\Models\AssoProductReference;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\DeliveryQuoteService;
use App\Support\AssoScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssoProductReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CommissionService::flush();
        (new \ReflectionProperty(AssoScope::class, 'ownerId'))->setValue(null, null);
    }

    public function test_asso_origin_uses_reference_and_other_sellers_keep_their_shop(): void
    {
        $platform = User::factory()->create(['email' => 'admin@asso.com']);
        $seller = User::factory()->create();
        $category = Category::create(['name' => 'Test', 'slug' => 'test']);
        $reference = AssoProductReference::create(['name' => 'R1', 'address' => 'Point A', 'city' => 'Yaoundé', 'country' => 'CM', 'quarter' => 'Centre', 'latitude' => 3.86, 'longitude' => 11.52, 'is_active' => true]);
        foreach ([$platform, $seller] as $owner) {
            $shop = Shop::create(['user_id' => $owner->id, 'name' => 'Shop', 'slug' => 'shop-'.$owner->id, 'status' => 'active', 'city' => 'Douala', 'country' => 'CM', 'latitude' => 4.05, 'longitude' => 9.7]);
            $products[] = Product::create(['user_id' => $owner->id, 'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Product', 'slug' => 'product-'.$owner->id, 'price' => 1000, 'stock' => 10, 'status' => 'active', 'type' => 'article', 'weight' => 1, 'asso_product_reference_id' => $reference->id]);
        }
        $this->assertEquals([3.86, 11.52], $products[0]->publicCoordinates());
        $this->assertEquals([4.05, 9.7], $products[1]->publicCoordinates());
        $cart = app(DeliveryQuoteService::class)->cart([['product_id' => $products[0]->id, 'quantity' => 1]]);
        $this->assertSame('Yaoundé', $cart['origin'][0]);
        $this->assertEquals(3.86, $cart['origin_lat']);
        $this->assertSame('Point A', $cart['collection_origin']['address']);
        $reference->update(['address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null]);
        $unlocated = $products[0]->fresh();
        $this->assertEquals([4.05, 9.7], $unlocated->publicCoordinates());
        $fallback = app(DeliveryQuoteService::class)->cart([['product_id' => $unlocated->id, 'quantity' => 1]]);
        $this->assertSame('Douala', $fallback['origin'][0]);
        $this->assertNull($fallback['collection_origin']);
        $other = app(DeliveryQuoteService::class)->cart([['product_id' => $products[1]->id, 'quantity' => 1]]);
        $this->assertSame('Douala', $other['origin'][0]);
        $this->assertNull($other['collection_origin']);
    }
    public function test_wholesale_asso_product_also_ships_from_its_located_reference(): void
    {
        $platform = User::factory()->create(['email' => 'admin@asso.com']);
        $category = Category::create(['name' => 'Test', 'slug' => 'test']);
        $reference = AssoProductReference::create(['name' => 'R1', 'address' => 'Point A', 'city' => 'Yaoundé', 'country' => 'CM', 'latitude' => 3.86, 'longitude' => 11.52, 'is_active' => true]);
        $shop = Shop::create(['user_id' => $platform->id, 'name' => 'Shop', 'slug' => 'shop', 'status' => 'active', 'city' => 'Douala', 'country' => 'CM', 'latitude' => 4.05, 'longitude' => 9.7]);
        $product = Product::create(['user_id' => $platform->id, 'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Gros', 'slug' => 'gros', 'price' => 1000, 'stock' => 10, 'status' => 'active', 'type' => 'article', 'weight' => 1, 'is_wholesale' => true, 'asso_product_reference_id' => $reference->id]);
        $cart = app(DeliveryQuoteService::class)->cart([['product_id' => $product->id, 'quantity' => 1]]);
        $this->assertSame('Yaoundé', $cart['origin'][0]);
        $this->assertSame($reference->id, $cart['collection_origin']['reference_id']);
    }

    public function test_sync_links_products_strips_code_and_can_be_restored(): void
    {
        $platform = User::factory()->create(['email' => 'admin@asso.com']);
        $seller = User::factory()->create();
        $category = Category::create(['name' => 'Test', 'slug' => 'test']);
        $texts = ["Garantie. Référence : \u{2060}ASSO-FRN-LRG-001\u{2060}.", "FR : Référence : FRN-CHN-ELM-001\r\n01 pièce = 01 carton"];
        foreach ([[$platform, $texts[0]], [$platform, $texts[1]], [$seller, $texts[0]]] as $i => [$owner, $text]) {
            $shop = Shop::firstOrCreate(['user_id' => $owner->id], ['name' => 'Shop', 'slug' => 'shop-'.$owner->id, 'status' => 'active']);
            $products[] = Product::create(['user_id' => $owner->id, 'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Product', 'slug' => 'product-'.$i, 'price' => 1000, 'stock' => 10, 'status' => 'active', 'type' => 'article', 'is_wholesale' => $i === 1, 'commercial_information' => $text]);
        }

        $this->artisan('asso:sync-product-references')->assertSuccessful();
        $this->assertDatabaseCount('asso_product_references', 0);
        $this->assertSame($texts[0], $products[0]->fresh()->commercial_information);

        $this->artisan('asso:sync-product-references', ['--apply' => true])->assertSuccessful();
        $this->artisan('asso:sync-product-references', ['--apply' => true])->assertSuccessful();
        $this->assertSame(['ASSO-FRN-LRG-001', 'FRN-CHN-ELM-001'], AssoProductReference::orderBy('name')->pluck('name')->all());
        $this->assertSame('ASSO-FRN-LRG-001', $products[0]->fresh()->assoReference->name);
        $this->assertSame('Garantie.', $products[0]->fresh()->commercial_information);
        $this->assertSame('FRN-CHN-ELM-001', $products[1]->fresh()->assoReference->name);
        $this->assertSame('01 pièce = 01 carton', $products[1]->fresh()->commercial_information);
        // Produit d'un autre vendeur : jamais touché.
        $this->assertNull($products[2]->fresh()->asso_product_reference_id);
        $this->assertSame($texts[0], $products[2]->fresh()->commercial_information);

        $backup = collect(glob(storage_path('app/asso-references/sauvegarde-*.json')))->sort()->first();
        $this->artisan('asso:sync-product-references', ['--restore' => $backup, '--apply' => true])->assertSuccessful();
        $this->assertSame($texts[0], $products[0]->fresh()->commercial_information);
        $this->assertNull($products[0]->fresh()->asso_product_reference_id);
        $this->assertDatabaseCount('asso_product_references', 0);
    }

    public function test_reference_code_is_kept_as_given_and_duplicates_are_refused(): void
    {
        $this->assertSame(['ASSO-FRN-AGR-001'], \App\Support\AssoReferenceCode::extract("Référence : ASSO-\r\nFRN-AGR-001"));
        $this->assertSame('- Conditionnement : sac', \App\Support\AssoReferenceCode::strip("- Conditionnement : sac\r\n- Référence : ASSO-FRN-AGR-001"));
        AssoProductReference::create(['name' => 'ASSO-FRN-AGR-001', 'is_active' => true]);
        $this->assertTrue(AssoProductReference::named(' asso-frn-agr-001 ')->exists());
    }
}
