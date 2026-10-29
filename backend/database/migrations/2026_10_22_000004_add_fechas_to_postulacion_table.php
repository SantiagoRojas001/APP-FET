<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Momento de cada decisión, para medir cuánto tarda cada etapa.
     */
    public function up(): void
    {
        Schema::table('postulacion', function (Blueprint $table) {
            $table->timestampTz('revisada_en')->nullable();
            $table->timestampTz('decidida_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('postulacion', function (Blueprint $table) {
            $table->dropColumn(['revisada_en', 'decidida_en']);
        });
    }
};
