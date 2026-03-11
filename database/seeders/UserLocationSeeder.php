<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Ajout de coordonnées GPS aux utilisateurs (Cameroun)...');

        // Zones du Cameroun - Yaoundé et Douala
        $zones = [
            // Yaoundé (Capitale)
            ['name' => 'Centre-ville Yaoundé', 'lat' => 3.8480, 'lng' => 11.5021, 'ville' => 'Yaoundé'],
            ['name' => 'Bastos', 'lat' => 3.8850, 'lng' => 11.5180, 'ville' => 'Yaoundé'],
            ['name' => 'Nlongkak', 'lat' => 3.8650, 'lng' => 11.5250, 'ville' => 'Yaoundé'],
            ['name' => 'Mfoundi', 'lat' => 3.8520, 'lng' => 11.4980, 'ville' => 'Yaoundé'],
            ['name' => 'Ngoa-Ekele', 'lat' => 3.8380, 'lng' => 11.4850, 'ville' => 'Yaoundé'],
            ['name' => 'Emana', 'lat' => 3.8280, 'lng' => 11.5120, 'ville' => 'Yaoundé'],
            ['name' => 'Essos', 'lat' => 3.8620, 'lng' => 11.5380, 'ville' => 'Yaoundé'],
            ['name' => 'Mvog-Ada', 'lat' => 3.8420, 'lng' => 11.5280, 'ville' => 'Yaoundé'],

            // Douala (Ville économique)
            ['name' => 'Akwa Douala', 'lat' => 4.0511, 'lng' => 9.7679, 'ville' => 'Douala'],
            ['name' => 'Bonanjo', 'lat' => 4.0483, 'lng' => 9.6950, 'ville' => 'Douala'],
            ['name' => 'Bonapriso', 'lat' => 4.0580, 'lng' => 9.7020, 'ville' => 'Douala'],
            ['name' => 'Deido', 'lat' => 4.0680, 'lng' => 9.7180, 'ville' => 'Douala'],
            ['name' => 'Bali', 'lat' => 4.0420, 'lng' => 9.7420, 'ville' => 'Douala'],
            ['name' => 'New Bell', 'lat' => 4.0550, 'lng' => 9.7350, 'ville' => 'Douala'],
            ['name' => 'Bonaberi', 'lat' => 4.0780, 'lng' => 9.6850, 'ville' => 'Douala'],
            ['name' => 'Makepe', 'lat' => 4.0650, 'lng' => 9.7580, 'ville' => 'Douala'],
        ];

        $adressesYaounde = [
            'Avenue Kennedy',
            'Boulevard du 20 Mai',
            'Rue Joseph Essono Balla',
            'Avenue Foch',
            'Rue de Nachtigal',
            'Boulevard de la Réunification',
            'Rue Manga Bell',
            'Avenue Charles Atangana',
        ];

        $adressesDouala = [
            'Boulevard de la Liberté',
            'Rue Joffre',
            'Avenue de Gaulle',
            'Rue des Cocotiers',
            'Boulevard de la République',
            'Rue Franqueville',
            'Avenue Ahidjo',
            'Rue King Akwa',
        ];

        $users = User::all();
        $updated = 0;

        foreach ($users as $user) {
            // Sélectionner une zone aléatoire
            $zone = $zones[array_rand($zones)];

            // Ajouter une variation aléatoire autour de la zone (environ 1-2 km)
            $latVariation = (rand(-100, 100) / 10000); // ±0.01 degrés
            $lngVariation = (rand(-100, 100) / 10000);

            $latitude = $zone['lat'] + $latVariation;
            $longitude = $zone['lng'] + $lngVariation;

            // Générer une adresse selon la ville
            $adresses = $zone['ville'] === 'Yaoundé' ? $adressesYaounde : $adressesDouala;
            $adresse = $adresses[array_rand($adresses)] . ', ' . $zone['name'] . ', ' . $zone['ville'] . ', Cameroun';

            // Mettre à jour l'utilisateur
            $user->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $adresse,
                'country' => 'Cameroun',
            ]);

            $updated++;
        }

        $this->command->info('✅ ' . $updated . ' utilisateurs mis à jour avec des coordonnées GPS');
        $this->command->info('');

        // Afficher les statistiques
        $vendeurs = User::where('role', 'vendeur')->whereNotNull('latitude')->count();
        $acheteurs = User::where('role', 'acheteur')->whereNotNull('latitude')->count();
        $livreurs = User::where('role', 'livreur')->whereNotNull('latitude')->count();

        $this->command->info('Statistiques:');
        $this->command->info('- Vendeurs avec GPS: ' . $vendeurs);
        $this->command->info('- Acheteurs avec GPS: ' . $acheteurs);
        $this->command->info('- Livreurs avec GPS: ' . $livreurs);
    }
}
