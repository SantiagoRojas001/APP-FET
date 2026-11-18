<?php

use App\Enums\ParteCalificacion;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evaluación cuantitativa PM-GFO-F-015: en cada uno de los dos momentos
     * la empresa y el docente califican de 1 a 5 los ítems que les tocan.
     */
    public function up(): void
    {
        Schema::create('calificacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vinculacion_id');
            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->cascadeOnDelete();
            $table->smallInteger('momento');
            $table->string('parte', 10);
            $table->foreignId('evaluador_id')->nullable()->constrained('usuario')->nullOnDelete();
            $table->jsonb('puntajes');
            $table->text('observaciones')->nullable();
            $table->timestampTz('creada_en')->default(DB::raw('now()'));
            $table->unique(['vinculacion_id', 'momento', 'parte']);
        });

        Restricciones::valoresPermitidos('calificacion', 'parte', ParteCalificacion::values());
        DB::statement('ALTER TABLE calificacion ADD CONSTRAINT calificacion_momento_check CHECK (momento IN (1, 2))');
    }

    public function down(): void
    {
        Schema::dropIfExists('calificacion');
    }
};
