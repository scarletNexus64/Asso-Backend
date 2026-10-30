<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retraits par méthode (Mobile Money / virement bancaire) avec frais et demandes manuelles.
 *
 * - wallet_balances.card_funds : part du solde DISPONIBLE encaissée par carte (Stripe).
 *   Un retrait IBAN automatique ne peut porter que sur cette part, un retrait Mobile Money
 *   automatique que sur le reste ; au-delà, la demande est traitée à la main (≈ 48 h).
 *   Un trigger la borne au solde disponible : tout débit consomme d'abord les fonds
 *   Mobile Money, quel que soit le chemin de code qui modifie le solde.
 * - payout_accounts : accepte un IBAN saisi dans l'app (banque non prise en charge par
 *   Stripe), viré à la main par ASSO.
 * - platform_withdrawals : méthode, mode (automatique / manuel), origine des fonds,
 *   suivi du traitement manuel.
 *
 * Purement additif : aucune donnée existante n'est modifiée ni supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_balances', function (Blueprint $table) {
            $table->decimal('card_funds', 15, 2)->default(0);
        });

        $this->createCardFundsTrigger();

        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->string('provider', 50)->nullable()->change();
            $table->string('phone_number', 30)->nullable()->change();
            $table->text('iban')->nullable();          // chiffré (cast encrypted)
            $table->string('iban_last4', 4)->nullable();
            $table->string('bic', 11)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('country', 2)->nullable();
            $table->boolean('stripe_supported')->default(false);
        });

        Schema::table('platform_withdrawals', function (Blueprint $table) {
            $table->string('method', 20)->nullable()->index();            // mobile_money | bank_transfer
            $table->string('processing_mode', 10)->default('automatic')->index(); // automatic | manual
            $table->string('funds_origin', 15)->nullable();                // card | mobile_money | mixed
            $table->decimal('card_funds_used', 15, 2)->default(0);
            $table->timestamp('expected_by')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('payout_reference')->nullable();
            $table->timestamp('fee_credited_at')->nullable();
        });

        DB::table('platform_withdrawals')->whereNull('method')->where('provider', 'stripe')->update(['method' => 'bank_transfer']);
        DB::table('platform_withdrawals')->whereNull('method')->where('provider', '!=', 'stripe')->update(['method' => 'mobile_money']);

        $now = now();
        foreach ([
            ['withdrawal_fee_mobile_percent', '0', 'Frais de retrait Mobile Money (%)'],
            ['withdrawal_fee_mobile_fixed', '0', 'Frais fixes de retrait Mobile Money (FCFA)'],
            ['withdrawal_fee_bank_percent', '0', 'Frais de retrait par virement bancaire (%)'],
            ['withdrawal_fee_bank_fixed', '0', 'Frais fixes de retrait par virement bancaire (FCFA)'],
            ['withdrawal_manual_delay_hours', '48', 'Délai moyen de traitement d’une demande de retrait manuelle (heures)'],
            ['min_manual_bank_withdrawal_amount', '5000', 'Minimum d’un virement bancaire manuel (FCFA)'],
        ] as [$key, $value, $description]) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'value' => $value,
                'type' => 'string',
                'group' => 'payment',
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->dropCardFundsTrigger();

        Schema::table('platform_withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['method', 'processing_mode', 'funds_origin', 'card_funds_used', 'expected_by', 'processed_at', 'payout_reference', 'fee_credited_at']);
        });

        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->dropColumn(['iban', 'iban_last4', 'bic', 'bank_name', 'country', 'stripe_supported']);
        });

        Schema::table('wallet_balances', function (Blueprint $table) {
            $table->dropColumn('card_funds');
        });

        // Les réglages restent (inoffensifs, potentiellement modifiés par l'admin).
    }

    private function createCardFundsTrigger(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION wallet_balances_clamp_card_funds() RETURNS trigger AS $$
                BEGIN
                    NEW.card_funds := LEAST(GREATEST(COALESCE(NEW.card_funds, 0), 0),
                                            GREATEST(COALESCE(NEW.balance, 0) - COALESCE(NEW.locked_balance, 0), 0));
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
                SQL);
            DB::unprepared('DROP TRIGGER IF EXISTS wallet_balances_clamp_card_funds ON wallet_balances');
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER wallet_balances_clamp_card_funds
                BEFORE INSERT OR UPDATE ON wallet_balances
                FOR EACH ROW EXECUTE FUNCTION wallet_balances_clamp_card_funds();
                SQL);
        } elseif ($driver === 'sqlite') {
            // SQLite (tests) : pas de BEFORE modifiant NEW, on corrige après coup.
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS wallet_balances_clamp_card_funds
                AFTER UPDATE ON wallet_balances
                FOR EACH ROW
                WHEN NEW.card_funds < 0 OR NEW.card_funds > MAX(NEW.balance - NEW.locked_balance, 0)
                BEGIN
                    UPDATE wallet_balances
                    SET card_funds = MIN(MAX(NEW.card_funds, 0), MAX(NEW.balance - NEW.locked_balance, 0))
                    WHERE id = NEW.id;
                END;
                SQL);
        }
    }

    private function dropCardFundsTrigger(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS wallet_balances_clamp_card_funds ON wallet_balances');
            DB::unprepared('DROP FUNCTION IF EXISTS wallet_balances_clamp_card_funds()');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS wallet_balances_clamp_card_funds');
        }
    }
};
