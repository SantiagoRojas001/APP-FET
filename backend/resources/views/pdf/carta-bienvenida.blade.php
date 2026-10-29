{{-- Carta de bienvenida a la práctica (dompdf). --}}
@php
    $modalidad = mb_strtolower($v->tipo->label());
    $e = $v->estudiante;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Carta de bienvenida · {{ $e->nombre_completo }}</title>
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
    ul { margin: 0 0 6mm; padding-left: 6mm; }
    li { margin-bottom: 1.5mm; }
    .firma { margin-top: 14mm; page-break-inside: avoid; }
    .firma .linea { width: 95mm; border-top: 1px solid #15171C; padding-top: 1.5mm; }
</style>
</head>
<body>
<img class="logo" src="data:image/png;base64,{{ $logo }}" alt="Fundación Escuela Tecnológica de Neiva">

<p class="fecha">Neiva, {{ now()->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>

<p>Estudiante<br><b>{{ $e->nombre_completo }}</b><br>{{ $e->programa->nombre }} · Código {{ $e->codigo }}</p>

<h1>Carta de bienvenida a tu {{ $modalidad }}</h1>

<p>
    La Fundación Escuela Tecnológica de Neiva «Jesús Oviedo Pérez» te da la bienvenida a tu {{ $modalidad }}.
    {{ $v->empresa->razon_social }} te seleccionó y la coordinación de prácticas autorizó tu vinculación en
    las siguientes condiciones:
</p>

<table>
    <tr><td class="k">Empresa</td><td>{{ $v->empresa->razon_social }}</td></tr>
    @if ($v->vacante)
        <tr><td class="k">Cargo o vacante</td><td>{{ $v->vacante->titulo }}</td></tr>
    @endif
    <tr><td class="k">Modalidad</td><td>{{ $v->tipo->label() }}</td></tr>
    <tr><td class="k">Fecha de inicio</td><td>{{ $v->fecha_inicio->format('d/m/Y') }}</td></tr>
    <tr><td class="k">Horas requeridas</td><td>{{ $e->programa->horas_requeridas }}</td></tr>
    <tr><td class="k">Docente tutor</td><td>{{ $v->tutor?->correo ?? 'Por asignar' }}</td></tr>
</table>

<p>Durante tu {{ $modalidad }} deberás:</p>
<ul>
    <li>Subir en APP FET tu certificado de afiliación a la ARL antes de iniciar actividades.</li>
    <li>Reportar cada semana tus horas con la evidencia de lo realizado; la empresa las aprueba.</li>
    <li>Mantener al día tu bitácora y el control de asistencia.</li>
    <li>Cumplir el reglamento de la empresa y los compromisos académicos con tu docente tutor.</li>
</ul>

<p>
    Mientras tu {{ $modalidad }} esté activa, tu hoja de vida queda deshabilitada en la bolsa de APP FET y
    tus demás postulaciones se cierran. Te deseamos muchos éxitos en esta etapa.
</p>

<div class="firma">
    <div class="linea">Coordinación de pasantías y prácticas<br>Fundación Escuela Tecnológica de Neiva</div>
</div>
</body>
</html>
