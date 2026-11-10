{{-- Carta de presentación del estudiante ante la empresa (dompdf). --}}
@php
    $e = $cita->estudiante;
    $modalidad = mb_strtolower(($cita->vacante?->modalidad ?? $e->modalidad_grado)?->label() ?? 'práctica');
    $inicio = $cita->fecha_inicio_practica ?? today();
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Carta de presentación · {{ $e->nombre_completo }}</title>
<style>
    @page { margin: 22mm 24mm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #15171C; line-height: 1.45; }
    .logo { height: 18mm; }
    .fecha { margin: 8mm 0 6mm; }
    h1 { font-size: 13pt; color: #3E7B27; margin: 0 0 6mm; }
    p { margin: 0 0 4mm; text-align: justify; }
    table { width: 100%; border-collapse: collapse; margin: 2mm 0 6mm; }
    td { padding: 1.2mm 2mm; border-bottom: 1px solid #E1E4EA; vertical-align: top; }
    td.k { width: 38%; font-weight: bold; }
    .firma { margin-top: 16mm; page-break-inside: avoid; }
    .firma .linea { width: 95mm; border-top: 1px solid #15171C; padding-top: 1.5mm; }
</style>
</head>
<body>
<img class="logo" src="data:image/png;base64,{{ $logo }}" alt="Fundación Escuela Tecnológica de Neiva">

<p class="fecha">Neiva, {{ ($cita->presentada_en ?? now())->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>

<p>Señores<br><b>{{ $cita->empresa->razon_social }}</b><br>NIT {{ $cita->empresa->nit }}</p>

<h1>Carta de presentación</h1>

<p>
    Reciban un cordial saludo. La Fundación Escuela Tecnológica de Neiva «Jesús Oviedo Pérez» presenta a
    <b>{{ $e->nombre_completo }}</b>, estudiante de {{ $e->programa->nombre }} (ciclo {{ mb_strtolower($e->ciclo()->label()) }}), para realizar su {{ $modalidad }}
    en su organización, de acuerdo con el proceso de selección que adelantaron y el convenio vigente con la institución.
</p>

<table>
    <tr><td class="k">Estudiante</td><td>{{ $e->nombre_completo }}</td></tr>
    <tr><td class="k">Programa y semestre</td><td>{{ $e->programa->nombre }}, {{ $e->semestre_actual }}.º semestre</td></tr>
    <tr><td class="k">Modalidad</td><td>{{ ucfirst($modalidad) }}</td></tr>
    @if ($cita->vacante)
        <tr><td class="k">Cargo o vacante</td><td>{{ $cita->vacante->titulo }}</td></tr>
    @endif
    <tr><td class="k">Fecha de inicio</td><td>{{ $inicio->format('d/m/Y') }}</td></tr>
    <tr><td class="k">Horas requeridas</td><td>{{ \App\Models\Parametro::horasRequeridas($e->ciclo()) }}</td></tr>
    <tr><td class="k">Docente tutor</td><td>{{ $e->hojaVida?->revisor?->correo ?? 'Por asignar' }}</td></tr>
</table>

<p>
    Durante la práctica, la empresa aprobará en APP FET las horas semanales del estudiante y, al finalizar,
    responderá su evaluación. Agradecemos el apoyo a la formación de nuestros estudiantes.
</p>

<div class="firma">
    <div class="linea">
        Director(a) de programa · {{ $e->programa->nombre }}<br>
        {{ $cita->director?->correo }}<br>
        Fundación Escuela Tecnológica de Neiva
    </div>
</div>
</body>
</html>
