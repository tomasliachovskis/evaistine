<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patvirtinkite prenumeratą</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr>
                    <td style="background-color:#0f234a; padding:24px 32px;">
                        <img src="https://evaistine.lt/assets/logo-white.svg" alt="eVaistine.lt" width="133" height="22" style="display:block; height:22px; width:178px; max-width:178px; border:0;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <h1 style="margin:0 0 16px; font-size:22px; font-weight:700; color:#111827;">Patvirtinkite el. paštą</h1>
                        <p style="margin:0 0 24px; font-size:17px; line-height:1.6; color:#374151;">
                            Kas ketvirtadienį atsiųsime naujus jūsų parduotuvių leidinius ir geriausias savaitės akcijas. Paspauskite mygtuką, kad pradėtumėte gauti laiškus.
                        </p>
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="border-radius:10px; background-color:#13306a;">
                                    <a href="{{ $confirmUrl }}" target="_blank" style="display:inline-block; padding:14px 28px; font-size:17px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">Taip, noriu gauti</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:24px 0 0; font-size:14px; line-height:1.6; color:#6b7280;">
                            Jei to neprašėte, tiesiog ignoruokite šį laišką. Be patvirtinimo laiškų nesiųsime.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                        <p style="margin:0; font-size:13px; color:#9ca3af;">&copy; {{ now()->year }} eVaistine.lt</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
