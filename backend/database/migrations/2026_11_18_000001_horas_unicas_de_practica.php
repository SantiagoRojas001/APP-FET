<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Todas las prácticas exigen las mismas horas (12 créditos, 16 semanas):
     * un solo parámetro reemplaza a las horas por ciclo.
     */
    public function up(): void
    {
        DB::table('parametro')->whereIn('clave', ['horas_tecnico', 'horas_tecnologo', 'horas_profesional'])->delete();
        DB::table('parametro')->insert(['clave' => 'horas_practica', 'valor' => '384']);
    }

    public function down(): void
    {
        DB::table('parametro')->where('clave', 'horas_practica')->delete();
        DB::table('parametro')->insert([
            ['clave' => 'horas_tecnico', 'valor' => '320'],
            ['clave' => 'horas_tecnologo', 'valor' => '480'],
            ['clave' => 'horas_profesional', 'valor' => '640'],
        ]);
    }
};
