<?php

namespace Tests\Feature;

use App\Models\ClasseScolaire;
use App\Models\Cycle;
use App\Models\Ecole;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\User;

// Petites fonctions pour créer rapidement les données des tests
trait Fabrique
{
    protected function utilisateur(string $role, ?Cycle $cycle = null): User
    {
        // Un comptable travaille toujours pour un cycle : à défaut, celui des données déjà créées
        if ($role === 'comptable' && ! $cycle) {
            $cycle = Cycle::first() ?? $this->cycle();
        }

        return User::create([
            'name' => ucfirst($role),
            'email' => $role . uniqid() . '@test.td',
            'password' => 'secret123',
            'role' => $role,
            'cycle_id' => $cycle?->id,
        ]);
    }

    protected function cycle(string $nom = 'Primaire'): Cycle
    {
        $ecole = Ecole::first() ?? Ecole::create(['nom' => 'Saint Martin de Tour']);

        return Cycle::create(['ecole_id' => $ecole->id, 'nom' => $nom]);
    }

    protected function eleve(?Cycle $cycle = null, string $nom = 'Doe'): Eleve
    {
        $cycle ??= $this->cycle();
        $classe = ClasseScolaire::firstOrCreate(['cycle_id' => $cycle->id, 'nom' => 'Classe ' . $cycle->nom]);

        return Eleve::create([
            'classe_id' => $classe->id,
            'matricule' => 'ELV-' . uniqid(),
            'nom' => $nom,
            'prenom' => 'Test',
        ]);
    }

    protected function paiement(Eleve $eleve): Paiement
    {
        return Paiement::create([
            'eleve_id' => $eleve->id,
            'annee_scolaire' => \App\Models\ConfigurationEcole::anneeActive(),
            'types' => ['tranche1'],
            'montant' => 10000,
            'mode_paiement' => 'especes',
            'date_paiement' => now()->toDateString(),
            'numero_recu' => Paiement::genererNumeroRecu(),

        ]);
    }

    protected function parentDe(Eleve ...$enfants): User
    {
        $parent = $this->utilisateur('parent');
        $parent->enfants()->attach(collect($enfants)->pluck('id'));

        return $parent;
    }
}
