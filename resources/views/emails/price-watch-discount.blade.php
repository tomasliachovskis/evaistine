<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atpigo sekama prekė</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Helvetica,Arial,sans-serif;">
@php
    // Same format as favorites/index.blade.php's $euro() helper.
    $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';
@endphp
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
                    <td style="padding:32px 32px 8px;">
                        <h1 style="margin:0 0 8px; font-size:20px; font-weight:700; color:#111827;">
                            {{ $productGroups->count() === 1 ? 'Atpigo prekė, kurią sekate' : 'Atpigo prekės, kurias sekate' }}
                        </h1>
                        <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#4b5563;">
                            Radome naujų nuolaidų prekėms iš jūsų sekamo sąrašo.
                        </p>
                    </td>
                </tr>
                @if ($totalSavings > 0)
                    <tr>
                        <td style="padding:0 32px 20px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:12px; background-color:#f5f8fc;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 4px; font-size:14px; color:#374151;">Galite sutaupyti dabar</p>
                                        <p style="margin:0; font-size:28px; font-weight:800; line-height:1.2; color:#0f234a;">{{ $euro($totalSavings) }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif
                @foreach ($productGroups as $group)
                    @php
                        $cheapest = $group->first();
                        $product = $cheapest->product;
                        $roundedPercent = $cheapest->discount_percent !== null ? round($cheapest->discount_percent) : null;
                        $productUrl = \App\Mail\PriceWatchDiscountMail::trackedUrl('https://evaistine.lt/akcijos/' . ($product->category?->slug ?? '') . '/' . $product->slug, 'product');
                    @endphp
                    <tr>
                        <td style="padding:0 32px 20px;">
                            <a href="{{ $productUrl }}" target="_blank" style="text-decoration:none;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:12px;">
                                    <tr>
                                        <td style="width:72px; padding:12px;">
                                            @if ($product->image_url)
                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" width="60" height="60" style="display:block; width:60px; height:60px; object-fit:contain; border:0;">
                                            @endif
                                        </td>
                                        <td style="padding:12px 12px 12px 0;">
                                            <p style="margin:0 0 4px; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase;">{{ $cheapest->store->name }}</p>
                                            <p style="margin:0 0 6px; font-size:14px; font-weight:600; line-height:1.4; color:#111827;">{{ $product->name }}</p>
                                            <table role="presentation" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td style="padding-right:8px;">
                                                        <span style="font-size:16px; font-weight:700; color:#2b5ba8;">{{ $euro($cheapest->discounted_price) }}</span>
                                                    </td>
                                                    @if ($cheapest->original_price)
                                                        <td style="padding-right:8px;">
                                                            <span style="font-size:13px; color:#9ca3af; text-decoration:line-through;">{{ $euro($cheapest->original_price) }}</span>
                                                        </td>
                                                    @endif
                                                    @if ($roundedPercent !== null && $roundedPercent >= 20)
                                                        <td>
                                                            <span style="display:inline-block; border-radius:6px; background-color:#b5125e; padding:2px 8px; font-size:12px; font-weight:700; color:#ffffff;">-{{ $roundedPercent }}%</span>
                                                        </td>
                                                    @endif
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </a>
                            @if ($group->count() > 1)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-left:1px solid #e5e7eb; border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; border-radius:0 0 12px 12px; margin-top:-1px;">
                                    @foreach ($group as $storeDiscount)
                                        <tr>
                                            <td style="padding:8px 0 8px 16px; {{ !$loop->last ? 'border-bottom:1px solid #f3f4f6;' : '' }}">
                                                <span style="font-size:12px; color:#6b7280;">{{ $storeDiscount->store->name }}</span>
                                            </td>
                                            <td align="right" style="padding:8px 16px 8px 0; {{ !$loop->last ? 'border-bottom:1px solid #f3f4f6;' : '' }}">
                                                <span style="font-size:12px; font-weight:600; color:#374151;">{{ $euro($storeDiscount->discounted_price) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td style="padding:0 32px 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="border-radius:8px; background-color:#2b5ba8;">
                                    <a href="{{ $favoritesUrl }}" target="_blank" style="display:block; padding:14px 28px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px; text-align:center;">Peržiūrėti sekamas prekes</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                        <p style="margin:0 0 8px; font-size:12px; color:#9ca3af;">
                            Nenorite gauti tokių priminimų? <a href="{{ $unsubscribeUrl }}" style="color:#9ca3af; text-decoration:underline;">Spauskite čia, kad atsisakytumėte</a>.
                        </p>
                        <p style="margin:0; font-size:12px; color:#9ca3af;">&copy; {{ now()->year }} eVaistine.lt</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
