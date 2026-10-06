<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Naujas leidinys</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="max-width:520px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr>
                    <td style="background-color:#0a4f52; padding:24px 32px;">
                        <img src="https://evaistine.lt/assets/logo-white.svg" alt="eVaistine.lt" width="133" height="22" style="display:block; height:22px; width:178px; max-width:178px; border:0;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 32px 12px;">
                        <h1 style="margin:0; font-size:24px; font-weight:700; color:#111827;">{{ count($leaflets) === 1 ? 'Pasirodė naujas leidinys' : 'Pasirodė nauji leidiniai' }}</h1>
                    </td>
                </tr>
                @foreach ($leaflets as $leaflet)
                    <tr>
                        <td style="padding:0 32px 14px;">
                            <a href="{{ \App\Mail\NewLeafletMail::trackedUrl($leaflet['url']) }}" target="_blank" style="text-decoration:none;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:12px;">
                                    <tr>
                                        <td style="width:96px; padding:10px;">
                                            @if ($leaflet['image'])
                                                <img src="{{ $leaflet['image'] }}" alt="" width="84" height="112" style="display:block; width:84px; height:112px; object-fit:cover; border-radius:6px; border:0;">
                                            @endif
                                        </td>
                                        <td style="padding:10px 14px 10px 4px; vertical-align:middle;">
                                            <p style="margin:0 0 4px; font-size:14px; font-weight:700; text-transform:uppercase; color:#6b7280;">{{ $leaflet['store_name'] }}</p>
                                            <p style="margin:0 0 6px; font-size:18px; font-weight:700; line-height:1.35; color:#111827;">{{ $leaflet['title'] }}</p>
                                            @if ($leaflet['dates'])
                                                <p style="margin:0 0 8px; font-size:15px; color:#0a4f52;">Galioja {{ $leaflet['dates'] }}</p>
                                            @endif
                                            <span style="display:inline-block; padding:8px 14px; border-radius:8px; background-color:#0b7275; font-size:15px; font-weight:700; color:#ffffff;">Žiūrėti leidinį</span>
                                        </td>
                                    </tr>
                                </table>
                            </a>
                        </td>
                    </tr>
                @endforeach
                <tr><td style="padding:0 0 14px;"></td></tr>
                @include('emails.partials.subscriber-footer')
            </table>
        </td>
    </tr>
</table>
</body>
</html>
