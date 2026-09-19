<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4 — Livraison et partenaires logistiques.
 *
 * - Transporteurs (SOLEX interurbain, DHL/FedEx international) : type de service,
 *   mode (domicile / agence), TVA, conditions et lien de suivi sur l'entreprise.
 * - Grilles par trajet (`delivery_routes`) : tranches de poids + prix du kg supplémentaire.
 * - Commande : poids expédié, détail du prix figé, numéro de suivi transporteur.
 * - Historique daté des étapes de livraison (`order_tracking_events`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverer_companies', function (Blueprint $table) {
            // local = livreurs de quartier (zones) ; intercity = SOLEX ; international = DHL, FedEx
            $table->string('service_type', 20)->default('local')->after('description');
            // door_to_door = livré à l'adresse ; agency_to_agency = dépôt et retrait en agence
            $table->string('service_mode', 20)->default('door_to_door')->after('service_type');
            // Grille exprimée hors taxe : la TVA (Setting delivery_vat_rate) est ajoutée.
            $table->boolean('prices_exclude_vat')->default(false)->after('service_mode');
            $table->text('conditions')->nullable()->after('prices_exclude_vat');
            $table->decimal('max_weight_kg', 10, 3)->nullable()->after('conditions');
            // Lien public de suivi, {number} est remplacé par le numéro du transporteur.
            $table->string('tracking_url_template')->nullable()->after('max_weight_kg');
        });

        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverer_company_id')->constrained('deliverer_companies')->cascadeOnDelete();
            $table->string('origin_country', 2);
            $table->string('origin_city')->nullable();       // null = toutes les villes du pays
            $table->string('destination_country', 2);
            $table->string('destination_city')->nullable();  // null = toutes les villes du pays
            $table->boolean('bidirectional')->default(true); // « et vice versa »
            // {"ranges":[{"min":0,"max":2,"price":2500,"label":"Plis & paquets"}], "extra_per_kg":150}
            $table->json('pricing_data');
            $table->decimal('asso_commission', 12, 2)->default(0);
            $table->string('lead_time')->nullable();         // « 24 h », « 3 à 5 jours »
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['origin_country', 'destination_country']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_mode', 20)->default('local')->after('delivery_zone_id');
            $table->foreignId('delivery_route_id')->nullable()->after('delivery_mode')
                ->constrained('delivery_routes')->nullOnDelete();
            $table->decimal('shipping_weight_kg', 10, 3)->nullable()->after('delivery_route_id');
            $table->decimal('delivery_vat_amount', 12, 2)->default(0)->after('delivery_commission');
            $table->json('delivery_breakdown')->nullable()->after('delivery_vat_amount');
            $table->string('carrier_tracking_number')->nullable()->after('tracking_number');
            $table->string('tracking_status', 30)->nullable()->after('status');
        });

        // Import Étranger → Cameroun : transporteur de l'option (DHL, FedEx…) et TVA.
        Schema::table('import_shipping_options', function (Blueprint $table) {
            $table->string('carrier')->nullable()->after('mode');
            $table->string('tracking_url_template')->nullable()->after('carrier');
        });

        Schema::create('order_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('step', 30);
            $table->string('label');
            $table->string('location')->nullable();
            $table->text('note')->nullable();
            $table->string('actor_type', 20)->default('system'); // system|buyer|vendor|deliverer|admin
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking_events');

        Schema::table('import_shipping_options', function (Blueprint $table) {
            $table->dropColumn(['carrier', 'tracking_url_template']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_route_id');
            $table->dropColumn([
                'delivery_mode', 'shipping_weight_kg', 'delivery_vat_amount',
                'delivery_breakdown', 'carrier_tracking_number', 'tracking_status',
            ]);
        });

        Schema::dropIfExists('delivery_routes');

        Schema::table('deliverer_companies', function (Blueprint $table) {
            $table->dropColumn([
                'service_type', 'service_mode', 'prices_exclude_vat',
                'conditions', 'max_weight_kg', 'tracking_url_template',
            ]);
        });
    }
};
