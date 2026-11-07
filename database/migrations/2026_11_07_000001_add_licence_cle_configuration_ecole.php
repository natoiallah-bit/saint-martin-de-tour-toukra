<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Clé d'activation enregistrée par la Direction (page Licence)
    public function up(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->text('licence_cle')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->dropColumn('licence_cle');
        });
    }
};
