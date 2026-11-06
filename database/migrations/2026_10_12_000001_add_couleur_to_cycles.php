<?php

use App\Models\Cycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Couleur des cartes de chaque cycle (primaire, collège, lycée…), réglable dans Paramètres
        Schema::table('cycles', function (Blueprint $table) {
            $table->string('couleur', 7)->nullable()->after('nom');
        });

        foreach (DB::table('cycles')->get() as $cycle) {
            DB::table('cycles')->where('id', $cycle->id)->update(['couleur' => Cycle::couleurParDefaut($cycle->nom)]);
        }
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropColumn('couleur');
        });
    }
};
