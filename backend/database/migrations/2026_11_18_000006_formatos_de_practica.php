<?php

use App\Enums\TipoFormato;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formatos institucionales de cada práctica, con sus firmas; tutor de la
     * empresa, y SNIES y resolución del programa para la carta de presentación.
     */
    public function up(): void
    {
        Schema::create('formato_practica', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vinculacion_id');
            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->jsonb('datos')->default('{}');
            $table->jsonb('firmas')->default('{}');
            $table->timestampTz('actualizado_en')->default(DB::raw('now()'));
            $table->unique(['vinculacion_id', 'tipo']);
        });
        Restricciones::valoresPermitidos('formato_practica', 'tipo', TipoFormato::values());

        Schema::table('vinculacion', function (Blueprint $table) {
            $table->jsonb('tutor_empresa')->nullable();
        });

        Schema::table('programa', function (Blueprint $table) {
            $table->string('snies', 20)->nullable();
            $table->string('resolucion', 120)->nullable();
        });
        DB::table('programa')->where('nombre', 'Ingeniería de Software')->update([
            'snies' => '103582',
            'resolucion' => 'MEN 3317 de marzo 14/2014',
        ]);
    }

    public function down(): void
    {
        Schema::table('programa', function (Blueprint $table) {
            $table->dropColumn(['snies', 'resolucion']);
        });
        Schema::table('vinculacion', function (Blueprint $table) {
            $table->dropColumn('tutor_empresa');
        });
        Schema::dropIfExists('formato_practica');
    }
};
