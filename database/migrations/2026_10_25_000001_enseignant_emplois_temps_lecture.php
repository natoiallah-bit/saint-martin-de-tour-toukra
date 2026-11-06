<?php

use App\Helpers\Droits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Un enseignant consulte les emplois du temps sans les modifier (sauf si la Direction a déjà changé ce réglage)
    public function up(): void
    {
        DB::table('droits_roles')->where('role', 'enseignant')->where('module', 'emplois_temps')
            ->where('niveau', Droits::SUPPRIMER)->update(['niveau' => Droits::LECTURE, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('droits_roles')->where('role', 'enseignant')->where('module', 'emplois_temps')
            ->where('niveau', Droits::LECTURE)->update(['niveau' => Droits::SUPPRIMER, 'updated_at' => now()]);
    }
};
