<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passages_infirmerie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->nullable()->constrained('eleves')->nullOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->dateTime('date_passage');
            $table->string('motif'); // Maux de tête, fièvre, blessure...
            $table->text('soins_donnes')->nullable();
            $table->boolean('renvoye_maison')->default(false);
            $table->boolean('parent_contacte')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passages_infirmerie');
    }
};
