<?php

namespace App\Notifications;

use App\Models\EndOfShiftReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EndOfShiftReportSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly EndOfShiftReport $report)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = trim(($this->report->user?->first_name ?? '').' '.($this->report->user?->last_name ?? '')) ?: 'Production user';

        return [
            'title' => 'End of Shift report submitted',
            'message' => "{$name} submitted an End of Shift report.",
            'author_name' => $name,
            'book_title' => $this->report->shift_date?->format('M d, Y') ?? 'End of Shift',
            'url' => route('production.eos.index'),
        ];
    }
}
