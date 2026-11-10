<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quien firma la carta de presentación (el director de programa) registra
     * su nombre, su documento de identidad y su firma dibujada.
     */
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->string('nombre', 120)->nullable();
            $table->string('documento', 20)->nullable();
            $table->string('firma_url', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'documento', 'firma_url']);
        });
    }
};
