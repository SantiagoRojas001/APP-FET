<?php

use App\Enums\EstadoRevision;
use App\Enums\TipoDocumento;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las horas se reportan por semana con su evidencia y la empresa las
     * aprueba; solo las aprobadas cuentan. Reemplaza el registro diario de
     * asistencias: lo ya registrado se agrupa por semana como aprobado.
     *
     * Los documentos suman el certificado de ARL y el diario de campo pasa a
     * llamarse bitácora.
     */
    public function up(): void
    {
        Schema::create('evidencia_semanal', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vinculacion_id');
            $table->date('semana_inicio');
            $table->decimal('horas', 4, 1);
            $table->string('actividades', 2000);
            $table->string('archivo_url', 255)->nullable();
            $table->string('estado', 10)->default(EstadoRevision::Pendiente->value);
            $table->text('comentario')->nullable();
            $table->integer('revisada_por')->nullable();
            $table->timestampTz('revisada_en')->nullable();
            $table->timestampTz('registrado_en')->useCurrent();

            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->cascadeOnDelete();
            $table->foreign('revisada_por')->references('id')->on('usuario')->nullOnDelete();
            $table->unique(['vinculacion_id', 'semana_inicio']);
        });
        DB::statement('ALTER TABLE evidencia_semanal ADD CONSTRAINT evidencia_semanal_horas_check CHECK (horas > 0 AND horas <= 60)');
        Restricciones::valoresPermitidos('evidencia_semanal', 'estado', EstadoRevision::values());

        DB::statement(<<<'SQL'
            INSERT INTO evidencia_semanal (vinculacion_id, semana_inicio, horas, actividades, estado, revisada_en, registrado_en)
            SELECT vinculacion_id, date_trunc('week', fecha)::date, LEAST(sum(horas), 60),
                   left(string_agg(to_char(fecha, 'DD/MM') || ': ' || actividad, E'\n' ORDER BY fecha), 2000),
                   'APROBADO', max(registrado_en), max(registrado_en)
            FROM asistencia
            GROUP BY vinculacion_id, date_trunc('week', fecha)
        SQL);
        Schema::drop('asistencia');

        DB::statement('ALTER TABLE documento_seguimiento DROP CONSTRAINT IF EXISTS documento_seguimiento_tipo_check');
        DB::table('documento_seguimiento')->where('tipo', 'DIARIO_CAMPO')->update(['tipo' => TipoDocumento::Bitacora->value]);
        Restricciones::valoresPermitidos('documento_seguimiento', 'tipo', TipoDocumento::values());
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documento_seguimiento DROP CONSTRAINT IF EXISTS documento_seguimiento_tipo_check');
        DB::table('documento_seguimiento')->where('tipo', 'BITACORA')->update(['tipo' => 'DIARIO_CAMPO']);
        DB::table('documento_seguimiento')->where('tipo', 'ARL')->delete();
        Restricciones::valoresPermitidos('documento_seguimiento', 'tipo', ['CONTROL_ASISTENCIA', 'DIARIO_CAMPO', 'INFORME_AVANCE', 'INFORME_FINAL']);

        Schema::create('asistencia', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vinculacion_id');
            $table->date('fecha');
            $table->decimal('horas', 4, 1);
            $table->string('actividad', 500);
            $table->timestampTz('registrado_en')->useCurrent();

            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->cascadeOnDelete();
            $table->unique(['vinculacion_id', 'fecha']);
        });
        Schema::drop('evidencia_semanal');
    }
};
