<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Valeurs par défaut héritées du Bénin : un compte créé sans pays devenait
 * « Bénin » et une transaction sans devise « XOF ». ASSO est au Cameroun (XAF).
 *
 * country_inferred : le pays du compte a été déduit (pas choisi dans l'app) ;
 * l'app peut alors le remplacer par le pays choisi à l'accueil.
 * Aucune ligne existante n'est modifiée ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'country_inferred')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('country_inferred')->default(false);
            });
        }
        if (DB::getDriverName() !== 'pgsql') {
            return; // SQLite (tests) ne sait pas modifier un DEFAULT.
        }
        DB::statement("ALTER TABLE users ALTER COLUMN country SET DEFAULT 'Cameroun'");
        DB::statement("ALTER TABLE transactions ALTER COLUMN currency SET DEFAULT 'XAF'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN country SET DEFAULT 'Bénin'");
            DB::statement("ALTER TABLE transactions ALTER COLUMN currency SET DEFAULT 'XOF'");
        }
        if (Schema::hasColumn('users', 'country_inferred')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('country_inferred');
            });
        }
    }
};
