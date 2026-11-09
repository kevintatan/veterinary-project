<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Comprobante N.º {{ $sale->number }}</title>
<style>
  /* Half letter, sober: printed on any office printer, often in black and white. */
  @page { margin: 26px 30px 40px 30px; }
  * { font-family: "DejaVu Sans", sans-serif; }
  body { font-size: 8.5px; color: #101828; line-height: 1.45; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; }
  .brand { color: #166534; }
  .muted { color: #475467; }
  .rule { border-bottom: 2px solid #166534; margin: 8px 0 10px; }
  .title { font-size: 12px; font-weight: bold; margin: 0; }
  .label { color: #475467; font-size: 7px; text-transform: uppercase; letter-spacing: .3px; }
  .items th { font-size: 7px; text-transform: uppercase; color: #475467; text-align: left; border-bottom: 1px solid #d0d5dd; padding: 3px 0; }
  .items th.num { text-align: right; }
  .items td { padding: 4px 0; border-bottom: 1px solid #eaecf0; }
  .num { text-align: right; white-space: nowrap; }
  .totals td { padding: 2px 0; }
  .grand td { font-size: 11px; font-weight: bold; border-top: 1px solid #101828; padding-top: 5px; }
  .notice { margin-top: 12px; border: 1px solid #d0d5dd; padding: 6px 8px; font-size: 7.5px; color: #344054; text-align: center; }
  .mark { position: fixed; top: 240px; left: 0; right: 0; text-align: center; font-size: 46px; font-weight: bold; color: #d0d5dd; letter-spacing: 5px; }
  .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 6.5px; color: #667085; text-align: center; }
</style>
</head>
<body>

@if ($sale->status === 'void')<div class="mark">ANULADA</div>@endif

<table>
  <tr>
    <td>
      @if ($logo)<img src="{{ $logo }}" style="height: 30px; margin-bottom: 3px;" alt="">@endif
      <div style="font-size: 11px; font-weight: bold;">{{ $clinic->legal_name ?: $clinic->name }}</div>
      <div class="muted">
        @if ($clinic->tax_id)NIT {{ $clinic->tax_id }}<br>@endif
        {{ $sale->branch?->address ?: $clinic->address }}@if ($clinic->phone) · Tel. {{ $clinic->phone }}@endif
      </div>
    </td>
    <td style="text-align: right; width: 42%;">
      <p class="title brand">Comprobante de pago</p>
      <div style="font-size: 11px; font-weight: bold;">N.º {{ str_pad((string) $sale->number, 6, '0', STR_PAD_LEFT) }}</div>
      <div class="muted">{{ $soldAt->format('d/m/Y') }} · {{ $soldAt->format('g:i') }} {{ $soldAt->hour < 12 ? 'a. m.' : 'p. m.' }}</div>
    </td>
  </tr>
</table>
<div class="rule"></div>

<table style="margin-bottom: 8px;">
  <tr>
    <td>
      <div class="label">Cliente</div>
      @if ($sale->customer_snapshot)
        <div>{{ $sale->customer_snapshot['name'] }}</div>
        <div class="muted">{{ collect([$sale->customer_snapshot['document'] ?? null, \App\Support\Phone::display($sale->customer_snapshot['phone'] ?? null)])->filter()->implode(' · ') }}</div>
      @else
        <div>Venta de mostrador</div>
      @endif
    </td>
    <td style="text-align: right;">
      <div class="label">Atendió</div>
      <div>{{ $sale->seller?->name }}</div>
      @if ($sale->branch)<div class="muted">{{ $sale->branch->name }}</div>@endif
    </td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr><th>Descripción</th><th class="num">Cant.</th><th class="num">Valor unit.</th><th class="num">Total</th></tr>
  </thead>
  <tbody>
    @foreach ($sale->items as $item)
      <tr>
        <td>
          {{ $item->description }}
          @if ((float) $item->discount > 0)<div class="muted">Descuento {{ $money($item->discount) }}</div>@endif
        </td>
        <td class="num">{{ $qty($item->quantity) }}</td>
        <td class="num">{{ $money($item->unit_price) }}</td>
        <td class="num">{{ $money($item->total) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<table class="totals" style="margin-top: 6px; width: 55%; margin-left: 45%;">
  @if ((float) $sale->discount_total > 0)
    <tr><td class="muted">Subtotal</td><td class="num">{{ $money($sale->subtotal) }}</td></tr>
    <tr><td class="muted">Descuentos</td><td class="num">- {{ $money($sale->discount_total) }}</td></tr>
  @endif
  @if ((float) $sale->tax_total > 0)
    <tr><td class="muted">IVA incluido</td><td class="num">{{ $money($sale->tax_total) }}</td></tr>
  @endif
  <tr class="grand"><td>Total</td><td class="num">{{ $money($sale->total) }}</td></tr>
  @foreach ($payments as $p)
    <tr><td class="muted">{{ $p['method'] }}@if ($p['reference']) ({{ $p['reference'] }})@endif</td><td class="num">{{ $money($p['amount']) }}</td></tr>
  @endforeach
  @if ($sale->status !== 'void' && $sale->balance() > 0)
    <tr><td><b>Saldo pendiente</b></td><td class="num"><b>{{ $money($sale->balance()) }}</b></td></tr>
  @endif
</table>

@if ($sale->status === 'void')
  <div class="notice">Venta anulada{{ $voidedBy ? " por {$voidedBy}" : '' }}: {{ $sale->void_reason }}</div>
@endif

<div class="notice">Comprobante de pago · <b>no es factura electrónica</b>. Si necesitas factura, solicítala a la clínica.</div>

<div class="footer">{{ $clinic->name }} · Generado con Zooetia</div>
</body>
</html>
