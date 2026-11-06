<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->json('types')->nullable()->after('eleve_id');
        });

        // Migre les anciennes valeurs 'type' vers le nouveau tableau 'types'
        \DB::table('paiements')->whereNotNull('type')->get()->each(function ($p) {
            \DB::table('paiements')->where('id', $p->id)->update([
                'types' => json_encode([$p->type]),
            ]);
        });

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('type')->nullable()->after('eleve_id');
        });
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('types');
        });
    }
};
