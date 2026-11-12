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
    public function test_import_is_conservative_and_requires_explicit_apply(): void
    {
        $platform = User::factory()->create(['email' => 'admin@asso.com']);
        $seller = User::factory()->create();
        $category = Category::create(['name' => 'Test', 'slug' => 'test']);
        foreach ([$platform, $seller] as $owner) {
            $shop = Shop::create(['user_id' => $owner->id, 'name' => 'Shop', 'slug' => 'shop-'.$owner->id, 'status' => 'active']);
            $products[] = Product::create(['user_id' => $owner->id, 'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => 'Product', 'slug' => 'product-'.$owner->id, 'price' => 1000, 'stock' => 10, 'status' => 'active', 'type' => 'article', 'commercial_information' => 'Garantie. Référence : ASSO-FRN-LRG-001']);
        }
        $this->artisan('asso:import-product-references')->assertSuccessful();
        $this->assertDatabaseCount('asso_product_references', 0);
        $this->artisan('asso:import-product-references', ['--apply' => true])->assertSuccessful();
        $this->artisan('asso:import-product-references', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('asso_product_references', 1);
        $this->assertNotNull($products[0]->fresh()->asso_product_reference_id);
        $this->assertNull($products[1]->fresh()->asso_product_reference_id);
        $this->assertSame('Garantie. Référence : ASSO-FRN-LRG-001', $products[0]->fresh()->commercial_information);
    }

}
