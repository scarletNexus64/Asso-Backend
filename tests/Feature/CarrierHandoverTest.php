<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DelivererCompany;
use App\Models\DeliveryAgency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\FcmService;
use App\Services\FirebaseMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Remise au transporteur : agence et numéro de suivi choisis par ASSO, pas par le vendeur. */
class CarrierHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(FirebaseMessagingService::class, fn ($mock) => $mock->shouldReceive('sendToUser')->andReturn([]));
        $this->mock(FcmService::class, fn ($mock) => $mock->shouldIgnoreMissing());
        $this->seller = User::factory()->create(['role' => 'vendeur']);
    }

    private function order(DelivererCompany $company, array $breakdown = [], array $extra = []): Order
    {
        $shop = Shop::create(['user_id' => $this->seller->id, 'name' => 'B', 'slug' => 'b-'.uniqid(), 'status' => 'active', 'city' => 'Douala']);
        $product = Product::create([
            'user_id' => $this->seller->id, 'shop_id' => $shop->id,
            'category_id' => Category::create(['name' => 'M', 'slug' => 'm-'.uniqid()])->id,
            'name' => 'Sac', 'slug' => 'sac-'.uniqid(), 'price' => 10000, 'stock' => 5, 'status' => 'active',
        ]);
        $order = Order::create($extra + [
            'user_id' => User::factory()->create()->id, 'status' => 'confirmed',
            'subtotal' => 10000, 'total' => 12000, 'delivery_fee' => 2000,
            'delivery_address' => 'Bafoussam, Cameroun', 'payment_method' => 'wallet_kpay', 'payment_status' => 'paid',
            'delivery_mode' => Order::DELIVERY_CARRIER, 'delivery_company_id' => $company->id,
            'delivery_breakdown' => $breakdown + ['company_name' => $company->name],
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'seller_id' => $this->seller->id,
            'quantity' => 1, 'unit_price' => 10000, 'total_price' => 10000,
        ]);

        return $order;
    }

    private function solex(): DelivererCompany
    {
        return DelivererCompany::create([
            'name' => 'SOLEX', 'service_type' => DelivererCompany::SERVICE_INTERCITY,
            'service_mode' => DelivererCompany::MODE_DOOR, 'is_active' => true,
        ]);
    }

    public function test_territorial_order_uses_department_agency_and_generated_number(): void
    {
        $solex = $this->solex();
        DeliveryAgency::create(['deliverer_company_id' => $solex->id, 'code' => 'SOLEX-CM008006', 'name' => 'SOLEX — Bafoussam', 'city' => 'Bafoussam', 'address' => 'Centre', 'phone' => '237600000000', 'is_active' => true]);
        $douala = DeliveryAgency::create(['deliverer_company_id' => $solex->id, 'code' => 'SOLEX-CM005004', 'name' => 'SOLEX — Douala', 'city' => 'Douala', 'address' => 'Akwa', 'phone' => '237600000000', 'is_active' => true]);
        $order = $this->order($solex, [
            'pricing_type' => 'territorial_network',
            'origin_coverage' => ['department_code' => 'CM005004', 'label' => 'Douala 1er'],
            'legs' => [['kind' => 'collection'], ['kind' => 'interregional'], ['kind' => 'distribution']],
        ]);

        $this->actingAs($this->seller, 'sanctum')->getJson("/api/v1/vendor/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.carrier_handover.agency.id', $douala->id)
            ->assertJsonPath('order.carrier_handover.collected_at_shop', true)
            ->assertJsonPath('order.carrier_handover.requires_tracking_number', false);

        $this->postJson("/api/v1/vendor/orders/{$order->id}/hand-to-carrier", ['note' => 'Fragile'])
            ->assertOk()
            ->assertJsonPath('order.status', 'shipped');

        $order->refresh();
        $this->assertMatchesRegularExpression('/^SOL-'.$order->id.'-[A-Z0-9]{5}$/', $order->carrier_tracking_number);
        $event = $order->trackingEvents->firstWhere('step', 'handed_to_carrier');
        $this->assertSame('SOLEX — Douala — Akwa', $event->location);
        $this->assertStringContainsString($order->carrier_tracking_number, $event->note);
        $this->assertStringContainsString('Fragile', $event->note);
    }

    public function test_existing_number_is_kept_and_no_agency_is_tolerated(): void
    {
        $order = $this->order($this->solex(), [], ['carrier_tracking_number' => 'SOL-EXIST-1']);

        $this->actingAs($this->seller, 'sanctum')->getJson("/api/v1/vendor/orders/{$order->id}")
            ->assertJsonPath('order.carrier_handover.agency', null);
        $this->postJson("/api/v1/vendor/orders/{$order->id}/hand-to-carrier")->assertOk();

        $this->assertSame('SOL-EXIST-1', $order->fresh()->carrier_tracking_number);
    }

    public function test_online_tracking_carrier_still_requires_waybill_number(): void
    {
        $dhl = DelivererCompany::create([
            'name' => 'DHL Express', 'service_type' => DelivererCompany::SERVICE_INTERNATIONAL,
            'service_mode' => DelivererCompany::MODE_DOOR, 'is_active' => true,
            'tracking_url_template' => 'https://dhl.test/?id={number}',
        ]);
        $order = $this->order($dhl);

        $this->actingAs($this->seller, 'sanctum')->getJson("/api/v1/vendor/orders/{$order->id}")
            ->assertJsonPath('order.carrier_handover.requires_tracking_number', true);
        $this->postJson("/api/v1/vendor/orders/{$order->id}/hand-to-carrier")->assertUnprocessable();
        $this->postJson("/api/v1/vendor/orders/{$order->id}/hand-to-carrier", ['carrier_tracking_number' => ' 1234567890 '])
            ->assertOk()
            ->assertJsonPath('order.delivery.carrier_tracking_number', '1234567890');
    }
}
