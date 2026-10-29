<?php

use App\Enums\EstadoPostulacion;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La hoja de vida llega directo a la empresa y el docente autoriza
     * después de que la empresa selecciona. Las postulaciones que el docente
     * ya había aprobado quedan como enviadas a la empresa.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE postulacion DROP CONSTRAINT IF EXISTS postulacion_estado_check');
        DB::table('postulacion')->where('estado', 'APROBADA')->update(['estado' => EstadoPostulacion::Enviada->value, 'revisada_en' => null]);
        Restricciones::valoresPermitidos('postulacion', 'estado', EstadoPostulacion::values());
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE postulacion DROP CONSTRAINT IF EXISTS postulacion_estado_check');
        DB::table('postulacion')->where('estado', 'SELECCIONADA')->update(['estado' => 'APROBADA']);
        Restricciones::valoresPermitidos('postulacion', 'estado', ['ENVIADA', 'APROBADA', 'RECHAZADA', 'ACEPTADA', 'DESCARTADA']);
    }
};
