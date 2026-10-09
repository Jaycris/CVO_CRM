<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\User;
use App\Support\SimpleImapClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailMailboxController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $accounts = $this->accessibleAccounts($user)->get();
        $account = $accounts->firstWhere('id', (int) $request->query('account')) ?? $accounts->first();
        $folder = in_array($request->query('folder'), ['INBOX', 'Sent'], true) ? $request->query('folder') : 'INBOX';

        $messages = $account
            ? $account->messages()
                ->where('folder', $folder)
                ->latest('sent_at')
                ->latest()
                ->paginate(20)
                ->withQueryString()
            : collect();

        $selectedMessage = $account && $request->filled('message')
            ? $account->messages()->whereKey($request->query('message'))->first()
            : null;

        $users = $this->canManageEmailAccounts($user)
            ? User::query()
                ->whereNull('suspended_at')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
            : collect();
        $settingsAccount = $request->boolean('new') ? null : $account;

        return view('email.mailbox', [
            'accounts' => $accounts,
            'account' => $account,
            'users' => $users,
            'folder' => $folder,
            'messages' => $messages,
            'selectedMessage' => $selectedMessage,
            'canManageEmailAccounts' => $this->canManageEmailAccounts($user),
            'settingsAccount' => $settingsAccount,
            'emailSignature' => $this->emailSignature($user),
            'signatureEditorHtml' => $this->signatureEditorHtml($user),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canManageEmailAccounts($user), 403);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'email_address' => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);
        $assignedUser = User::findOrFail($validated['user_id']);

        $account = new EmailAccount([
            'user_id' => $assignedUser->id,
            'brand_id' => $assignedUser->brand_id,
            'display_name' => $validated['display_name'],
            'email_address' => mb_strtolower($validated['email_address']),
            'username' => $validated['username'],
            'imap_host' => $validated['imap_host'],
            'imap_port' => $validated['imap_port'],
            'imap_encryption' => $validated['imap_encryption'],
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'],
            'smtp_encryption' => $validated['smtp_encryption'],
            'is_shared' => false,
        ]);
        $account->setPlainPassword($validated['password']);
        $account->save();

        return redirect()
            ->route('email.index', ['account' => $account->id, 'settings' => 1])
            ->with('success', 'Employee mailbox connected. Only the assigned employee will see it after login.');
    }

    public function updateAccount(Request $request, EmailAccount $account)
    {
        abort_unless($this->canManageEmailAccounts($request->user()), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'display_name' => ['required', 'string', 'max:255'],
            'email_address' => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
        ]);
        $assignedUser = User::findOrFail($validated['user_id']);

        $account->fill([
            'user_id' => $assignedUser->id,
            'brand_id' => $assignedUser->brand_id,
            'display_name' => $validated['display_name'],
            'email_address' => mb_strtolower($validated['email_address']),
            'username' => $validated['username'],
            'imap_host' => $validated['imap_host'],
            'imap_port' => $validated['imap_port'],
            'imap_encryption' => $validated['imap_encryption'],
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'],
            'smtp_encryption' => $validated['smtp_encryption'],
            'is_shared' => false,
        ]);
        $account->setPlainPassword($validated['password'] ?? null);
        $account->save();

        return redirect()
            ->route('email.index', ['account' => $account->id])
            ->with('success', 'Email account settings updated.');
    }

    public function sync(Request $request, EmailAccount $account)
    {
        $this->authorizeAccount($request->user(), $account);

        try {
            $synced = $this->syncInbox($account);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('email.index', ['account' => $account->id])
                ->with('error', 'The mailbox could not be refreshed. Please check the IMAP settings and password.');
        }

        return redirect()
            ->route('email.index', ['account' => $account->id])
            ->with('success', "{$synced} inbox message(s) refreshed.");
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'email_account_id' => ['required', 'integer', Rule::exists('email_accounts', 'id')],
            'to' => ['required', 'string', 'max:1000'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'include_signature' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'inline_images' => ['nullable', 'array'],
            'inline_images.*' => ['image', 'max:10240'],
        ]);

        $account = EmailAccount::findOrFail($validated['email_account_id']);
        $this->authorizeAccount($request->user(), $account);

        $to = $this->parseAddressList($validated['to']);
        $cc = $this->parseAddressList($validated['cc'] ?? '');

        if ($to === []) {
            throw ValidationException::withMessages([
                'to' => 'Please enter at least one valid recipient email address.',
            ]);
        }

        try {
            $attachments = array_merge(
                $request->file('attachments', []),
                $request->file('inline_images', [])
            );
            $signature = $request->boolean('include_signature')
                ? $this->emailSignature($request->user())
                : null;
            $bodyText = $signature
                ? rtrim($validated['body']) . "\n\n" . $signature['text']
                : $validated['body'];
            $this->sendSmtpMessage($account, $to, $cc, $validated['subject'], $bodyText, $attachments, $signature);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('email.index', ['account' => $account->id])
                ->withInput()
                ->with('error', 'The email could not be sent. Please check the SMTP settings and password.');
        }

        EmailMessage::create([
            'email_account_id' => $account->id,
            'folder' => 'Sent',
            'subject' => $validated['subject'],
            'from_name' => $account->display_name,
            'from_email' => $account->email_address,
            'to' => $to,
            'cc' => $cc,
            'body_text' => $bodyText,
            'sent_at' => now(),
            'is_seen' => true,
            'has_attachments' => ! empty($attachments),
        ]);

        return redirect()
            ->route('email.index', ['account' => $account->id, 'folder' => 'Sent'])
            ->with('success', 'Email sent successfully.');
    }

    public function updateSignature(Request $request)
    {
        $validated = $request->validate([
            'email_signature_enabled' => ['nullable', 'boolean'],
            'email_signature_name' => ['nullable', 'string', 'max:255'],
            'email_signature_title' => ['nullable', 'string', 'max:255'],
            'email_signature_contact_number' => ['nullable', 'string', 'max:255'],
            'email_signature_html' => ['nullable', 'string', 'max:2000000'],
        ]);

        $request->user()->forceFill([
            'email_signature_enabled' => $request->boolean('email_signature_enabled'),
            'email_signature_name' => $validated['email_signature_name'] ?? null,
            'email_signature_title' => $validated['email_signature_title'] ?? null,
            'email_signature_contact_number' => $validated['email_signature_contact_number'] ?? null,
            'email_signature_html' => $this->sanitizeSignatureHtml($validated['email_signature_html'] ?? null),
        ])->save();

        return redirect()
            ->route('email.index', [
                'account' => $request->query('account'),
                'folder' => $request->query('folder'),
            ])
            ->with('success', 'Email signature updated.');
    }

    private function accessibleAccounts(User $user)
    {
        return EmailAccount::query()
            ->when(! $this->canManageEmailAccounts($user), function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['brand', 'user'])
            ->orderByRaw('user_id is null')
            ->orderBy('display_name');
    }

    private function authorizeAccount(User $user, EmailAccount $account): void
    {
        abort_unless(
            $this->canManageEmailAccounts($user)
            || $account->user_id === $user->id,
            403
        );
    }

    private function canManageEmailAccounts(User $user): bool
    {
        return $user->role?->name === 'Admin';
    }

    private function sendSmtpMessage(EmailAccount $account, array $to, array $cc, string $subject, string $body, array $attachments = [], ?array $signature = null): void
    {
        $transport = new EsmtpTransport(
            $account->smtp_host,
            $account->smtp_port,
            $account->smtp_encryption !== 'none'
        );
        $transport->setUsername($account->username);
        $transport->setPassword($account->plainPassword() ?? '');

        $email = (new Email)
            ->from(new Address($account->email_address, $account->display_name))
            ->subject($subject)
            ->text($body)
            ->html($this->messageHtml($body, $signature));

        foreach ($to as $address) {
            $email->addTo($address);
        }

        foreach ($cc as $address) {
            $email->addCc($address);
        }

        foreach ($attachments as $attachment) {
            if ($attachment && $attachment->isValid()) {
                $email->attachFromPath(
                    $attachment->getRealPath(),
                    $attachment->getClientOriginalName(),
                    $attachment->getMimeType()
                );
            }
        }

        (new Mailer($transport))->send($email);
    }

    private function emailSignature(User $user): ?array
    {
        if ($user->email_signature_enabled === false) {
            return null;
        }

        $name = trim((string) ($user->email_signature_name ?: trim($user->first_name . ' ' . $user->last_name)));
        $title = trim((string) ($user->email_signature_title ?: $user->role?->name));
        $contact = trim((string) ($user->email_signature_contact_number ?: $user->phone_number));
        $brand = $user->brand;
        $logoPath = $brand?->logo_path ?: $brand?->site_logo_path;
        $logoUrl = $logoPath ? asset('storage/' . $logoPath) : null;
        $html = $this->sanitizeSignatureHtml($user->email_signature_html)
            ?: $this->defaultSignatureHtml($name, $title, $contact, $brand?->imprint_name, $logoUrl);

        if ($name === '' && $title === '' && $contact === '' && ! $logoUrl && $html === '') {
            return null;
        }

        return [
            'name' => $name,
            'title' => $title,
            'contact' => $contact,
            'brand' => $brand?->imprint_name,
            'logoUrl' => $logoUrl,
            'html' => $html,
            'text' => trim(preg_replace('/\n{3,}/', "\n\n", html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))))),
        ];
    }

    private function signatureEditorHtml(User $user): string
    {
        $name = trim((string) ($user->email_signature_name ?: trim($user->first_name . ' ' . $user->last_name)));
        $title = trim((string) ($user->email_signature_title ?: $user->role?->name));
        $contact = trim((string) ($user->email_signature_contact_number ?: $user->phone_number));
        $brand = $user->brand;
        $logoPath = $brand?->logo_path ?: $brand?->site_logo_path;
        $logoUrl = $logoPath ? asset('storage/' . $logoPath) : null;

        return $this->sanitizeSignatureHtml($user->email_signature_html)
            ?: $this->defaultSignatureHtml($name, $title, $contact, $brand?->imprint_name, $logoUrl);
    }

    private function messageHtml(string $body, ?array $signature = null): string
    {
        $bodyWithoutSignature = $body;

        if ($signature) {
            $signaturePosition = strrpos($body, $signature['text']);
            if ($signaturePosition !== false) {
                $bodyWithoutSignature = rtrim(substr($body, 0, $signaturePosition));
            }
        }

        $html = '<div>' . nl2br(e($bodyWithoutSignature)) . '</div>';

        if (! $signature) {
            return $html;
        }

        $html .= '<div style="margin-top:28px; color:#111827; font-family:Arial, Helvetica, sans-serif;">' . $signature['html'] . '</div>';

        return $html;
    }

    private function defaultSignatureHtml(string $name, string $title, string $contact, ?string $brandName, ?string $logoUrl): string
    {
        $html = '<div>--</div>';

        if ($name !== '') {
            $html .= '<div style="margin-top:10px; font-size:18px; font-weight:700;">' . e($name) . '</div>';
        }

        if ($title !== '') {
            $html .= '<div style="margin-top:2px; color:#666; font-size:14px; font-weight:700;">' . e($title) . '</div>';
        }

        if ($logoUrl) {
            $html .= '<img src="' . e($logoUrl) . '" alt="' . e($brandName ?: 'Brand logo') . '" style="display:block; margin-top:28px; max-width:240px; max-height:120px;">';
        }

        if ($contact !== '') {
            $html .= '<div style="margin-top:24px; color:#666; font-size:14px; font-weight:700;">Contact Number: ' . e($contact) . '</div>';
        }

        return $html;
    }

    private function sanitizeSignatureHtml(?string $html): ?string
    {
        if (! $html) {
            return null;
        }

        $html = strip_tags($html, '<div><p><br><b><strong><i><em><u><span><a><img><ul><ol><li>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? '';

        return trim($html) ?: null;
    }

    private function syncInbox(EmailAccount $account): int
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

            foreach ($client->messages('INBOX', 50) as $message) {
                EmailMessage::updateOrCreate(
                    [
                        'email_account_id' => $account->id,
                        'folder' => 'INBOX',
                        'uid' => $message['uid'],
                    ],
                    [
                        'message_id' => $message['message_id'],
                        'subject' => $message['subject'],
                        'from_name' => $message['from_name'],
                        'from_email' => $message['from_email'],
                        'body_text' => $message['body_text'],
                        'body_html' => $message['body_html'],
                        'sent_at' => $this->parseMessageDate($message['sent_at']),
                        'is_seen' => $message['is_seen'],
                        'is_answered' => $message['is_answered'],
                        'has_attachments' => $message['has_attachments'],
                    ]
                );
                $synced++;
            }

            $account->update(['last_synced_at' => now()]);

            return $synced;
        } finally {
            $client->disconnect();
        }
    }

    private function parseAddressList(string $value): array
    {
        return collect(preg_split('/[,;\n]+/', $value) ?: [])
            ->map(fn (string $email) => trim($email))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
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
