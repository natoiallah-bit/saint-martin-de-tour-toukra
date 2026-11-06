<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'enseignant_id')) {
                $table->foreignId('enseignant_id')->nullable()->after('cycle_id')
                    ->constrained('enseignants')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'personnel_id')) {
                $table->foreignId('personnel_id')->nullable()->after('enseignant_id')
                    ->constrained('personnels')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'enseignant_id')) {
                $table->dropConstrainedForeignId('enseignant_id');
            }
            if (Schema::hasColumn('users', 'personnel_id')) {
                $table->dropConstrainedForeignId('personnel_id');
            }
        });
    }
};
