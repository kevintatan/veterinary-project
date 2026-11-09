<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $s['title'] }} · {{ $s['pet']['name'] }}</title>
<style>
  /* Same sober letterhead as the clinical PDF: it is printed and archived. */
  @page { margin: 34px 46px 52px 46px; }
  * { font-family: "DejaVu Sans", sans-serif; }
  body { font-size: 10px; color: #101828; line-height: 1.55; }
  h1 { font-size: 15px; margin: 0; color: #101828; }
  h2 { font-size: 13px; margin: 0 0 2px; text-align: center; }
  h3 { font-size: 8.5px; margin: 0 0 6px; text-transform: uppercase; letter-spacing: .7px; color: #344054; }
  .brand { color: #166534; }
  .muted { color: #475467; }
  .small { font-size: 8px; }
  table { width: 100%; border-collapse: collapse; }
  td { vertical-align: top; }
  .rule { border-bottom: 2px solid #166534; margin: 10px 0 14px; }
  .facts td { padding: 2px 10px 2px 0; }
  .label { color: #475467; font-size: 7.5px; text-transform: uppercase; letter-spacing: .3px; }
  p { margin: 0 0 8px; text-align: justify; }
  ul { margin: 0 0 8px; padding-left: 16px; }
  li { margin-bottom: 4px; text-align: justify; }
  .sigs td { width: 33%; padding: 0 10px 0 0; }
  .sig { border-top: 1px solid #101828; padding-top: 4px; margin-top: 4px; font-size: 8.5px; }
  .sig-img { height: 62px; }
  .sig-empty { height: 62px; }
  .mark { position: fixed; top: 300px; left: 0; right: 0; text-align: center; font-size: 64px; font-weight: bold; color: #d0d5dd; letter-spacing: 6px; }
  .evidence td, .evidence th { border: 1px solid #d0d5dd; padding: 4px 6px; font-size: 8px; text-align: left; }
  .evidence th { background: #f2f4f7; }
  .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 7.5px; word-break: break-all; }
  .footer { position: fixed; bottom: -32px; left: 0; right: 130px; font-size: 7px; color: #667085; }
</style>
</head>
<body>

@if ($draft)<div class="mark">BORRADOR</div>@endif
@if ($voided)<div class="mark">ANULADO</div>@endif

<div class="footer">
  {{ $clinic->name }} · Documento firmado electrónicamente en Zooetia (Ley 527 de 1999). Información confidencial del paciente y su tutor (Ley 1581 de 2012).
</div>

<table>
  <tr>
    <td style="width: 62%">
      @if ($logo)<img src="{{ $logo }}" style="height: 38px; margin-bottom: 4px;" alt="">@endif
      <h1 class="brand">{{ $s['clinic']['name'] }}</h1>
      <div class="muted small">
        {{ collect([$s['clinic']['legal_name'], $s['clinic']['tax_id'] ? 'NIT '.$s['clinic']['tax_id'] : null])->filter()->join(' · ') }}<br>
        {{ collect([$s['clinic']['address'], $s['clinic']['phone']])->filter()->join(' · ') }}
      </div>
    </td>
    <td style="width: 38%; text-align: right;" class="small muted">
      {{ $s['date'] }}<br>
      Documento N.º {{ strtoupper(substr(str_replace('-', '', $doc->id), -10)) }}
    </td>
  </tr>
</table>
<div class="rule"></div>

<h2>{{ $s['title'] }}</h2>
<p class="small muted" style="text-align:center; margin-bottom: 12px;">Paciente: {{ $s['pet']['name'] }} · Tutor: {{ $s['owner']['name'] }}</p>

<table class="facts" style="margin-bottom: 12px; table-layout: fixed;">
  <colgroup><col style="width: 25%"><col style="width: 27%"><col style="width: 20%"><col style="width: 28%"></colgroup>
  <tr>
    <td><div class="label">Paciente</div>{{ $s['pet']['name'] }}</td>
    <td><div class="label">Especie y raza</div>{{ collect([$s['pet']['species'], $s['pet']['breed']])->filter()->join(' · ') }}</td>
    <td><div class="label">Sexo · Edad · Peso</div>{{ collect([$s['pet']['sex'], $s['pet']['age'], $s['pet']['weight']])->filter()->join(' · ') }}</td>
    <td><div class="label">Microchip</div>{{ $s['pet']['microchip'] ?? '—' }}</td>
  </tr>
  <tr>
    <td><div class="label">Tutor</div>{{ $s['owner']['name'] }}</td>
    <td><div class="label">Documento</div>{{ $s['owner']['document'] }}</td>
    <td><div class="label">Celular</div>{{ $s['owner']['phone'] }}</td>
    <td><div class="label">Médico veterinario</div>{{ $s['vet']['name'] }} · T.P. {{ $s['vet']['license'] }}</td>
  </tr>
</table>

@foreach ($s['blocks'] as $block)
  @if ($block['type'] === 'list')
    <ul>@foreach ($block['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
  @else
    <p>{{ $block['text'] }}</p>
  @endif
@endforeach

<p>En constancia se firma electrónicamente el {{ $s['date'] }}.</p>

<table class="sigs" style="margin-top: 22px; page-break-inside: avoid;">
  <tr>
    @foreach ($signers as $signer)
      <td>
        @if ($signer['image'])<img class="sig-img" src="{{ $signer['image'] }}" alt="">@else<div class="sig-empty"></div>@endif
        <div class="sig">
          <strong>{{ $signer['name'] ?? '' }}</strong><br>
          {{ $signer['label'] }}@if ($signer['document']) · {{ $signer['document'] }}@endif<br>
          <span class="muted">@if ($signer['signed_at'])Firmó el {{ $signer['signed_at']->translatedFormat('j M Y, g:i a') }}@else Pendiente de firma @endif</span>
        </div>
      </td>
    @endforeach
  </tr>
</table>

@if ($voided)
  <p style="margin-top: 14px;"><strong>Documento anulado</strong> el {{ $doc->voided_at?->copy()->setTimezone($clinic->timezone)->translatedFormat('j \d\e F \d\e Y') }}. Motivo: {{ $doc->void_reason }}</p>
@endif

@if (count($evidence) > 0)
  <div style="page-break-before: always;"></div>
  <h3>Evidencias de firma electrónica</h3>
  <p class="small">
    Cada firma se hizo sobre el mismo contenido, identificado por la huella digital (SHA-256) que aparece abajo. Si el texto
    cambiara en una sola letra, la huella sería otra. Zooetia conserva el original firmado; la huella del archivo PDF
    se puede consultar en la clínica.
  </p>
  <table class="evidence">
    <tr><th>Firmante</th><th>Rol</th><th>Fecha y hora</th><th>Cómo</th><th>Dispositivo · IP</th></tr>
    @foreach ($evidence as $e)
      <tr>
        <td>{{ $e['name'] }}<br><span class="muted">{{ $e['document'] }}</span></td>
        <td>{{ $e['label'] }}</td>
        <td>{{ $e['signed_at']->translatedFormat('j M Y, g:i:s a') }}</td>
        <td>{{ $e['method'] }}</td>
        <td>{{ $e['device'] }}<br><span class="muted">{{ $e['ip'] ?? '—' }}</span></td>
      </tr>
    @endforeach
  </table>
  <p class="small" style="margin-top: 8px;">Huella del contenido firmado (SHA-256):<br><span class="mono">{{ $doc->content_hash }}</span></p>
  <p class="small muted">Generado el {{ $generatedAt->translatedFormat('j \d\e F \d\e Y, g:i a') }}</p>
@endif

</body>
</html>
