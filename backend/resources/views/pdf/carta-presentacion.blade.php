{{-- Carta de presentación del estudiante ante la empresa (dompdf). --}}
@php
    $e = $cita->estudiante;
    $modalidad = mb_strtolower(($cita->vacante?->modalidad ?? $e->modalidad_grado)?->label() ?? 'práctica profesional');
    $inicio = $cita->fecha_inicio_practica ?? today();
    $programa = $e->programa;
    $ciclo = match ($e->ciclo()) {
        \App\Enums\Ciclo::Tecnico => 'ciclo técnico',
        \App\Enums\Ciclo::Tecnologo => 'ciclo tecnólogo',
        \App\Enums\Ciclo::Profesional => str_starts_with($programa->nombre, 'Ingeniería') ? 'ciclo de Ingeniería' : 'ciclo profesional',
    };
    $creditos = config('fet.practica.creditos');
    $semanas = config('fet.practica.semanas');
    $meses = (int) round($semanas / 4);
    $horas = \App\Models\Parametro::horasRequeridas();
    $director = $firmante?->nombre ?? 'Director(a) de programa';
    $registro = $programa->snies
        ? " cuenta con Código SNIES {$programa->snies}".($programa->resolucion ? " y Resolución del Ministerio de Educación Nacional {$programa->resolucion}" : '').', el cual'
        : '';
    $identificacion = $e->documento
        ? ', identificado(a) con C.C. No. '.number_format((float) $e->documento, 0, '', '.').($e->lugar_expedicion ? " de {$e->lugar_expedicion}" : '')
        : '';
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
    .firma img { display: block; width: 60mm; margin-bottom: 1mm; }
</style>
</head>
<body>
<img class="logo" src="data:image/png;base64,{{ $logo }}" alt="Fundación Escuela Tecnológica de Neiva">

<p class="fecha">Neiva, {{ ($cita->presentada_en ?? now())->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>

<p>Estimado<br><b>{{ $cita->empresa->razon_social }}</b><br>Ciudad</p>

<p>Cordial saludo.</p>

<p>
    En calidad de director del Programa de {{ $programa->nombre }} de la Fundación Escuela Tecnológica de Neiva
    «Jesús Oviedo Pérez» presento al estudiante, como requisito de cumplimiento para desarrollar y terminar el proceso
    de formación del {{ $ciclo }} de nuestro programa académico.
</p>

<p>
    Teniendo en cuenta que nuestro Programa {{ $programa->nombre }}{{ $registro }} tiene en su plan de estudios la {{ $modalidad }}
    como requisito, con una cantidad de {{ $creditos }} créditos académicos, correspondientes a {{ $horas }} horas para
    ejecutar en {{ $semanas }} semanas ({{ $meses }} meses), solicitamos contemplen la posibilidad de vincular a
    <b>{{ mb_strtoupper($e->nombre_completo) }}</b>{{ $identificacion }}, quien cursa
    {{ $e->semestre_actual }}.º semestre y cumple con los requisitos exigidos por la institución para la realización de
    la {{ $modalidad }} en el período académico {{ \App\Support\Periodo::deFecha($inicio) }}.
</p>

@if ($firmante?->correo)
    <p>Ante cualquier inquietud se puede comunicar al correo: {{ $firmante->correo }}.</p>
@endif

<p>Atentamente,</p>

<div class="firma">
    @if ($firma)
        <img src="{{ $firma }}" alt="Firma">
    @endif
    <div class="linea">
        <b>{{ mb_strtoupper($director) }}</b><br>
        @if ($firmante?->documento)C.C. {{ $firmante->documento }}<br>@endif
        Director del Programa de {{ $programa->nombre }}<br>
        Fundación Escuela Tecnológica de Neiva «Jesús Oviedo Pérez»
    </div>
</div>
</body>
</html>
