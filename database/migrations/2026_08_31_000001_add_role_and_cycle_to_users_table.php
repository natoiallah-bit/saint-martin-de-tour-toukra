<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['direction', 'secretariat', 'caissier', 'enseignant', 'surveillant', 'infirmerie'])
                ->default('secretariat')->after('email');
            $table->foreignId('cycle_id')->nullable()->after('role')->constrained('cycles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cycle_id');
            $table->dropColumn('role');
        });
    }
};
