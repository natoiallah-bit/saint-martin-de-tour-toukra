<?php

namespace App\Helpers;

use App\Models\ConfigurationEcole;
use App\Models\EmploiTemps;
use App\Models\Presence;
use Carbon\Carbon;

/**
 * Absences et retards d'un trimestre, calculés à partir de l'appel (page Présences).
 *
 * L'appel se fait à chaque cours : les heures d'absence sont la somme de la durée
 * des cours manqués (durée enregistrée au moment de l'appel). Une absence notée
 * pour toute la journée compte toutes les heures de cours prévues ce jour-là dans
 * l'emploi du temps de la classe. Les dates du trimestre sont réglées dans Paramètres.
 */
class Assiduite
{
    protected const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi'];

    // [eleve_id => ['heures' => ?float, 'jours' => int, 'retards' => int]] pour toute une classe,
    // ou null si les dates du trimestre ne sont pas réglées
    public static function pourClasse(int $classeId, int $trimestre, ?array $eleveIds = null): ?array
    {
        $periode = ConfigurationEcole::periodeTrimestre($trimestre);
        if (! $periode) {
            return null;
        }

        // Heures de cours de la classe pour chaque jour de la semaine (emploi du temps)
        $heuresParJour = EmploiTemps::where('classe_id', $classeId)->get()
            ->groupBy('jour')
            ->map(fn ($creneaux) => $creneaux->sum(fn ($c) => Carbon::parse($c->heure_debut)->diffInMinutes(Carbon::parse($c->heure_fin)) / 60));
        $avecEmploiDuTemps = $heuresParJour->isNotEmpty();

        $presences = Presence::where('classe_id', $classeId)
            ->whereDate('date', '>=', $periode[0]->toDateString())
            ->whereDate('date', '<=', $periode[1]->toDateString())
            ->whereIn('statut', ['absent', 'retard'])
            ->when($eleveIds !== null, fn ($q) => $q->whereIn('eleve_id', $eleveIds))
            ->get()
            ->groupBy('eleve_id');

        $resultat = [];
        foreach ($presences as $eleveId => $lignes) {
            $absences = $lignes->where('statut', 'absent');
            $parJour = $absences->groupBy(fn ($p) => $p->date->toDateString());

            // Heures manquées : toute la journée si une absence "journée" a été notée,
            // sinon la somme des cours manqués ce jour-là
            $heures = 0;
            $calculable = true;
            foreach ($parJour as $absencesDuJour) {
                $journee = $absencesDuJour->first(fn ($p) => $p->duree_minutes === null);
                if ($journee) {
                    $calculable = $calculable && $avecEmploiDuTemps;
                    $heures += $heuresParJour->get(self::JOURS[$journee->date->dayOfWeekIso] ?? '', 0);
                } else {
                    $heures += $absencesDuJour->sum('duree_minutes') / 60;
                }
            }

            $resultat[$eleveId] = [
                'jours' => $parJour->count(),
                'heures' => $calculable ? round($heures, 2) : null,
                'retards' => $lignes->where('statut', 'retard')->count(),
            ];
        }

        return $resultat;
    }

    // Absences et retards d'un élève (zéro s'il n'a jamais été noté absent ou en retard)
    public static function pourEleve(int $eleveId, int $classeId, int $trimestre): ?array
    {
        $classe = self::pourClasse($classeId, $trimestre, [$eleveId]);

        if ($classe === null) {
            return null;
        }

        $avecEmploiDuTemps = EmploiTemps::where('classe_id', $classeId)->exists();

        return $classe[$eleveId] ?? ['jours' => 0, 'heures' => $avecEmploiDuTemps ? 0.0 : null, 'retards' => 0];
    }
}
