<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\LocationFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_addresses_are_summarized_as_city_and_country(): void
    {
        $this->assertSame('Douala, Cameroun', LocationFormatter::label(null, null, 'Rue 1, Douala, Cameroun'));
        $this->assertSame('Douala, Cameroun', LocationFormatter::label(null, null,
            'Marché Central de Douala, Douala IIe, Communauté Urbaine de Douala, Wouri, Région du Littoral, Cameroun'));
        $this->assertSame('Yaoundé, Cameroun', LocationFormatter::label(null, null, 'Marché Mokolo, Yaoundé'));
        $this->assertNull(LocationFormatter::label(null, null, 'Lat: 4.05, Lng: 9.70'));
        $this->assertSame('Kribi, Cameroun', LocationFormatter::label('Kribi', 'Cameroun', 'n’importe quoi'));
    }

    public function test_vendor_updates_location_and_products_show_city_country(): void
    {
        $vendor = User::factory()->create(['role' => 'vendeur']);
        $shop = Shop::create([
            'user_id' => $vendor->id,
            'name' => 'Boutique',
            'slug' => 'boutique',
            'status' => 'active',
            'address' => 'Bonapriso',
        ]);
        $category = Category::create(['name' => 'Divers', 'slug' => 'divers']);
        $product = Product::create([
            'user_id' => $vendor->id, 'shop_id' => $shop->id, 'category_id' => $category->id,
            'name' => 'Sac', 'price' => 5000, 'stock' => 2, 'status' => 'active',
        ]);
        Sanctum::actingAs($vendor);

        $this->postJson('/api/v1/vendor/shop', [
            '_method' => 'PUT',
            'shop_address' => 'Rue Njo-Njo, Douala, Cameroun',
            'shop_city' => 'Douala',
            'shop_country' => 'Cameroun',
            'shop_latitude' => 4.03,
            'shop_longitude' => 9.69,
        ])->assertOk()
            ->assertJsonPath('shop.location_label', 'Douala, Cameroun');

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('product.location', 'Douala, Cameroun');

        // Ancienne version de l'app : seule l'adresse est envoyée.
        $this->postJson('/api/v1/vendor/shop', [
            '_method' => 'PUT',
            'shop_address' => 'Carrefour Warda, Yaoundé, Cameroun',
        ])->assertOk()
            ->assertJsonPath('shop.city', 'Yaoundé')
            ->assertJsonPath('shop.location_label', 'Yaoundé, Cameroun');
    }
}
