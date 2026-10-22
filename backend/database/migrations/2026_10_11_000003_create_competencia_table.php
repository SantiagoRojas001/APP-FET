<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de competencias (Tabla 3).
     */
    public function up(): void
    {
        Schema::create('competencia', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 80)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia');
    }
};
