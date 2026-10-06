<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Šios savaitės akcijos</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Helvetica,Arial,sans-serif;">
@php
    $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';
    $track = fn (string $url, string $content) => \App\Mail\WeeklyDigestMail::trackedUrl($url, $content);
    $image = fn (?string $url) => $url ? (str_starts_with($url, 'http') ? $url : url($url)) : null;
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="max-width:520px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr>
                    <td style="background-color:#0f234a; padding:24px 32px;">
                        <img src="https://evaistine.lt/assets/logo-white.svg" alt="eVaistine.lt" width="133" height="22" style="display:block; height:22px; width:178px; max-width:178px; border:0;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 32px 8px;">
                        <h1 style="margin:0 0 8px; font-size:24px; font-weight:700; color:#111827;">Šios savaitės akcijos</h1>
                        <p style="margin:0; font-size:17px; line-height:1.5; color:#374151;">{{ $stores->pluck('name')->implode(', ') }}</p>
                    </td>
                </tr>

                @if ($leaflets !== [])
                    <tr>
                        <td style="padding:20px 32px 4px;">
                            <h2 style="margin:0 0 12px; font-size:20px; font-weight:700; color:#111827;">Nauji leidiniai</h2>
                        </td>
                    </tr>
                    @foreach ($leaflets as $leaflet)
                        <tr>
                            <td style="padding:0 32px 14px;">
                                <a href="{{ $track($leaflet['url'], 'leaflet') }}" target="_blank" style="text-decoration:none;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:12px;">
                                        <tr>
                                            <td style="width:84px; padding:10px;">
                                                @if ($leaflet['image'])
                                                    <img src="{{ $leaflet['image'] }}" alt="" width="72" height="96" style="display:block; width:72px; height:96px; object-fit:cover; border-radius:6px; border:0;">
                                                @endif
                                            </td>
                                            <td style="padding:10px 14px 10px 4px; vertical-align:middle;">
                                                <p style="margin:0 0 4px; font-size:14px; font-weight:700; text-transform:uppercase; color:#6b7280;">{{ $leaflet['store_name'] }}</p>
                                                <p style="margin:0 0 4px; font-size:17px; font-weight:700; line-height:1.35; color:#111827;">{{ $leaflet['title'] }}</p>
                                                @if ($leaflet['dates'])
                                                    <p style="margin:0; font-size:15px; color:#0f234a;">Galioja {{ $leaflet['dates'] }}</p>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                @endif

                @if ($offers->isNotEmpty())
                    <tr>
                        <td style="padding:20px 32px 4px;">
                            <h2 style="margin:0 0 12px; font-size:20px; font-weight:700; color:#111827;">Geriausi pasiūlymai</h2>
                        </td>
                    </tr>
                    @foreach ($offers as $discount)
                        @php
                            $product = $discount->product;
                            $productUrl = $track(url(\App\Support\PageUrl::product($product->slug)), 'offer');
                            $percent = $discount->discount_percent ? round($discount->discount_percent) : null;
                        @endphp
                        <tr>
                            <td style="padding:0 32px 14px;">
                                <a href="{{ $productUrl }}" target="_blank" style="text-decoration:none;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:12px;">
                                        <tr>
                                            <td style="width:84px; padding:10px;">
                                                @if ($image($product->image_url))
                                                    <img src="{{ $image($product->image_url) }}" alt="" width="72" height="72" style="display:block; width:72px; height:72px; object-fit:contain; border:0;">
                                                @endif
                                            </td>
                                            <td style="padding:10px 14px 10px 4px; vertical-align:middle;">
                                                <p style="margin:0 0 4px; font-size:16px; line-height:1.35; color:#111827;">{{ $product->name }}</p>
                                                <p style="margin:0; font-size:20px; font-weight:800; color:#111827;">
                                                    {{ $discount->discounted_price ? $euro($discount->discounted_price) : '' }}
                                                    @if ($percent)
                                                        <span style="display:inline-block; margin-left:6px; padding:2px 8px; border-radius:6px; background-color:#b5125e; font-size:15px; font-weight:800; color:#ffffff;">-{{ $percent }}%</span>
                                                    @endif
                                                </p>
                                                <p style="margin:4px 0 0; font-size:14px; color:#6b7280;">{{ $discount->store?->name }}</p>
                                            </td>
                                        </tr>
                                    </table>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                @endif

                <tr>
                    <td style="padding:12px 32px 28px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="border-radius:10px; background-color:#13306a;">
                                    <a href="{{ $track($allOffersUrl, 'all_offers') }}" target="_blank" style="display:inline-block; padding:14px 26px; font-size:17px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">Visos jūsų vaistinių akcijos</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @include('emails.partials.subscriber-footer')
            </table>
        </td>
    </tr>
</table>
</body>
</html>
