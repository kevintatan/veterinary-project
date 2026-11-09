@php
  $d = $record['detail'];
  $v = $d['vital_signs'] ?? null;
  $vitals = $v ? array_filter([
      'Peso' => $v['weight_kg'] !== null ? number_format($v['weight_kg'], 1, ',', '.').' kg' : null,
      'Temp.' => $v['temperature_c'] !== null ? number_format($v['temperature_c'], 1, ',', '.').' °C' : null,
      'FC' => $v['heart_rate'] !== null ? $v['heart_rate'].' lpm' : null,
      'FR' => $v['respiratory_rate'] !== null ? $v['respiratory_rate'].' rpm' : null,
      'TLLC' => $v['capillary_refill_s'] !== null ? number_format($v['capillary_refill_s'], 1, ',', '.').' s' : null,
      'SpO₂' => $v['spo2_pct'] !== null ? $v['spo2_pct'].' %' : null,
      'PA' => $v['systolic_bp'] !== null ? $v['systolic_bp'].'/'.($v['diastolic_bp'] ?? '—').' mmHg' : null,
      'Glucosa' => $v['glucose_mg_dl'] !== null ? $v['glucose_mg_dl'].' mg/dL' : null,
      'CC' => $v['body_condition_score'] !== null ? $v['body_condition_score'].'/9' : null,
      'Dolor' => $v['pain_score'] !== null ? $v['pain_score'].'/10' : null,
      'Mucosas' => isset($v['mucous_membranes']['label']) ? mb_strtolower($v['mucous_membranes']['label']) : null,
      'Hidratación' => isset($v['hydration']['label']) ? mb_strtolower($v['hydration']['label']) : null,
  ], fn ($x) => $x !== null) : [];
  $block = fn ($label, $text) => $text ? '<div class="section"><b>'.e($label).':</b> '.nl2br(e($text)).'</div>' : '';
@endphp

