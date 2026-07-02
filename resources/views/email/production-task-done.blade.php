@php
    $crmName = 'CreatiVision CRM';
    $brandName = 'CreatiVision Outsourcing';
    $brandLogo = asset('images/CreativeVision LOGO 1.png');
    $headerBackground = 'linear-gradient(135deg, #064e3b 0%, #022c22 45%, #050505 100%)';
    $buttonBackground = 'linear-gradient(135deg, #065f46 0%, #022c22 100%)';
    $taskTitle = $completedTask?->title ?: 'Production Task';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $crmName }} Production Update</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:40px;">
    <div style="max-width:640px; margin:auto; overflow:hidden; background:#ffffff; border-radius:14px;">
        <div style="background:{{ $headerBackground }}; padding:30px 40px; text-align:center;">
            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" style="max-width:240px; width:100%; height:auto;">
        </div>

        <div style="padding:40px;">
            <p style="margin:0 0 10px; color:#047857; font-size:13px; font-weight:bold; letter-spacing:1.5px; text-transform:uppercase;">
                Production Update
            </p>

            <h2 style="margin:0 0 18px; color:#111827; font-size:26px;">
                {{ $count > 1 ? "{$count} production tasks are done" : 'Production task completed' }}
            </h2>

            @if ($count > 1)
                <p style="margin:0 0 22px; color:#374151; font-size:15px; line-height:1.6;">
                    {{ $count }} assigned production tasks were marked as done. Please review them in the Fulfillment Tracker.
                </p>
            @else
                <p style="margin:0 0 22px; color:#374151; font-size:15px; line-height:1.6;">
                    {{ $assigneeName }} marked a production task as done. Please review the work details below.
                </p>

                <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:18px; margin:0 0 24px;">
                    <p style="margin:0 0 10px; color:#64748b; font-size:12px; font-weight:bold; text-transform:uppercase;">Author</p>
                    <p style="margin:0 0 16px; color:#111827; font-size:15px; font-weight:bold;">{{ $endorsement?->author_name ?: '-' }}</p>

                    <p style="margin:0 0 10px; color:#64748b; font-size:12px; font-weight:bold; text-transform:uppercase;">Book Title</p>
                    <p style="margin:0 0 16px; color:#111827; font-size:15px; font-weight:bold;">{{ $endorsement?->book_title ?: '-' }}</p>

                    <p style="margin:0 0 10px; color:#64748b; font-size:12px; font-weight:bold; text-transform:uppercase;">Task</p>
                    <p style="margin:0; color:#111827; font-size:15px; font-weight:bold;">{{ $taskTitle }}</p>
                </div>

                @if ($completedTask?->result_link)
                    <div style="margin:0 0 16px;">
                        <a href="{{ $completedTask->result_link }}"
                           style="background:#111827; color:#ffffff; text-decoration:none; padding:13px 20px; border-radius:10px; display:inline-block; font-weight:bold; font-size:14px;">
                            Open Result Link
                        </a>
                    </div>
                @endif
            @endif

            <div style="margin:28px 0;">
                <a href="{{ $trackerUrl }}"
                   style="background:{{ $buttonBackground }}; color:#ffffff; text-decoration:none; padding:14px 24px; border-radius:10px; display:inline-block; font-weight:bold; font-size:15px;">
                    Open Fulfillment Tracker
                </a>
            </div>

            <hr style="border:0; border-top:1px solid #e5e7eb; margin:30px 0;">

            <small style="color:#777;">
                {{ $crmName }} &middot; {{ $brandName }}
            </small>
        </div>
    </div>
</body>
</html>
