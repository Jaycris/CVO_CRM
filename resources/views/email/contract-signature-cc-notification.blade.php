<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your signing request has been successfully sent!</title>
</head>
@php
    $brandName = $packet['brandName'] ?? $packet['senderName'];
    $brandLogoUrl = $packet['brandLogoUrl'] ?? null;
    $brandPrimaryColor = $packet['brandPrimaryColor'] ?? '#047857';
    $crmName = $packet['crmName'] ?? 'VisionFlow CRM';
@endphp
<body style="margin:0; padding:0; background:#f5f7fb; font-family:Arial, sans-serif; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f7fb; padding:24px;">
        <tr>
            <td align="center">
                <table width="620" cellpadding="0" cellspacing="0" role="presentation" style="max-width:620px; width:100%; background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px; border-bottom:1px solid #e2e8f0;">
                            @if ($brandLogoUrl)
                                <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }}" style="display:block; max-width:220px; max-height:56px; width:auto; height:auto;">
                                <div style="margin-top:12px; font-size:16px; font-weight:700; color:#0f172a;">{{ $brandName }}</div>
                            @else
                                <div style="font-size:28px; font-weight:800; color:{{ $brandPrimaryColor }};">{{ $brandName }}</div>
                            @endif
                            <div style="margin-top:6px; color:#64748b; font-size:14px;">Contract signature request powered by {{ $crmName }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0; font-size:24px; line-height:1.3;">Your signing request has been successfully sent!</h1>
                            <p style="margin:16px 0 0; font-size:15px; line-height:1.6; color:#475569;">
                                Hi {{ $ccName }},
                            </p>
                            <p style="margin:12px 0 0; font-size:15px; line-height:1.6; color:#475569;">
                                {{ $senderUserName }} has sent a contract for electronic signature to {{ $recipientName }}.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:24px; background:#f8fafc; border-radius:12px;">
                                <tr>
                                    <td style="padding:18px;">
                                        <div style="font-size:13px; color:#64748b;">Document</div>
                                        <div style="margin-top:4px; font-size:16px; font-weight:700;">{{ $packet['title'] }}</div>
                                        <div style="margin-top:10px; font-size:13px; color:#64748b;">Document ID: {{ $packet['documentId'] }}</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; font-size:13px; line-height:1.6; color:#64748b;">
                                This notice was generated through {{ $crmName }} for {{ $brandName }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
