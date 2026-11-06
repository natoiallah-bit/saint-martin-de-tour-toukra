<?php

use App\Helpers\Droits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Modules des rapports trimestriels et annuels dans le tableau des droits :
    // volet pédagogique (Principal) et volet financier (Comptable) ; la Direction voit tout
    public function up(): void
    {
        $maintenant = now();
        foreach (['rapport_pedagogique', 'rapport_financier'] as $module) {
            foreach (array_keys(Droits::ROLES) as $role) {
                DB::table('droits_roles')->insertOrIgnore([
                    'role' => $role,
                    'module' => $module,
                    'niveau' => Droits::DEFAUTS[$role][$module] ?? Droits::AUCUN,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('droits_roles')->whereIn('module', ['rapport_pedagogique', 'rapport_financier'])->delete();
    }
};
