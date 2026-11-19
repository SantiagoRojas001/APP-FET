<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** La cédula solo la ven el estudiante y la empresa: ya no se compara la firma. */
    public function up(): void
    {
        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->dropColumn(['cedula_firma_url', 'firma_similitud']);
        });
    }

    public function down(): void
    {
        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->string('cedula_firma_url', 255)->nullable();
            $table->smallInteger('firma_similitud')->nullable();
        });
    }
};
