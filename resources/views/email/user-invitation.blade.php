@php
    $brandName = 'CreatiVision Outsourcing';
    $crmName = 'CreatiVision CRM';
    $brandPrimary = $brand?->primary_color ?: '#065f46';
    $brandAccent = $brand?->accent_color ?: '#d1fae5';
    $brandLogo = asset('images/CreativeVision-LOGO-1.png');
    $headerBackground = 'linear-gradient(135deg, #064e3b 0%, #022c22 45%, #050505 100%)';
    $buttonBackground = 'linear-gradient(135deg, #065f46 0%, #022c22 100%)';
    $buttonBorder = '#065f46';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $crmName }} Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:40px;">

    <div style="max-width:600px; margin:auto; overflow:hidden; background:#ffffff; border-radius:14px;">
        <div style="background:{{ $headerBackground }}; padding:30px 40px; text-align:center;">
            <img src="{{ $brandLogo }}"
                 alt="{{ $brandName }}"
                 style="max-width:240px; width:100%; height:auto;">
        </div>

        <div style="padding:40px;">
            <h2 style="margin:0 0 18px; color:#111827; font-size:24px;">
                Hello {{ $user->first_name }},
            </h2>

            <p style="margin:0 0 14px; color:#374151; font-size:15px; line-height:1.6;">
                Your {{ $crmName }} account has been created successfully.
            </p>

            <p style="margin:0; color:#374151; font-size:15px; line-height:1.6;">
                Please click the button below to verify your email and create your password.
            </p>

            <div style="margin:32px 0;">
                <a href="{{ $invitationUrl }}"
                   style="
                        background:{{ $buttonBackground }};
                        border:1px solid {{ $buttonBorder }};
                        color:#ffffff;
                        text-decoration:none;
                        padding:14px 24px;
                        border-radius:10px;
                        display:inline-block;
                        font-weight:bold;
                        font-size:15px;
                   ">
                    Create Password
                </a>
            </div>

            <p style="margin:0 0 14px; color:#374151; font-size:15px; line-height:1.6;">
                This invitation link will expire in <strong>7 days</strong>.
            </p>

            <p style="margin:0; color:#6b7280; font-size:14px; line-height:1.6;">
                If you did not expect this email, please ignore it.
            </p>

            <hr style="border:0; border-top:1px solid #e5e7eb; margin:30px 0;">

            <small style="color:#777;">
                {{ $crmName }} &middot; {{ $brandName }}
            </small>
        </div>

    </div>

</body>
</html>
