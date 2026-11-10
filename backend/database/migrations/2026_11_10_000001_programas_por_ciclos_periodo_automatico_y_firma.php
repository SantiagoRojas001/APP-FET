<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Programas de la FET; cada uno se cursa por ciclos (técnico, tecnólogo y profesional). */
    public const PROGRAMAS = [
        'Ingeniería Ambiental',
        'Ingeniería de Alimentos',
        'Ingeniería de Software',
        'Ingeniería Eléctrica',
        'Administración de la Seguridad y Salud en el Trabajo',
        'Administración de Negocios',
    ];

    /**
     * - Los programas son los seis de la FET y el ciclo sale del semestre: los
     *   programas anteriores de software (uno por ciclo) pasan a Ingeniería de
     *   Software. Las horas de práctica ya no son por programa sino por ciclo.
     * - El periodo académico se calcula con la fecha (AAAA-1 o AAAA-2).
     * - La hoja de vida lleva la firma dibujada por el estudiante.
     * - El usuario ve el recorrido guiado de la app una sola vez.
     */
    public function up(): void
    {
        foreach (self::PROGRAMAS as $nombre) {
            DB::table('programa')->updateOrInsert(['nombre' => $nombre], ['total_semestres' => 10]);
        }
        $software = DB::table('programa')->where('nombre', 'Ingeniería de Software')->value('id');
        $anteriores = DB::table('programa')->whereNotIn('nombre', self::PROGRAMAS)->pluck('id');
        if ($anteriores->isNotEmpty()) {
            DB::table('estudiante')->whereIn('programa_id', $anteriores)->update(['programa_id' => $software]);
            DB::table('usuario')->whereIn('programa_id', $anteriores)->update(['programa_id' => $software]);
            DB::table('programa')->whereIn('id', $anteriores)->delete();
        }
        DB::table('programa')->update(['total_semestres' => 10]);

        Schema::table('programa', function (Blueprint $table) {
            $table->dropColumn('horas_requeridas');
        });
        DB::table('parametro')->insert([
            ['clave' => 'horas_tecnico', 'valor' => '320'],
            ['clave' => 'horas_tecnologo', 'valor' => '480'],
            ['clave' => 'horas_profesional', 'valor' => '640'],
        ]);

        Schema::table('vinculacion', function (Blueprint $table) {
            $table->dropForeign(['periodo_id']);
            $table->dropColumn('periodo_id');
        });
        Schema::dropIfExists('periodo_academico');

        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->string('firma_url', 255)->nullable();
        });

        Schema::table('usuario', function (Blueprint $table) {
            $table->boolean('tutorial_visto')->default(false);
        });
        // Quien ya usaba la app no necesita el recorrido.
        DB::table('usuario')->update(['tutorial_visto' => true]);
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn('tutorial_visto');
        });
        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->dropColumn('firma_url');
        });

        Schema::create('periodo_academico', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 10)->unique();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->timestampTz('cerrado_en')->nullable();
        });
        Schema::table('vinculacion', function (Blueprint $table) {
            $table->integer('periodo_id')->nullable();
            $table->foreign('periodo_id')->references('id')->on('periodo_academico')->nullOnDelete();
        });

        DB::table('parametro')->whereIn('clave', ['horas_tecnico', 'horas_tecnologo', 'horas_profesional'])->delete();
        Schema::table('programa', function (Blueprint $table) {
            $table->smallInteger('horas_requeridas')->default(480);
        });
    }
};
