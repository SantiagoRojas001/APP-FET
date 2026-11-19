{{-- Formatos institucionales de la práctica (dompdf). --}}
@php
    use App\Enums\EstadoRevision;
    use App\Enums\ParteCalificacion;
    use App\Enums\TipoFormato;

    $e = $vinculacion->estudiante;
    $valor = function (array $campo) use ($datos) {
        $v = $datos[$campo['id']] ?? null;
        if (blank($v)) {
            return 'No registra';
        }

        return $campo['tipo'] === 'fecha' ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : $v;
    };
    $tutor = $vinculacion->tutor_empresa ?? [];
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $config['titulo'] }}</title>
<style>
    @page { margin: 16mm 18mm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #15171C; line-height: 1.35; }
    .cab { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
    .cab td { border: 1px solid #8A909C; padding: 1.5mm 2mm; vertical-align: middle; }
    .logo { height: 13mm; }
    h1 { font-size: 11.5pt; margin: 0; text-align: center; }
    h2 { font-size: 10pt; margin: 4mm 0 1.5mm; }
    table.t { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
    table.t th, table.t td { border: 1px solid #B8BDC7; padding: 1.2mm 1.6mm; text-align: left; vertical-align: top; }
    table.t th { background: #EFF1F4; }
    .n { text-align: center !important; width: 11mm; }
    .firmas { width: 100%; margin-top: 8mm; page-break-inside: avoid; }
    .firmas td { width: 33%; vertical-align: bottom; padding: 0 3mm 0 0; }
    .firmas img { display: block; width: 42mm; height: 14mm; }
    .espacio { height: 14mm; }
    .linea { border-top: 1px solid #15171C; padding-top: 1mm; font-size: 8.5pt; }
</style>
</head>
<body>
<table class="cab">
    <tr>
        <td style="width:30mm"><img class="logo" src="data:image/png;base64,{{ $logo }}" alt="FET"></td>
        <td><h1>{{ mb_strtoupper($config['titulo']) }}</h1></td>
        @if ($config['codigo'])
            <td style="width:34mm">Código: {{ $config['codigo'] }}<br>Versión: {{ $config['version'] }}</td>
        @endif
    </tr>
</table>

<table class="t">
    <tr><th style="width:32%">Empresa</th><td>{{ $vinculacion->empresa->razon_social }} (NIT {{ $vinculacion->empresa->nit }})</td></tr>
    <tr><th>Estudiante</th><td>{{ $e->nombre_completo }}@if ($e->documento), C.C. {{ $e->documento }}@endif</td></tr>
    <tr><th>Programa y periodo</th><td>{{ $e->programa->nombre }}, periodo {{ $vinculacion->periodo() }}</td></tr>
    <tr><th>Docente tutor (asesor FET)</th><td>{{ $vinculacion->tutor?->nombre ?? $vinculacion->tutor?->correo ?? 'Sin asignar' }}</td></tr>
    @if ($tutor)
        <tr><th>Tutor en la empresa</th><td>{{ $tutor['nombre'] ?? '' }}@if (filled($tutor['cargo'] ?? null)), {{ $tutor['cargo'] }}@endif @if (filled($tutor['correo'] ?? null)) · {{ $tutor['correo'] }}@endif @if (filled($tutor['telefono'] ?? null)) · {{ $tutor['telefono'] }}@endif</td></tr>
    @endif
</table>

@foreach ($config['campos'] as $campo)
    @if ($campo['tipo'] === 'tabla')
        <h2>{{ $campo['etiqueta'] }}</h2>
        <table class="t">
            <tr>@foreach ($campo['columnas'] as $c)<th>{{ $c['etiqueta'] }}</th>@endforeach</tr>
            @foreach ($datos[$campo['id']] ?? [] as $fila)
                <tr>@foreach ($campo['columnas'] as $c)<td>{{ $c['tipo'] === 'fecha' ? \Illuminate\Support\Carbon::parse($fila[$c['id']])->format('d/m/Y') : $fila[$c['id']] }}</td>@endforeach</tr>
            @endforeach
        </table>
    @else
        <h2>{{ $campo['etiqueta'] }}</h2>
        <div>{!! nl2br(e($valor($campo))) !!}</div>
    @endif
@endforeach

@if ($tipo === TipoFormato::RegistroAsistencia)
    <table class="t">
        <tr><th class="n">Semana</th><th style="width:30mm">Fechas</th><th>Actividad</th><th style="width:17mm">Evidencia</th><th class="n">Horas</th><th style="width:20mm">Aprobación empresa</th></tr>
        @foreach ($semanas as $i => $s)
            <tr>
                <td class="n">{{ $i + 1 }}</td>
                <td>{{ $s->semana_inicio->format('d/m/Y') }} a {{ $s->semana_inicio->copy()->addDays(6)->format('d/m/Y') }}</td>
                <td>{{ $s->actividades }}</td>
                <td>{{ $s->archivo_url ? 'Adjunta' : 'No adjunta' }}</td>
                <td class="n">{{ rtrim(rtrim(number_format($s->horas, 1, ',', ''), '0'), ',') }}</td>
                <td>{{ $s->estado->label() }}</td>
            </tr>
        @endforeach
        <tr><th colspan="4">Total de horas certificadas</th><th class="n">{{ rtrim(rtrim(number_format($semanas->where('estado', EstadoRevision::Aprobado)->sum('horas'), 1, ',', ''), '0'), ',') }}</th><th></th></tr>
    </table>
@endif

@if ($tipo === TipoFormato::EvaluacionCuantitativa)
    <p>Escala: 1 Bajo, 2 Regular, 3 Adecuado, 4 Bueno, 5 Excelente.</p>
    @foreach (config('calificacion.categorias') as $id => $categoria)
        <h2>{{ $categoria['etiqueta'] }} ({{ $categoria['peso'] }} %)</h2>
        <table class="t">
            <tr><th>Ítem</th><th class="n">M1</th><th class="n">M2</th></tr>
            @foreach ($categoria['items'] as $item => $texto)
                <tr>
                    <td>{{ $texto }}</td>
                    @foreach ([1, 2] as $momento)
                        @php
                            $registro = $calificaciones->first(fn ($c) => $c->momento === $momento && $c->parte === ParteCalificacion::from($categoria['parte']));
                            $puntaje = collect($registro?->puntajes ?? [])->firstWhere('id', $item)['puntaje'] ?? '';
                        @endphp
                        <td class="n">{{ $puntaje }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endforeach
    <table class="t">
        <tr><th style="width:60%">Nota primer momento (40 %)</th><td></td></tr>
        <tr><th>Nota segundo momento (60 %)</th><td></td></tr>
        <tr><th>Nota final</th><td></td></tr>
    </table>
    @foreach ($calificaciones->filter(fn ($c) => filled($c->observaciones)) as $c)
        <p><b>Observaciones de {{ mb_strtolower($c->parte->label()) }}, momento {{ $c->momento }}:</b> {{ $c->observaciones }}</p>
    @endforeach
@endif

<table class="firmas">
    <tr>
        @foreach ($firmas as $f)
            <td>
                @if ($f['firmada'] && $f['imagen'])
                    <img src="{{ $f['imagen'] }}" alt="Firma">
                @else
                    <div class="espacio"></div>
                @endif
                <div class="linea"><b>{{ $f['nombre'] ?? '' }}</b><br>@if ($f['documento'])C.C. {{ $f['documento'] }}<br>@endif{{ $f['etiqueta'] }}</div>
            </td>
        @endforeach
    </tr>
</table>
</body>
</html>
