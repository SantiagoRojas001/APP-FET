<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El director asigna a cada estudiante su docente tutor y define los
     * cupos de pasantía de su programa en cada periodo.
     */
    public function up(): void
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->foreignId('tutor_id')->nullable()->constrained('usuario')->nullOnDelete();
        });

        Schema::create('cupo_pasantia', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('programa_id')->constrained('programa')->cascadeOnDelete();
            $table->string('periodo', 6);
            $table->smallInteger('tecnico')->default(5);
            $table->smallInteger('tecnologo')->default(5);
            $table->unique(['programa_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupo_pasantia');

        Schema::table('estudiante', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tutor_id');
        });
    }
};
