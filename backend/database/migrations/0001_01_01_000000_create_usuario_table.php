<?php

use App\Enums\Rol;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas de acceso y rol de cada persona o empresa (Tabla 4).
     */
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->increments('id');
            $table->string('correo', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('rol', 20);
            $table->boolean('activo')->default(true);
            $table->timestampTz('creado_en')->useCurrent();
        });
        Restricciones::valoresPermitidos('usuario', 'rol', Rol::values());

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuario');
    }
};
