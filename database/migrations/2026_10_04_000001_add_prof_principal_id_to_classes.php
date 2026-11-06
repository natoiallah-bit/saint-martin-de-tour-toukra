<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            if (! Schema::hasColumn('classes', 'prof_principal_id')) {
                $table->foreignId('prof_principal_id')->nullable()->after('prof_principal')
                    ->constrained('enseignants')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            if (Schema::hasColumn('classes', 'prof_principal_id')) {
                $table->dropConstrainedForeignId('prof_principal_id');
            }
        });
    }
};
