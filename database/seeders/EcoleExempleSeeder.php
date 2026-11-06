<?php

namespace Database\Seeders;

use App\Models\ClasseScolaire;
use App\Models\Cycle;
use App\Models\Ecole;
use App\Models\Eleve;
use Illuminate\Database\Seeder;

class EcoleExempleSeeder extends Seeder
{
    public function run(): void
    {
        $ecole = Ecole::create([
            'nom' => 'Complexe Scolaire Saint Martin de Tour de Toukra',
            'ville' => "N'Djamena",
            'couleur_principale' => '#1b2749',
        ]);

        $primaire = Cycle::create(['ecole_id' => $ecole->id, 'nom' => 'Primaire']);
        $college = Cycle::create(['ecole_id' => $ecole->id, 'nom' => 'Collège']);

        $cp1 = ClasseScolaire::create(['cycle_id' => $primaire->id, 'nom' => 'CP1']);
        $cm2 = ClasseScolaire::create(['cycle_id' => $primaire->id, 'nom' => 'CM2']);
        $sixieme = ClasseScolaire::create(['cycle_id' => $college->id, 'nom' => '6ème']);

        Eleve::create([
            'classe_id' => $cp1->id,
            'nom' => 'Abakar',
            'prenom' => 'Aïcha',
            'sexe' => 'F',
        ]);

        Eleve::create([
            'classe_id' => $cm2->id,
            'nom' => 'Moussa',
            'prenom' => 'Idriss',
            'sexe' => 'M',
        ]);

        Eleve::create([
            'classe_id' => $sixieme->id,
            'nom' => 'Djimet',
            'prenom' => 'Fatimé',
            'sexe' => 'F',
        ]);
    }
}
