<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ajouter la colonne roles (TEXT pour SQLite, JSON pour autres)
        Schema::table('users', function (Blueprint $table) {
            $table->text('roles')->nullable()->after('role');
        });

        // Migrer les données existantes : convertir role en roles array
        // Pour SQLite, on stocke le JSON comme TEXT
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['roles' => json_encode([$user->role])]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
