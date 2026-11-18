<?php

use App\Enums\TipoDocumento;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Docentes y empresas también suben documentos (incluidas las copias
     * firmadas de los formatos), la empresa puede aprobar un informe y el
     * docente comparte materiales con su grupo.
     */
    public function up(): void
    {
        Restricciones::reemplazarValores('documento_seguimiento', 'tipo', TipoDocumento::values());

        Schema::table('documento_seguimiento', function (Blueprint $table) {
            $table->timestampTz('aprobado_empresa_en')->nullable();
        });

        Schema::create('material_docente', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('docente_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('titulo', 150);
            $table->string('archivo_url', 255);
            $table->timestampTz('creado_en')->default(DB::raw('now()'));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_docente');

        Schema::table('documento_seguimiento', function (Blueprint $table) {
            $table->dropColumn('aprobado_empresa_en');
        });

        Restricciones::reemplazarValores('documento_seguimiento', 'tipo', ['ARL', 'BITACORA', 'CONTROL_ASISTENCIA', 'INFORME_AVANCE', 'INFORME_FINAL']);
    }
};