<div class="record {{ $record['voided'] ? 'void' : '' }}">
  <div class="record-head">
    <span class="tag {{ $record['voided'] ? 'void' : '' }}">{{ $record['voided'] ? 'Anulado · ' : '' }}{{ $record['type_label'] }}</span>
    <b style="margin-left: 4px;">{{ $record['occurred_at']->format('d/m/Y g:i a') }}</b>
    <span class="muted"> · {{ $record['summary'] }}</span>
  </div>

  @if ($record['voided'])
    <div class="small void-note">
      Anulado{{ $record['voided']['by'] ? ' por '.$record['voided']['by'] : '' }}. Motivo: {{ $record['voided']['reason'] }}
    </div>
  @endif

  <div class="content">
    @if (! empty($d['consultation']))
      {!! $block('Motivo', $d['consultation']['reason']) !!}
      {!! $block('Anamnesis', $d['consultation']['subjective']) !!}
    @endif
    @if (! empty($d['follow_up']))
      {!! $block($d['follow_up']['kind']['label'] ?? 'Seguimiento', $d['follow_up']['reason']) !!}
      {!! $block('Evolución', $d['follow_up']['details']) !!}
    @endif

    @if ($vitals)
      <div class="section"><b>Signos vitales:</b></div>
      <div class="vitals" style="margin-top: 3px;">@foreach ($vitals as $label => $value)<span>{{ $label }} {{ $value }}</span>@endforeach</div>
    @endif

    @if (! empty($d['findings']) && count($d['findings']))
      @php
        $abnormal = collect($d['findings'])->where('status.value', 'abnormal');
        $normal = collect($d['findings'])->where('status.value', 'normal');
      @endphp
      <div class="section"><b>Examen físico:</b>
        @foreach ($abnormal as $f)
          <div><span class="abnormal">{{ $f['system']['label'] }} (anormal):</span> {{ $f['notes'] ?: 'sin detalle' }}</div>
        @endforeach
        @if ($normal->isNotEmpty())
          <div class="muted">Normal: {{ $normal->map(fn ($f) => mb_strtolower(str_replace('Sistema ', '', $f['system']['label'])))->join(', ') }}.</div>
        @endif
      </div>
    @endif
    @if (! empty($d['consultation']))
      {!! $block('Observaciones', $d['consultation']['objective']) !!}
    @endif

    @if (! empty($d['diagnoses']) && count($d['diagnoses']))
      <div class="section"><b>Diagnóstico:</b>
        @foreach ($d['diagnoses'] as $dx)
          <div>{{ $dx['kind']['label'] }}: {{ $dx['description'] }}{{ $dx['code'] ? ' ('.$dx['code'].')' : '' }}</div>
        @endforeach
      </div>
    @endif
    @if (! empty($d['consultation']))
      {!! $block('Análisis', $d['consultation']['assessment']) !!}
      {!! $block('Plan terapéutico', $d['consultation']['plan_treatment']) !!}
      {!! $block('Plan diagnóstico', $d['consultation']['plan_diagnostic']) !!}
    @endif

    @if (! empty($d['vaccination']))
      @php $vx = $d['vaccination']; @endphp
      <div class="section">
        <b>Vacuna:</b> {{ $vx['vaccine_name'] }}{{ $vx['manufacturer'] ? ' · '.$vx['manufacturer'] : '' }}
        @if ($vx['lot_number']) · Lote {{ $vx['lot_number'] }}@endif
        @if ($vx['lot_expires_on']) (vence {{ \Illuminate\Support\Carbon::parse($vx['lot_expires_on'])->format('d/m/Y') }})@endif
        @if ($vx['dose_number']) · Dosis {{ $vx['dose_number'] }}@endif
        @if ($vx['route']) · Vía {{ $vx['route'] }}@endif
      </div>
    @endif

    @if (! empty($d['deworming']))
      <div class="section"><b>Desparasitación {{ mb_strtolower($d['deworming']['kind']['label']) }}:</b> {{ $d['deworming']['product_name'] }}{{ $d['deworming']['dose'] ? ' · '.$d['deworming']['dose'] : '' }}</div>
    @endif

    @if (! empty($d['prescription']))
      {!! $block('Diagnóstico', $d['prescription']['diagnosis']) !!}
      <table class="rx" style="margin-top: 4px;">
        <tr><th>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th>Duración</th><th>Vía</th><th>Cant.</th></tr>
        @foreach ($d['prescription']['items'] as $item)
          <tr>
            <td><b>{{ $item['medication'] }}</b>{{ $item['presentation'] ? ' '.$item['presentation'] : '' }}@if ($item['notes'])<br><span class="muted">{{ $item['notes'] }}</span>@endif</td>
            <td>{{ $item['dose'] }}</td>
            <td>{{ $item['frequency'] }}</td>
            <td>{{ $item['duration'] }}</td>
            <td>{{ $item['route'] }}</td>
            <td>{{ $item['quantity'] }}</td>
          </tr>
        @endforeach
      </table>
      {!! $block('Indicaciones', $d['prescription']['instructions']) !!}
    @endif

    @if (! empty($d['procedure']))
      {!! $block('Procedimiento', $d['procedure']['name']) !!}
      {!! $block('Descripción', $d['procedure']['description']) !!}
      {!! $block('Premedicación', $d['procedure']['premedication']) !!}
      {!! $block('Anestesia', $d['procedure']['anesthesia']) !!}
      {!! $block('Complicaciones', $d['procedure']['complications']) !!}
      {!! $block('Resultado', $d['procedure']['outcome']) !!}
    @endif

    @if (! empty($d['note']))
      <div class="section">{!! nl2br(e($d['note']['body'])) !!}</div>
    @endif

    @if ($record['next_control_at'])
      <div class="section"><b>Próximo control:</b> {{ $record['next_control_at']->format('d/m/Y') }}{{ $record['next_control_note'] ? ' · '.$record['next_control_note'] : '' }}</div>
    @endif
    @if ($record['attachments'])
      <div class="section muted small"><b>Adjuntos:</b> {{ implode(', ', $record['attachments']) }}</div>
    @endif
  </div>

  @if ($record['author'] && (! $single || $record['voided']))
    <div class="sign">Médico veterinario: <b>{{ $record['author'] }}</b>{{ $record['license'] ? ' · T.P. '.$record['license'] : '' }}</div>
  @endif
</div>
