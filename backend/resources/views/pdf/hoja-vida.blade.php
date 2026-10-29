{{-- Hoja de vida en el formato institucional PA-GTH-F-10 de la FET (dompdf). --}}
@php
    use App\Enums\NivelFormacion;
    use App\Enums\TipoInvestigacion;
    use App\Enums\TipoReferencia;

    $hoja = $estudiante->hojaVida;
    $formaciones = ($hoja?->formaciones ?? collect())->groupBy(fn ($f) => $f->nivel->value);
    $investigaciones = ($hoja?->investigaciones ?? collect())->groupBy(fn ($i) => $i->tipo->value);
    $referencias = ($hoja?->referencias ?? collect())->groupBy(fn ($r) => $r->tipo->value);
    $experiencias = $hoja?->experiencias ?? collect();
    $cursos = $hoja?->cursos ?? collect();
    $nacimiento = $hoja?->fecha_nacimiento;
    $dato = fn ($valor) => filled($valor) ? $valor : 'No registra';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Hoja de vida · {{ $estudiante->nombre_completo }}</title>
<style>
    @page { margin: 40mm 22mm 20mm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #000; line-height: 1.35; }
    header { position: fixed; top: -32mm; left: -10mm; right: -10mm; }
    header table.enc { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
    header table.enc > tr > td, header .enc td.celda { border: 1.5px solid #000; vertical-align: middle; height: 22mm; }
    header .logo { width: 33%; text-align: center; }
    header .logo img { height: 17mm; }
    header .nombre { width: 39%; text-align: center; font-weight: bold; font-size: 9pt; }
    header .meta { width: 28%; padding: 0; }
    header .meta table { width: 100%; border-collapse: collapse; }
    header .meta td { font-weight: bold; font-size: 8pt; padding: 1.2mm 1.5mm; border-bottom: 1.5px solid #000; }
    header .meta tr:last-child td { border-bottom: 0; padding: 2.6mm 1.5mm; }
    h1 { color: #5A9A3A; text-align: center; font-size: 19pt; margin: 0 0 5mm; }
    .foto { text-align: center; margin-bottom: 6mm; }
    .foto img, .foto .vacia { width: 33mm; height: 42mm; border: 2.5px solid #5B9150; border-radius: 1mm; }
    .foto .vacia { display: inline-block; color: #888; font-size: 9pt; line-height: 42mm; }
    h2 { background: #8CB83A; color: #fff; text-align: center; font-size: 14pt; margin: 0 0 5mm; padding: 1mm 0; }
    h2 .n { padding-right: 7mm; }
    h3 { background: #E5EBB4; font-size: 12.5pt; margin: 6mm -3mm 3mm; padding: 2mm 3mm; }
    h4 { color: #2F5F1F; font-size: 12pt; margin: 4mm 0 0; padding-top: 1.5mm; border-top: 1px solid #5B9150; }
    .salto { page-break-before: always; }
    .campo { margin: 0 0 4mm; }
    .campo b { text-transform: uppercase; }
    .bloque { margin: 0 0 5mm; }
    .bloque p, p.bloque { margin: 0; }
    .nr { margin: 0 0 4mm; }
    table.fecha { border-collapse: collapse; }
    table.fecha td { padding: 0 2.5mm 0 0; vertical-align: top; text-align: center; }
    table.fecha .k { width: 62mm; font-weight: bold; text-align: left; }
    table.fecha small { display: block; font-size: 10pt; }
    .cursos-titulo { text-align: center; font-weight: bold; font-size: 12.5pt; margin: 7mm 0 0; }
    table.cursos { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
    table.cursos th { background: #8CB83A; color: #000; font-size: 10pt; padding: .6mm 1.5mm; text-align: center; }
    table.cursos td { border: 1px solid #C9E0B0; padding: 1mm 1.5mm; vertical-align: top; }
    .referencia { font-weight: bold; font-size: 12.5pt; margin: 8mm 0 3mm; }
    .firma { margin: 30mm auto 0; width: 70mm; text-align: center; font-weight: bold; border-top: 1.5px solid #000; padding-top: 1mm; }
</style>
</head>
<body>
<header>
    <table class="enc">
        <tr>
            <td class="celda logo"><img src="data:image/png;base64,{{ $logo }}" alt="FET"></td>
            <td class="celda nombre">FORMATO HOJA DE VIDA</td>
            <td class="celda meta">
                <table>
                    <tr><td>CODIGO: PA-GTH-F-10</td></tr>
                    <tr><td>VERSION: 01</td></tr>
                    <tr><td>FECHA: 23/06/2018</td></tr>
                </table>
            </td>
        </tr>
    </table>
</header>

{{-- 1. Datos personales --}}
<h1>HOJA DE VIDA</h1>
<div class="foto">
    @if ($foto)<img src="{{ $foto }}" alt="Foto">@else<span class="vacia">Foto</span>@endif
</div>

<h2><span class="n">1.</span>DATOS PERSONALES</h2>
<p class="campo"><b>Nombre:</b> {{ $estudiante->nombre_completo }}</p>
@if ($verDocumento)
    <p class="campo"><b>Documento de identidad:</b> {{ $estudiante->documento }}</p>
@endif
<table class="fecha campo">
    <tr>
        <td class="k">FECHA DE NACIMIENTO:</td>
        @if ($nacimiento)
            <td>({{ $nacimiento->format('d') }})<small>Día</small></td>
            <td>({{ $nacimiento->format('m') }})<small>Mes</small></td>
            <td>({{ $nacimiento->format('Y') }})<small>Año</small></td>
        @else
            <td>No registra</td>
        @endif
    </tr>
</table>
<p class="campo"><b>Lugar de nacimiento:</b> {{ $dato($hoja?->lugar_nacimiento) }}</p>
<p class="campo"><b>Estado civil:</b> {{ $dato($hoja?->estado_civil) }}</p>
<p class="campo"><b>Lugar de residencia:</b> {{ $dato($hoja?->lugar_residencia) }}</p>
<p class="campo"><b>Dirección:</b> {{ $dato($hoja?->direccion) }}</p>
<p class="campo"><b>Teléfonos:</b> {{ $dato($estudiante->telefono) }}</p>
<p class="campo"><b>E-mail:</b> {{ $estudiante->usuario->correo }}</p>
<p class="campo"><b>R-H:</b> {{ $dato($hoja?->rh) }}</p>
<p class="campo"><strong>Persona de contacto en caso de emergencia:</strong>
    @if (filled($hoja?->contacto_emergencia_nombre))
        {{ $hoja->contacto_emergencia_nombre }} — Tel. {{ $hoja->contacto_emergencia_telefono }}
    @else
        No registra
    @endif
</p>

{{-- 2. Formación académica --}}
<h2 class="salto"><span class="n">2.</span>FORMACIÓN ACADÉMICA</h2>
<h3>2.1 EDUCACIÓN SUPERIOR</h3>
@foreach ([NivelFormacion::Maestria, NivelFormacion::Especializacion, NivelFormacion::Pregrado] as $nivel)
    @php $titulos = $formaciones->get($nivel->value, collect()); @endphp
    @if ($nivel === NivelFormacion::Pregrado)
        <div class="bloque">
            <p>Pregrado:</p>
            <p>Fecha de grado: {{ $estudiante->es_egresado ? 'Egresado(a)' : 'En curso (semestre '.$estudiante->semestre_actual.')' }}</p>
            <p>Institución: Fundación Escuela Tecnológica de Neiva “Jesús Oviedo Pérez”</p>
            <p>País y ciudad: Colombia, Neiva</p>
            <p>Título obtenido: {{ $estudiante->programa->nombre }}</p>
        </div>
    @elseif ($titulos->isEmpty())
        <p class="bloque">{{ $nivel->label() }}: No registra</p>
    @endif
    @foreach ($titulos as $f)
        <div class="bloque">
            <p>{{ $nivel->label() }}:</p>
            <p>Fecha de grado: {{ $f->fecha_grado?->format('d/m/Y') ?? 'En curso' }}</p>
            <p>Institución: {{ $f->institucion }}</p>
            <p>País y ciudad: {{ $f->pais_ciudad }}</p>
            <p>Título obtenido: {{ $f->titulo }}</p>
        </div>
    @endforeach
@endforeach

<p class="cursos-titulo">Diplomados, cursos, seminarios y actualizaciones</p>
<table class="cursos">
    <tr><th>TEMA</th><th>DURACIÓN</th><th>FECHA</th><th>INSTITUCIÓN</th><th>MODALIDAD</th></tr>
    @forelse ($cursos as $c)
        <tr>
            <td>{{ $c->tema }}</td>
            <td>{{ $c->duracion_horas }}</td>
            <td>{{ $c->fecha->format('m/Y') }}</td>
            <td>{{ $c->institucion }}</td>
            <td>{{ $c->modalidad }}</td>
        </tr>
    @empty
        <tr><td colspan="5">No registra</td></tr>
    @endforelse
</table>

<h3>2.2 TÉCNICA Y TECNOLÓGICA</h3>
@foreach ([NivelFormacion::Tecnologo, NivelFormacion::Tecnico] as $nivel)
    @forelse ($formaciones->get($nivel->value, collect()) as $f)
        <div class="bloque">
            <p>{{ $nivel->label() }}:</p>
            <p>Fecha de grado: {{ $f->fecha_grado?->format('d/m/Y') ?? 'En curso' }}</p>
            <p>Institución: {{ $f->institucion }}</p>
            <p>País y ciudad: {{ $f->pais_ciudad }}</p>
            <p>Título obtenido: {{ $f->titulo }}</p>
            <p>Modalidad de estudio: {{ $dato($f->modalidad) }}</p>
        </div>
    @empty
        <p class="bloque">{{ $nivel->label() }}: No registra</p>
    @endforelse
@endforeach

{{-- 3. Experiencia laboral --}}
<h2 class="salto"><span class="n">3.</span>EXPERIENCIA LABORAL</h2>
@forelse ($experiencias as $e)
    <div class="bloque">
        <p>INSTITUCIÓN/EMPRESA: {{ $e->organizacion }}</p>
        <p>NOMBRE DEL CARGO: {{ $e->cargo }}</p>
        <p>DIRECCIÓN: {{ $dato($e->direccion) }}</p>
        <p>TELÉFONOS: {{ $dato($e->telefono) }}</p>
        <p>NOMBRE DEL JEFE INMEDIATO: {{ $dato($e->jefe_inmediato) }}</p>
        <p>DEDICACIÓN: {{ $dato($e->dedicacion) }}</p>
        <p>FECHA DE INICIO Y TERMINACIÓN: {{ $e->fecha_inicio->format('d/m/Y') }} — {{ $e->fecha_fin?->format('d/m/Y') ?? 'Actual' }}</p>
        <p>FUNCIONES: {{ $dato($e->descripcion) }}</p>
    </div>
@empty
    <p class="nr">No registra</p>
@endforelse

{{-- 4. Experiencia académica investigativa --}}
<h2 class="salto"><span class="n">4.</span>EXPERIENCIA ACADÉMICA INVESTIGATIVA</h2>
@php
    $apartados = [
        ['4.1 PERTENENCIA A GRUPOS DE INVESTIGACIÓN', [TipoInvestigacion::Grupo]],
        ['4.2 PROYECTOS DE INVESTIGACIÓN DESARROLLADOS', [TipoInvestigacion::Proyecto]],
        ['4.3 DIRECCIÓN / TUTORÍAS CONCLUIDAS', [TipoInvestigacion::TutoriaPosgrado, TipoInvestigacion::TutoriaPregrado]],
        ['4.4 PAR EVALUADOR / JURADO EN COMITÉS DE EVALUACIÓN', [TipoInvestigacion::ParEvaluador]],
        ['4.5 TRABAJOS E INFORMES TÉCNICOS', [TipoInvestigacion::InformeTecnico]],
        ['4.6 PUBLICACIONES', [TipoInvestigacion::Publicacion]],
        ['4.7 PERTENENCIA A ORGANIZACIONES', [TipoInvestigacion::Organizacion]],
        ['4.8 PONENCIAS', [TipoInvestigacion::Ponencia]],
    ];
    $subtitulos = [
        TipoInvestigacion::TutoriaPosgrado->value => 'Tutorías trabajos de culminación de posgrado',
        TipoInvestigacion::TutoriaPregrado->value => 'Tutorías trabajos de culminación de pregrado',
    ];
@endphp
@foreach ($apartados as [$titulo, $tipos])
    <h3>{{ $titulo }}</h3>
    @foreach ($tipos as $tipo)
        @if (isset($subtitulos[$tipo->value]))<h4>{{ $subtitulos[$tipo->value] }}</h4>@endif
        @forelse ($investigaciones->get($tipo->value, collect()) as $i)
            <div class="bloque">
                @if ($tipo === TipoInvestigacion::Grupo)
                    <p>Nombre del grupo: {{ $i->descripcion }}</p>
                    <p>Tiempo de vinculación: {{ $dato($i->periodo) }}</p>
                @else
                    <p>{{ $i->descripcion }}{{ $i->periodo ? ' ('.$i->periodo.')' : '' }}</p>
                @endif
            </div>
        @empty
            <p class="nr">No registra</p>
        @endforelse
    @endforeach
@endforeach

{{-- 5. Referencias --}}
<h2 class="salto"><span class="n">5.</span>REFERENCIAS</h2>
@foreach ([TipoReferencia::Familiar, TipoReferencia::Personal] as $tipo)
    <p class="referencia">{{ mb_strtoupper($tipo->label()) }}</p>
    @forelse ($referencias->get($tipo->value, collect()) as $r)
        <div class="bloque">
            <p>NOMBRE COMPLETO: {{ $r->nombre }}</p>
            <p>PROFESIÓN U OCUPACIÓN: {{ $r->ocupacion }}</p>
            @if ($tipo === TipoReferencia::Familiar)<p>PARENTESCO: {{ $r->parentesco }}</p>@endif
            <p>TELÉFONO: {{ $r->telefono }}</p>
        </div>
    @empty
        <p class="nr">No registra</p>
    @endforelse
@endforeach

<div class="firma">FIRMA</div>
</body>
</html>
