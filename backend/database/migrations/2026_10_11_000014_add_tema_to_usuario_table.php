<?php

use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tema visual preferido por cada usuario (claro, oscuro o el del sistema).
     * Ajuste al modelo de datos: se guarda en la cuenta para que cada persona
     * conserve su tema en cualquier equipo.
     */
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->string('tema', 10)->default('system');
        });
        Restricciones::valoresPermitidos('usuario', 'tema', ['light', 'dark', 'system']);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE usuario DROP CONSTRAINT IF EXISTS usuario_tema_check');

        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn('tema');
        });
    }
};
