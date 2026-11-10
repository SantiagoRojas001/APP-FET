<?php

use App\Enums\EstadoSolicitudConvenio;
use App\Enums\Modalidad;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - La empresa se registra con la firma de su representante legal y el
     *   convenio vigente con la FET.
     * - Sin convenio, envía una solicitud de convenio (no crea cuenta): la FET
     *   le envía el convenio y la empresa vuelve a registrarse con él.
     */
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->string('representante_nombre', 120)->nullable();
            $table->string('representante_documento', 20)->nullable();
            $table->string('firma_representante_url', 255)->nullable();
        });

        Schema::create('solicitud_convenio', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nit', 20);
            $table->string('razon_social', 150);
            $table->string('sector', 80)->nullable();
            $table->string('correo', 150);
            $table->string('contacto_nombre', 120);
            $table->string('contacto_telefono', 20);
            $table->string('modalidad_interes', 25);
            $table->string('representante_nombre', 120);
            $table->string('representante_documento', 20);
            $table->string('firma_url', 255);
            $table->string('estado', 12)->default(EstadoSolicitudConvenio::Pendiente->value);
            $table->text('observacion')->nullable();
            $table->string('convenio_url', 255)->nullable();
            $table->integer('atendida_por')->nullable();
            $table->timestampTz('atendida_en')->nullable();
            $table->timestampTz('creado_en')->useCurrent();

            $table->index('nit');
            $table->foreign('atendida_por')->references('id')->on('usuario')->nullOnDelete();
        });
        Restricciones::valoresPermitidos('solicitud_convenio', 'estado', EstadoSolicitudConvenio::values());
        Restricciones::valoresPermitidos('solicitud_convenio', 'modalidad_interes', Modalidad::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_convenio');
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['representante_nombre', 'representante_documento', 'firma_representante_url']);
        });
    }
};
