<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Prisijungimo nuoroda</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr>
                    <td style="background-color:#0a4f52; padding:24px 32px;">
                        <img src="https://evaistine.lt/assets/logo-white.svg" alt="eVaistine.lt" width="133" height="22" style="display:block; height:22px; width:178px; max-width:178px; border:0;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <h1 style="margin:0 0 16px; font-size:20px; font-weight:700; color:#111827;">Prisijunkite prie eVaistine.lt</h1>
                        <p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#4b5563;">
                            Paspauskite mygtuką žemiau, kad prisijungtumėte. Nuoroda galioja 30 minučių ir gali būti panaudota tik vieną kartą.
                        </p>
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="border-radius:8px; background-color:#0f8b8d;">
                                    <a href="{{ $loginUrl }}" target="_blank" style="display:inline-block; padding:12px 28px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;">Prisijungti</a>
                                </td>
                            </tr>
                        </table>
                        @if (! empty($code))
                            <p style="margin:28px 0 8px; font-size:15px; line-height:1.6; color:#4b5563;">
                                Arba įveskite šį kodą svetainėje, prisijungimo lange:
                            </p>
                            <p style="margin:0; font-size:34px; font-weight:700; letter-spacing:6px; color:#111827; font-family:'Courier New',Courier,monospace;">{{ substr($code, 0, 3) }} {{ substr($code, 3) }}</p>
                        @endif
                        <p style="margin:24px 0 0; font-size:13px; line-height:1.6; color:#9ca3af;">
                            Jei nuorodos neprašėte, tiesiog ignoruokite šį laišką.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                        <p style="margin:0; font-size:12px; color:#9ca3af;">&copy; {{ now()->year }} eVaistine.lt</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
