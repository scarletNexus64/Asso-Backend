<?php

use App\Support\LocationFormatter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ville et pays structurés pour afficher « Ville, Pays » au lieu d'une adresse libre.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('city', 120)->nullable()->after('address');
            $table->string('country', 120)->nullable()->after('city');
        });

        // Récupère ce qui peut l'être depuis les adresses déjà enregistrées.
        DB::table('shops')->whereNotNull('address')->orderBy('id')->each(function ($shop) {
            [$city, $country] = LocationFormatter::parse($shop->address);
            if ($city !== null || $country !== null) {
                DB::table('shops')->where('id', $shop->id)->update(['city' => $city, 'country' => $country]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['city', 'country']);
        });
    }
};
