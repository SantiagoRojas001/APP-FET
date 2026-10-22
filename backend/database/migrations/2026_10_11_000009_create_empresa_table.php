<?php

use App\Enums\EstadoValidacion;
use App\Enums\Modalidad;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empresas registradas y su estado de validación institucional (Tabla 11).
     */
    public function up(): void
    {
        Schema::create('empresa', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('usuario_id')->unique();
            $table->string('nit', 20)->unique();
            $table->string('razon_social', 150);
            $table->string('sector', 80)->nullable();
            $table->string('contacto_nombre', 120)->nullable();
            $table->string('contacto_telefono', 20)->nullable();
            $table->string('modalidad_interes', 25);
            $table->string('estado_validacion', 12)->default(EstadoValidacion::Pendiente->value);
            $table->integer('validada_por')->nullable();
            $table->timestampTz('validada_en')->nullable();

            $table->foreign('usuario_id')->references('id')->on('usuario')->cascadeOnDelete();
            $table->foreign('validada_por')->references('id')->on('usuario')->nullOnDelete();
        });
        Restricciones::valoresPermitidos('empresa', 'modalidad_interes', Modalidad::values());
        Restricciones::valoresPermitidos('empresa', 'estado_validacion', EstadoValidacion::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa');
    }
};
