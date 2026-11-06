<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\Ecole;
use App\Models\Matiere;
use Illuminate\Database\Seeder;

// Nouvelle installation : l'école, ses cycles et les matières de base, pour pouvoir
// créer tout de suite les classes et inscrire les élèves. Ne touche à rien si l'école
// a déjà ses cycles ou ses matières (relancer installer.bat ne change rien).
class StructureEcoleSeeder extends Seeder
{
    public function run(): void
    {
        $ecole = Ecole::first() ?? Ecole::create([
            'nom' => 'Complexe Scolaire Saint Martin de Tour de Toukra',
            'ville' => "N'Djamena",
            'couleur_principale' => '#1b2749',
        ]);

        if (! Cycle::exists()) {
            foreach (['Maternelle', 'Primaire', 'Collège', 'Lycée'] as $nom) {
                Cycle::create(['ecole_id' => $ecole->id, 'nom' => $nom]);
            }
        }

        if (! Matiere::exists()) {
            $this->call(MatiereSeeder::class);
        }
    }
}
