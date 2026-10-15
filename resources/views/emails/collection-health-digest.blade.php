@php
    use App\Services\CollectionHealthService as Health;

    $th = 'padding:6px 8px; border-bottom:1px solid #e5e7eb; font-size:12px; font-weight:600; color:#6b7280; text-align:right; white-space:nowrap;';
    $td = 'padding:6px 8px; border-bottom:1px solid #e5e7eb; font-size:14px; color:#111827; text-align:right; white-space:nowrap;';
    $bad = 'color:#b91c1c; font-weight:700;';
@endphp
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Duomenų surinkimas</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="720" cellpadding="0" cellspacing="0" style="max-width:720px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr>
                    <td style="padding:24px 32px 8px;">
                        <h1 style="margin:0 0 8px; font-size:22px; font-weight:700; color:#111827;">{{ parse_url(config('app.url'), PHP_URL_HOST) }}: duomenų surinkimas, {{ now()->format('Y-m-d H:i') }}</h1>
                        <p style="margin:0; font-size:15px; line-height:1.6; color:#374151;">
                            Kas atėjo per paskutines 24 val. ir kiek iš to tapo nuolaidomis. Visa suvestinė: <a href="{{ url('/admin/collection-health') }}" style="color:#044923;">{{ url('/admin/collection-health') }}</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px 0;">
                        <h2 style="margin:0 0 4px; font-size:18px; font-weight:700; color:#111827;">E-shop scraperiai, 24 val.</h2>
                        <p style="margin:0 0 12px; font-size:13px; line-height:1.5; color:#6b7280;">
                            „Atėjo“ = unikalios prekės, „Įprastai“ = dienų prieš 8–14 d. mediana (raudona, kai atėjo &gt;50 % mažiau). „Be datų“, „Pasibaigę“ ir „Be kainos“ eilučių discounts:process nepanaudoja. „Naujų“ = naujai sukurtos nuolaidos (jau esama nuolaida su tomis pačiomis datomis neskaičiuojama).
                        </p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                            <tr>
                                <th style="{{ $th }} text-align:left;">Parduotuvė</th>
                                <th style="{{ $th }}">Atėjo</th>
                                <th style="{{ $th }}">Įprastai</th>
                                <th style="{{ $th }}">Be datų</th>
                                <th style="{{ $th }}">Pasibaigę</th>
                                <th style="{{ $th }}">Be kainos</th>
                                <th style="{{ $th }}">Naujų</th>
                                <th style="{{ $th }}">Aktyvių</th>
                            </tr>
                            @foreach ($eshop as $row)
                                @php $last = $row['last_24h']; @endphp
                                <tr>
                                    <td style="{{ $td }} text-align:left; font-weight:700;">{{ $row['store'] }}</td>
                                    <td style="{{ $td }} {{ $last['products'] === 0 || ($row['is_anomaly'] && $row['delta'] < 0) ? $bad : '' }}">{{ $last['products'] }}</td>
                                    <td style="{{ $td }} color:#6b7280;">{{ $row['baseline'] ?? '—' }}</td>
                                    <td style="{{ $td }} {{ $last['no_dates'] ? $bad : 'color:#9ca3af;' }}">{{ $last['no_dates'] }}</td>
                                    <td style="{{ $td }} {{ $last['expired'] ? $bad : 'color:#9ca3af;' }}">{{ $last['expired'] }}</td>
                                    <td style="{{ $td }} {{ $last['no_price'] ? $bad : 'color:#9ca3af;' }}">{{ $last['no_price'] }}</td>
                                    <td style="{{ $td }}">{{ $last['created'] ?? '—' }}</td>
                                    <td style="{{ $td }} {{ $row['active'] === 0 || ($row['active'] && $row['active'] < $last['products'] * 0.5) ? $bad : '' }}">{{ $row['active'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 32px 0;">
                        <h2 style="margin:0 0 12px; font-size:18px; font-weight:700; color:#111827;">Reikia dėmesio</h2>
                        @if ($issues)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                @foreach ($issues as $issue)
                                    @php $critical = $issue['severity'] === Health::CRITICAL; @endphp
                                    <tr>
                                        <td valign="top" style="padding:8px 12px 8px 0; border-top:1px solid #e5e7eb; font-size:13px; font-weight:700; white-space:nowrap; color:{{ $critical ? '#b91c1c' : '#b45309' }};">{{ $critical ? 'KRIT' : 'ĮSP' }}</td>
                                        <td valign="top" width="28%" style="padding:8px 12px 8px 0; border-top:1px solid #e5e7eb; font-size:14px; font-weight:700; color:#111827;">{{ $issue['store'] }}</td>
                                        <td style="padding:8px 0; border-top:1px solid #e5e7eb; font-size:14px; line-height:1.5; color:#111827;">{{ $issue['message'] }}@if ($issue['since']) <span style="color:#6b7280;">(nuo {{ $issue['since'] }})</span>@endif</td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <p style="margin:0; font-size:15px; color:#15803d;">Kritinių problemų ir įspėjimų nėra.</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 32px; font-size:13px; color:#9ca3af;">
                        collection-health:digest, kasdien 08:30.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
