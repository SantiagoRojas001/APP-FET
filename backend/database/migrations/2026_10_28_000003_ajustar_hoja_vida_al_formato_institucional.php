<?php

use App\Enums\NivelFormacion;
use App\Enums\TipoInvestigacion;
use App\Enums\TipoReferencia;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hoja de vida con los apartados del formato PA-GTH-F-10 de la FET: datos
     * personales y foto, formación académica, cursos, experiencia laboral
     * detallada, experiencia investigativa y referencias. La formación como
     * texto libre se reemplaza por títulos estructurados.
     */
    public function up(): void
    {
        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->string('foto_url', 255)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('lugar_nacimiento', 100)->nullable();
            $table->string('estado_civil', 20)->nullable();
            $table->string('lugar_residencia', 100)->nullable();
            $table->string('direccion', 150)->nullable();
            $table->string('rh', 3)->nullable();
            $table->string('contacto_emergencia_nombre', 120)->nullable();
            $table->string('contacto_emergencia_telefono', 20)->nullable();
            $table->dropColumn('formacion');
        });

        Schema::table('experiencia', function (Blueprint $table) {
            $table->string('direccion', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('jefe_inmediato', 120)->nullable();
            $table->string('dedicacion', 20)->nullable();
        });

        Schema::create('formacion_academica', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('hoja_vida_id');
            $table->string('nivel', 15);
            $table->string('titulo', 150);
            $table->string('institucion', 150);
            $table->string('pais_ciudad', 100);
            $table->date('fecha_grado')->nullable();
            $table->string('modalidad', 20)->nullable();

            $table->foreign('hoja_vida_id')->references('id')->on('hoja_vida')->cascadeOnDelete();
        });
        Restricciones::valoresPermitidos('formacion_academica', 'nivel', NivelFormacion::values());

        Schema::create('curso', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('hoja_vida_id');
            $table->string('tema', 150);
            $table->smallInteger('duracion_horas');
            $table->date('fecha');
            $table->string('institucion', 150);
            $table->string('modalidad', 20);

            $table->foreign('hoja_vida_id')->references('id')->on('hoja_vida')->cascadeOnDelete();
        });

        Schema::create('actividad_investigativa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('hoja_vida_id');
            $table->string('tipo', 20);
            $table->string('descripcion', 1000);
            $table->string('periodo', 60)->nullable();

            $table->foreign('hoja_vida_id')->references('id')->on('hoja_vida')->cascadeOnDelete();
        });
        Restricciones::valoresPermitidos('actividad_investigativa', 'tipo', TipoInvestigacion::values());

        Schema::create('referencia', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('hoja_vida_id');
            $table->string('tipo', 10);
            $table->string('nombre', 120);
            $table->string('ocupacion', 100);
            $table->string('parentesco', 50)->nullable();
            $table->string('telefono', 20);

            $table->foreign('hoja_vida_id')->references('id')->on('hoja_vida')->cascadeOnDelete();
        });
        Restricciones::valoresPermitidos('referencia', 'tipo', TipoReferencia::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('referencia');
        Schema::dropIfExists('actividad_investigativa');
        Schema::dropIfExists('curso');
        Schema::dropIfExists('formacion_academica');

        Schema::table('experiencia', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'telefono', 'jefe_inmediato', 'dedicacion']);
        });

        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->dropColumn([
                'foto_url', 'fecha_nacimiento', 'lugar_nacimiento', 'estado_civil', 'lugar_residencia',
                'direccion', 'rh', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
            ]);
            $table->text('formacion')->nullable();
        });
    }
};
