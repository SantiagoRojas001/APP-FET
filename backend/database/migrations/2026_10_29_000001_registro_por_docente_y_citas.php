<?php

use App\Enums\EstadoAcuerdo;
use App\Enums\EstadoCita;
use App\Enums\EstadoHojaVida;
use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nuevo flujo: el docente registra a los estudiantes que eligieron
     * pasantía o práctica y su promedio decide si quedan habilitados. Ya no
     * hay postulaciones: el docente revisa la hoja de vida (la aprueba o la
     * devuelve con recomendaciones), las empresas con acuerdo aprobado ven
     * las aprobadas y asignan citas, y según la cita aceptan o rechazan al
     * estudiante. Si lo aceptan, el director de programa envía la carta de
     * presentación. Las vacantes las ven los docentes para recomendar
     * estudiantes. Los parámetros (promedio mínimo y semestres habilitados)
     * los define la administración y reemplazan la regla de semestre mínimo.
     */
    public function up(): void
    {
        Restricciones::reemplazarValores('usuario', 'rol', Rol::values());
        Schema::table('usuario', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false);
            $table->integer('programa_id')->nullable();
            $table->foreign('programa_id')->references('id')->on('programa')->nullOnDelete();
        });

        Schema::table('estudiante', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->change();
            $table->string('documento', 20)->nullable()->change();
            $table->string('modalidad_grado', 25)->nullable();
            $table->decimal('promedio', 3, 2)->nullable();
            $table->boolean('habilitado')->nullable();
            $table->timestampTz('resultado_enviado_en')->nullable();
            $table->integer('registrado_por')->nullable();
            $table->foreign('registrado_por')->references('id')->on('usuario')->nullOnDelete();
        });
        Restricciones::valoresPermitidos('estudiante', 'modalidad_grado', Modalidad::values());
        DB::statement('ALTER TABLE estudiante ADD CONSTRAINT estudiante_promedio_check CHECK (promedio >= 0 AND promedio <= 5)');
        // Quienes ya tenían cuenta conservan su acceso.
        DB::table('estudiante')->update(['habilitado' => true]);

        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->string('estado', 20)->default(EstadoHojaVida::Borrador->value);
            $table->text('observaciones')->nullable();
            $table->timestampTz('enviada_en')->nullable();
            $table->integer('revisada_por')->nullable();
            $table->timestampTz('revisada_en')->nullable();
            $table->foreign('revisada_por')->references('id')->on('usuario')->nullOnDelete();
        });
        Restricciones::valoresPermitidos('hoja_vida', 'estado', EstadoHojaVida::values());

        Schema::create('parametro', function (Blueprint $table) {
            $table->string('clave', 40)->primary();
            $table->string('valor', 255);
        });
        DB::table('parametro')->insert([
            ['clave' => 'promedio_minimo', 'valor' => '4.2'],
            ['clave' => 'semestres_habilitados', 'valor' => '3,5,9'],
        ]);

        Schema::create('acuerdo', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('empresa_id');
            $table->string('archivo_url', 255);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fin');
            $table->string('estado', 10)->default(EstadoAcuerdo::Pendiente->value);
            $table->text('observacion')->nullable();
            $table->timestampTz('cargado_en')->useCurrent();
            $table->integer('revisado_por')->nullable();
            $table->timestampTz('revisado_en')->nullable();

            $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
            $table->foreign('revisado_por')->references('id')->on('usuario')->nullOnDelete();
        });
        Restricciones::valoresPermitidos('acuerdo', 'estado', EstadoAcuerdo::values());

        Schema::create('recomendacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vacante_id');
            $table->integer('estudiante_id');
            $table->integer('docente_id');
            $table->string('comentario', 500)->nullable();
            $table->timestampTz('creada_en')->useCurrent();

            $table->foreign('vacante_id')->references('id')->on('vacante')->cascadeOnDelete();
            $table->foreign('estudiante_id')->references('id')->on('estudiante')->cascadeOnDelete();
            $table->foreign('docente_id')->references('id')->on('usuario')->cascadeOnDelete();
            $table->unique(['vacante_id', 'estudiante_id']);
        });

        Schema::create('cita', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('empresa_id');
            $table->integer('estudiante_id');
            $table->integer('vacante_id')->nullable();
            $table->timestampTz('fecha_hora');
            $table->string('direccion', 200);
            $table->string('indicaciones', 1000)->nullable();
            $table->string('estado', 15)->default(EstadoCita::Programada->value);
            $table->text('observacion')->nullable();
            $table->date('fecha_inicio_practica')->nullable();
            $table->timestampTz('creada_en')->useCurrent();
            $table->timestampTz('decidida_en')->nullable();
            $table->integer('presentada_por')->nullable();
            $table->timestampTz('presentada_en')->nullable();
            $table->integer('vinculacion_id')->nullable();

            $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
            $table->foreign('estudiante_id')->references('id')->on('estudiante')->cascadeOnDelete();
            $table->foreign('vacante_id')->references('id')->on('vacante')->nullOnDelete();
            $table->foreign('presentada_por')->references('id')->on('usuario')->nullOnDelete();
            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->nullOnDelete();
        });
        Restricciones::valoresPermitidos('cita', 'estado', EstadoCita::values());

        Schema::dropIfExists('postulacion');
        Schema::dropIfExists('regla_elegibilidad');
    }

    public function down(): void
    {
        Schema::create('regla_elegibilidad', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('programa_id');
            $table->smallInteger('semestre_minimo');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->foreign('programa_id')->references('id')->on('programa')->cascadeOnDelete();
        });
        Schema::create('postulacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vacante_id');
            $table->integer('estudiante_id');
            $table->string('estado', 12)->default('ENVIADA');
            $table->timestampTz('fecha')->useCurrent();
            $table->text('observacion')->nullable();
            $table->timestampTz('revisada_en')->nullable();
            $table->timestampTz('decidida_en')->nullable();
            $table->foreign('vacante_id')->references('id')->on('vacante')->cascadeOnDelete();
            $table->foreign('estudiante_id')->references('id')->on('estudiante')->cascadeOnDelete();
            $table->unique(['vacante_id', 'estudiante_id']);
        });

        Schema::dropIfExists('cita');
        Schema::dropIfExists('recomendacion');
        Schema::dropIfExists('acuerdo');
        Schema::dropIfExists('parametro');

        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->dropForeign(['revisada_por']);
            $table->dropColumn(['estado', 'observaciones', 'enviada_en', 'revisada_por', 'revisada_en']);
        });
        Schema::table('estudiante', function (Blueprint $table) {
            $table->dropForeign(['registrado_por']);
            $table->dropColumn(['modalidad_grado', 'promedio', 'habilitado', 'resultado_enviado_en', 'registrado_por']);
        });
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign(['programa_id']);
            $table->dropColumn(['debe_cambiar_password', 'programa_id']);
        });
        DB::table('usuario')->where('rol', 'DIRECTOR')->delete();
        Restricciones::reemplazarValores('usuario', 'rol', ['ADMIN', 'DOCENTE', 'ESTUDIANTE', 'EMPRESA']);
    }
};
