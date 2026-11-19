<?php

namespace App\Support;

use App\Models\BrandMailSetting;
use App\Models\EmailAccount;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;

class BrandMailer
{
    public static function account(?int $brandId): BrandMailSetting|EmailAccount|null
    {
        if (! $brandId) return null;
        return BrandMailSetting::where('brand_id', $brandId)->where('is_enabled', true)->first()
            ?? EmailAccount::where('brand_id', $brandId)->where('is_shared', true)->orderBy('id')->first();
    }

    public static function mailer(BrandMailSetting|EmailAccount|null $account): ?string
    {
        if (! $account) return null;
        $name = ($account instanceof BrandMailSetting ? 'brand_sender_' : 'contract_brand_').$account->id;
        config(['mail.mailers.'.$name => [
            'transport' => 'smtp', 'scheme' => $account->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $account->smtp_host, 'port' => $account->smtp_port,
            'username' => $account->username, 'password' => $account->plainPassword() ?? '',
            'timeout' => 15, 'local_domain' => config('mail.mailers.smtp.local_domain'),
            'require_tls' => true,
        ]]);
        Mail::purge($name);
        return $name;
    }

    public static function send(BrandMailSetting|EmailAccount|null $account, string $to, Mailable $mail): void
    {
        if ($account) {
            $mail->from($account->email_address, $account->display_name);
            $mail->replyTo($account->reply_to_address ?: $account->email_address, $account->display_name);
        }
        Mail::mailer(self::mailer($account))->to($to)->send($mail);
    }

    public static function notification(?int $brandId, MailMessage $message): MailMessage
    {
        $account = self::account($brandId);
        if (! $account) return $message;
        return $message->mailer(self::mailer($account))->from($account->email_address, $account->display_name)
            ->replyTo($account->reply_to_address ?: $account->email_address, $account->display_name);
    }
}
