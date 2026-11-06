<?php

use App\Models\ClasseScolaire;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ordre des classes dans toutes les listes : cycle (maternelle → lycée) puis niveau
        // (CP1 → CM2, 6e → 3e…). Recalculé automatiquement quand une classe est enregistrée.
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedSmallInteger('ordre')->default(9999)->after('nom');
        });

        $cycles = DB::table('cycles')->pluck('nom', 'id');
        foreach (DB::table('classes')->get() as $classe) {
            DB::table('classes')->where('id', $classe->id)
                ->update(['ordre' => ClasseScolaire::calculerOrdre($classe->nom, $cycles[$classe->cycle_id] ?? null)]);
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('ordre');
        });
    }
};
