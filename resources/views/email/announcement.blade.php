<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $announcement->title }}</title>
</head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="680" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#064e3b,#020617);padding:36px;text-align:center;">
                            <img src="{{ asset('images/CreativeVision LOGO-navsite.png') }}" alt="CreatiVision" style="max-height:84px;width:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:38px 42px;">
                            <p style="margin:0 0 10px;color:#047857;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">Announcement</p>
                            <h1 style="margin:0 0 18px;font-size:28px;line-height:1.3;color:#020617;">{{ $announcement->title }}</h1>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#334155;white-space:pre-line;">{{ $announcement->body }}</p>
                            <p style="margin:26px 0 0;font-size:13px;color:#64748b;">Posted in CreatiVision CRM.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
