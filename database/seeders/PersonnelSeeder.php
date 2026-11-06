<?php

namespace Database\Seeders;

use App\Models\Personnel;
use Illuminate\Database\Seeder;

class PersonnelSeeder extends Seeder
{
    public function run(): void
    {
        Personnel::firstOrCreate(['matricule' => 'CS-P001'], [
            'nom' => 'Ngarndigue', 'prenom' => 'Marie', 'type' => 'enseignant',
            'fonction' => 'Professeur de Mathématiques', 'salaire_base' => 120000, 'actif' => true,
        ]);
        Personnel::firstOrCreate(['matricule' => 'CS-P002'], [
            'nom' => 'Djimtoloum', 'prenom' => 'Paul', 'type' => 'administratif',
            'fonction' => 'Comptable', 'salaire_base' => 100000, 'actif' => true,
        ]);
        Personnel::firstOrCreate(['matricule' => 'CS-P003'], [
            'nom' => 'Abakar', 'prenom' => 'Ousmane', 'type' => 'securite',
            'fonction' => 'Agent de sécurité', 'salaire_base' => 60000, 'actif' => true,
        ]);
    }
}
