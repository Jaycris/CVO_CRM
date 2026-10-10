<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Support\SimpleImapClient;
use Illuminate\Support\Carbon;

class EmailInboxSyncService
{
    public function sync(EmailAccount $account, int $limit = 50): int
    {
        $client = new SimpleImapClient(
            $account->imap_host,
            $account->imap_port,
            $account->imap_encryption,
            $account->username,
            $account->plainPassword() ?? ''
        );

        $client->connect();

        try {
            $synced = 0;

            foreach ($client->messages('INBOX', $limit) as $message) {
                $emailMessage = EmailMessage::withTrashed()->firstOrNew([
                    'email_account_id' => $account->id,
                    'folder' => 'INBOX',
                    'uid' => $message['uid'],
                ]);

                if ($emailMessage->trashed()) {
                    continue;
                }

                $emailMessage->fill([
                    'message_id' => $message['message_id'],
                    'subject' => $message['subject'],
                    'from_name' => $message['from_name'],
                    'from_email' => $message['from_email'],
                    'body_text' => $message['body_text'],
                    'body_html' => $message['body_html'],
                    'sent_at' => $this->parseMessageDate($message['sent_at']),
                    'is_seen' => $emailMessage->exists ? ($emailMessage->is_seen || $message['is_seen']) : $message['is_seen'],
                    'is_answered' => $message['is_answered'],
                    'has_attachments' => $message['has_attachments'],
                ]);

                if (! $emailMessage->exists || $emailMessage->isDirty()) {
                    $synced++;
                }

                $emailMessage->save();
            }

            $account->update(['last_synced_at' => now()]);

            return $synced;
        } finally {
            $client->disconnect();
        }
    }

    private function parseMessageDate(?string $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }
}
