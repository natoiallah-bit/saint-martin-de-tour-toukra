<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (! Schema::hasColumn('notes', 'devoir1')) {
                $table->decimal('devoir1', 4, 2)->nullable()->after('matiere_id');
            }
            if (! Schema::hasColumn('notes', 'devoir2')) {
                $table->decimal('devoir2', 4, 2)->nullable()->after('devoir1');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (Schema::hasColumn('notes', 'devoir1')) $table->dropColumn('devoir1');
            if (Schema::hasColumn('notes', 'devoir2')) $table->dropColumn('devoir2');
        });
    }
};
