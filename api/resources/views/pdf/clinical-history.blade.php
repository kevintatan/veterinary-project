<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $title }} · {{ $pet->name }}</title>
<style>
  /* Sober on purpose: clinics print in black & white and photocopy these.
     The only color is the clinic's identity (name + letterhead rule). */
  @page { margin: 34px 40px 48px 40px; }
  * { font-family: "DejaVu Sans", sans-serif; }
  body { font-size: 9.5px; color: #101828; line-height: 1.45; }
  h1 { font-size: 15px; margin: 0; color: #101828; }
  h2 { font-size: 9px; margin: 0 0 6px; text-transform: uppercase; letter-spacing: .7px; color: #344054; }
  .brand { color: #166534; }
  .muted { color: #475467; }
  .small { font-size: 8px; }
  table { width: 100%; border-collapse: collapse; }
  td { vertical-align: top; }
  .header td { padding: 0; }
  .rule { border-bottom: 2px solid #166534; margin: 10px 0 12px; }
  .box { border: 1px solid #98a2b3; border-radius: 4px; padding: 8px 10px; }
  .grid td { padding: 2px 8px 2px 0; }
  .label { color: #475467; font-size: 7.5px; text-transform: uppercase; letter-spacing: .3px; }
  .alert { border: 1px solid #101828; border-left-width: 3px; border-radius: 3px; padding: 4px 7px; margin-bottom: 3px; }
  .record { margin-top: 12px; page-break-inside: avoid; }
  .record-head { border-bottom: 1px solid #98a2b3; padding-bottom: 3px; margin-bottom: 5px; }
  .tag { display: inline-block; font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; padding: 1px 5px; border: 1px solid #344054; border-radius: 2px; }
  .tag.void { border-style: dashed; }
  .void .content { color: #667085; text-decoration: line-through; }
  .void-note { font-style: italic; color: #344054; margin-bottom: 3px; }
  .section { margin-top: 4px; }
  .vitals span { display: inline-block; border: 1px solid #d0d5dd; border-radius: 2px; padding: 1px 5px; margin: 0 3px 3px 0; }
  .rx td { border-bottom: 1px solid #d0d5dd; padding: 3px 4px; }
  .rx th { text-align: left; font-size: 7.5px; text-transform: uppercase; color: #344054; padding: 2px 4px; border-bottom: 1px solid #344054; }
  .abnormal { font-weight: bold; }
  .sign { margin-top: 3px; font-size: 8px; color: #344054; }
  .footer { position: fixed; bottom: -30px; left: 0; right: 130px; font-size: 7px; color: #667085; }
</style>
</head>
<body>

<div class="footer">
  Documento generado por {{ $generatedBy }} el {{ $generatedAt->translatedFormat('j \d\e F \d\e Y, g:i a') }} · {{ $clinic->name }}.
  Información confidencial del paciente y su tutor (Ley 1581 de 2012).
</div>

<table class="header">
  <tr>
    <td style="width: 60%">
      @if ($logo)
        <img src="{{ $logo }}" style="height: 38px; margin-bottom: 4px;" alt="">
      @endif
      <h1 class="brand">{{ $clinic->name }}</h1>
      <div class="muted">
        @if ($clinic->legal_name || $clinic->tax_id){{ collect([$clinic->legal_name, $clinic->tax_id ? 'NIT '.$clinic->tax_id : null])->filter()->join(' · ') }}<br>@endif
        {{ $branch?->address ?? $clinic->address }}@if ($branch?->address || $clinic->address)<br>@endif
        {{ collect([$branch?->phone ?? $clinic->phone, $clinic->email, $clinic->website])->filter()->join(' · ') }}
      </div>
    </td>
    <td style="width: 40%; text-align: right;">
      <div style="font-size: 12px; font-weight: bold;">{{ $title }}</div>
      @if ($period)<div class="muted">{{ $period }}</div>@endif
      <div class="muted small">{{ $generatedAt->translatedFormat('j \d\e F \d\e Y') }}</div>
    </td>
  </tr>
</table>
<div class="rule"></div>

<table>
  <tr>
    <td style="width: 55%; padding-right: 8px;">
      <div class="box">
        <h2>Paciente</h2>
        <div style="font-size: 12px; font-weight: bold;">{{ $pet->name }}</div>
        <table class="grid">
          <tr>
            <td><span class="label">Especie / raza</span><br>{{ collect([$pet->species?->name, $pet->breed?->name ?? $pet->breed_other])->filter()->join(' · ') ?: '—' }}</td>
            <td><span class="label">Sexo</span><br>{{ \App\Enums\PetAttributes::SEX[$pet->sex] ?? '—' }}</td>
            <td><span class="label">Edad</span><br>{{ $pet->ageLabel() ?? '—' }}</td>
          </tr>
          <tr>
            <td><span class="label">Nacimiento</span><br>{{ $pet->birth_date ? ($pet->birth_date_is_estimated ? '≈ ' : '').$pet->birth_date->format('d/m/Y') : '—' }}</td>
            <td><span class="label">Peso</span><br>{{ $pet->weight_kg ? number_format((float) $pet->weight_kg, 1, ',', '.').' kg' : '—' }}</td>
            <td><span class="label">Microchip</span><br>{{ $pet->microchip ?: '—' }}</td>
          </tr>
          <tr>
            <td><span class="label">Color</span><br>{{ $pet->color ?: '—' }}</td>
            <td colspan="2"><span class="label">Estado reproductivo</span><br>{{ \App\Enums\PetAttributes::REPRODUCTIVE_STATUS[$pet->reproductive_status] ?? '—' }}</td>
          </tr>
        </table>
        @if ($pet->deceased_at)
          <div class="small" style="margin-top: 4px; font-weight: bold;">Fallecido el {{ $pet->deceased_at->format('d/m/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="width: 45%;">
      <div class="box">
        <h2>Tutor</h2>
        <div style="font-weight: bold;">{{ $owner->full_name }}</div>
        @if ($owner->document_number)<div>{{ $owner->document_type }} {{ $owner->document_number }}</div>@endif
        @if ($owner->mobile_phone)<div>{{ preg_replace('/^\+57(\d{3})(\d{3})(\d{4})$/', '+57 $1 $2 $3', $owner->mobile_phone) }}</div>@endif
        @if ($owner->email)<div>{{ $owner->email }}</div>@endif
        @if ($owner->address)<div class="muted">{{ $owner->address }}{{ $owner->neighborhood ? ' · '.$owner->neighborhood : '' }}</div>@endif
      </div>
    </td>
  </tr>
</table>

@if ($pet->alerts->isNotEmpty())
  <div style="margin-top: 10px;">
    <h2>Alertas</h2>
    @foreach ($pet->alerts as $alert)
      <div class="alert">
        <b>{{ \App\Enums\PetAttributes::ALERT_TYPE[$alert->type] ?? $alert->type }}:</b> {{ $alert->description }}
      </div>
    @endforeach
  </div>
@endif

<div style="margin-top: 14px;">
  <h2>{{ $records->count() === 1 ? 'Registro' : 'Registros ('.$records->count().')' }}</h2>
  @forelse ($records as $record)
    @include('pdf.partials.record', ['record' => $record, 'single' => $records->count() === 1])
  @empty
    <p class="muted">No hay registros en el periodo seleccionado.</p>
  @endforelse
</div>

@if ($records->count() === 1 && ! $records->first()['voided'] && $records->first()['author'])
  {{-- A single printed document (e.g. a prescription) carries a signature line. --}}
  <table style="margin-top: 48px;">
    <tr>
      <td style="width: 45%; border-top: 1px solid #98a2b3; padding-top: 4px;">
        <b>{{ $records->first()['author'] }}</b><br>
        <span class="muted">Médico veterinario{{ $records->first()['license'] ? ' · T.P. '.$records->first()['license'] : '' }}</span>
      </td>
      <td></td>
    </tr>
  </table>
@endif

</body>
</html>
