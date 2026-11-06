<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\Matiere;
use Illuminate\Database\Seeder;

class MatiereSeeder extends Seeder
{
    // Ce seeder crée uniquement les NOMS des matières avec un coefficient
    // par défaut de 1 et une catégorie de base. Le directeur doit ensuite
    // ajuster coefficient, catégorie et enseignant via l'écran "Matières"
    // de l'application — ce ne sont pas des vraies données de l'école.
    public function run(): void
    {
        $matieresParCycle = [
            'Primaire' => [
                ['nom' => 'Mathématiques', 'categorie' => 'Scientifique'],
                ['nom' => 'Éveil scientifique', 'categorie' => 'Scientifique'],
                ['nom' => 'Rédaction', 'categorie' => 'Littéraire'],
                ['nom' => 'Dictée-Question', 'categorie' => 'Littéraire'],
                ['nom' => 'Histoire', 'categorie' => 'Littéraire'],
                ['nom' => 'Anglais', 'categorie' => 'Littéraire'],
                ['nom' => 'Informatique', 'categorie' => 'Accessoire'],
                ['nom' => 'Instruction Civique', 'categorie' => 'Accessoire'],
                ['nom' => 'Art', 'categorie' => 'Accessoire'],
                ['nom' => 'EPS', 'categorie' => 'Accessoire'],
                ['nom' => 'Conduite', 'categorie' => 'Accessoire'],
            ],
            'Collège' => [
                ['nom' => 'Mathématiques', 'categorie' => 'Scientifique'],
                ['nom' => 'Physique-Chimie', 'categorie' => 'Scientifique'],
                ['nom' => 'SVT', 'categorie' => 'Scientifique'],
                ['nom' => 'Géographie', 'categorie' => 'Scientifique'],
                ['nom' => 'Rédaction', 'categorie' => 'Littéraire'],
                ['nom' => 'Dictée-Question', 'categorie' => 'Littéraire'],
                ['nom' => 'Histoire', 'categorie' => 'Littéraire'],
                ['nom' => 'Anglais', 'categorie' => 'Littéraire'],
                ['nom' => 'Arabe', 'categorie' => 'Littéraire'],
                ['nom' => 'Informatique', 'categorie' => 'Accessoire'],
                ['nom' => 'Instruction Civique', 'categorie' => 'Accessoire'],
                ['nom' => 'Musique', 'categorie' => 'Accessoire'],
                ['nom' => 'Art', 'categorie' => 'Accessoire'],
                ['nom' => 'EPS', 'categorie' => 'Accessoire'],
                ['nom' => 'Conduite', 'categorie' => 'Accessoire'],
            ],
        ];

        foreach ($matieresParCycle as $nomCycle => $matieres) {
            $cycle = Cycle::where('nom', $nomCycle)->first();
            if (!$cycle) continue;

            foreach ($matieres as $m) {
                Matiere::updateOrCreate(
                    ['cycle_id' => $cycle->id, 'nom' => $m['nom']],
                    ['categorie' => $m['categorie'], 'coefficient' => 1] // coefficient=1 par défaut, à ajuster
                );
            }
        }
    }
}
