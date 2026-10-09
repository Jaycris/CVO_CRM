<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\User;
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
            : ($messages instanceof \Illuminate\Contracts\Pagination\Paginator ? $messages->first() : null);

        $brands = $this->canManageEmailAccounts($user)
            ? Brand::orderBy('imprint_name')->get()
            : collect();
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
            'brands' => $brands,
            'users' => $users,
            'folder' => $folder,
            'imapAvailable' => function_exists('imap_open'),
            'messages' => $messages,
            'selectedMessage' => $selectedMessage,
            'canManageEmailAccounts' => $this->canManageEmailAccounts($user),
            'settingsAccount' => $settingsAccount,
        ]);
    }

    public function storeAccount(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canManageEmailAccounts($user), 403);

        $validated = $request->validate([
            'mailbox_type' => ['required', Rule::in(['brand', 'employee'])],
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
            'brand_id' => ['required_if:mailbox_type,brand', 'nullable', 'integer', Rule::exists('brands', 'id')],
            'user_id' => ['required_if:mailbox_type,employee', 'nullable', 'integer', Rule::exists('users', 'id')],
        ]);
        $assignedUser = $validated['mailbox_type'] === 'employee'
            ? User::findOrFail($validated['user_id'])
            : null;

        $account = new EmailAccount([
            'user_id' => $assignedUser?->id,
            'brand_id' => $validated['mailbox_type'] === 'brand' ? $validated['brand_id'] : $assignedUser?->brand_id,
            'display_name' => $validated['display_name'],
            'email_address' => mb_strtolower($validated['email_address']),
            'username' => $validated['username'],
            'imap_host' => $validated['imap_host'],
            'imap_port' => $validated['imap_port'],
            'imap_encryption' => $validated['imap_encryption'],
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'],
            'smtp_encryption' => $validated['smtp_encryption'],
            'is_shared' => $validated['mailbox_type'] === 'brand',
        ]);
        $account->setPlainPassword($validated['password']);
        $account->save();

        return redirect()
            ->route('email.index', ['account' => $account->id, 'settings' => 1])
            ->with('success', $account->is_shared
                ? 'Brand mailbox connected. Employees assigned to this brand can now use it from Email.'
                : 'Employee mailbox connected. The assigned employee will see it automatically after login.');
    }

    public function updateAccount(Request $request, EmailAccount $account)
    {
        abort_unless($this->canManageEmailAccounts($request->user()), 403);

        $validated = $request->validate([
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

        $account->fill([
            'display_name' => $validated['display_name'],
            'email_address' => mb_strtolower($validated['email_address']),
            'username' => $validated['username'],
            'imap_host' => $validated['imap_host'],
            'imap_port' => $validated['imap_port'],
            'imap_encryption' => $validated['imap_encryption'],
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'],
            'smtp_encryption' => $validated['smtp_encryption'],
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

        if (! function_exists('imap_open')) {
            return redirect()
                ->route('email.index', ['account' => $account->id])
                ->with('error', 'Inbox sync needs the PHP IMAP extension enabled on the server.');
        }

        try {
            $synced = $this->syncInbox($account);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('email.index', ['account' => $account->id])
                ->with('error', 'The mailbox could not be synced. Please check the IMAP settings and password.');
        }

        return redirect()
            ->route('email.index', ['account' => $account->id])
            ->with('success', "{$synced} inbox message(s) synced.");
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'email_account_id' => ['required', 'integer', Rule::exists('email_accounts', 'id')],
            'to' => ['required', 'string', 'max:1000'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
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
            $this->sendSmtpMessage($account, $to, $cc, $validated['subject'], $validated['body']);
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
            'body_text' => $validated['body'],
            'sent_at' => now(),
            'is_seen' => true,
        ]);

        return redirect()
            ->route('email.index', ['account' => $account->id, 'folder' => 'Sent'])
            ->with('success', 'Email sent successfully.');
    }

    private function accessibleAccounts(User $user)
    {
        return EmailAccount::query()
            ->when(! $this->canManageEmailAccounts($user), function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere(function ($query) use ($user) {
                            $query->where('is_shared', true)
                                ->whereNotNull('brand_id')
                                ->where('brand_id', $user->brand_id);
                        });
                });
            })
            ->when($this->canManageEmailAccounts($user), function ($query) {
                $query->where(function ($query) {
                    $query->where('is_shared', true)
                        ->whereNotNull('brand_id')
                        ->orWhereNotNull('user_id');
                });
            })
            ->with(['brand', 'user'])
            ->orderByDesc('is_shared')
            ->orderBy('display_name');
    }

    private function authorizeAccount(User $user, EmailAccount $account): void
    {
        abort_unless(
            $this->canManageEmailAccounts($user)
            || $account->user_id === $user->id
            || ($account->is_shared && $account->brand_id && $account->brand_id === $user->brand_id),
            403
        );
    }

    private function canManageEmailAccounts(User $user): bool
    {
        return $user->role?->name === 'Admin';
    }

    private function sendSmtpMessage(EmailAccount $account, array $to, array $cc, string $subject, string $body): void
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
            ->html(nl2br(e($body)));

        foreach ($to as $address) {
            $email->addTo($address);
        }

        foreach ($cc as $address) {
            $email->addCc($address);
        }

        (new Mailer($transport))->send($email);
    }

    private function syncInbox(EmailAccount $account): int
    {
        $mailbox = $this->mailboxString($account, 'INBOX');
        $imap = @imap_open($mailbox, $account->username, $account->plainPassword() ?? '', OP_READONLY);

        if (! $imap) {
            throw new \RuntimeException(imap_last_error() ?: 'Unable to open mailbox.');
        }

        try {
            $uids = imap_search($imap, 'ALL', SE_UID) ?: [];
            $uids = array_slice(array_reverse($uids), 0, 50);
            $synced = 0;

            foreach ($uids as $uid) {
                $overview = imap_fetch_overview($imap, (string) $uid, FT_UID)[0] ?? null;

                if (! $overview) {
                    continue;
                }

                EmailMessage::updateOrCreate(
                    [
                        'email_account_id' => $account->id,
                        'folder' => 'INBOX',
                        'uid' => (int) $uid,
                    ],
                    [
                        'message_id' => $overview->message_id ?? null,
                        'subject' => $this->decodeHeader($overview->subject ?? '(No subject)'),
                        'from_name' => $this->decodeHeader($overview->from ?? null),
                        'from_email' => $this->extractEmail($overview->from ?? null),
                        'body_text' => trim((string) imap_fetchbody($imap, (string) $uid, '1', FT_UID | FT_PEEK)),
                        'sent_at' => isset($overview->date) ? Carbon::parse($overview->date) : null,
                        'is_seen' => ! empty($overview->seen),
                        'is_answered' => ! empty($overview->answered),
                        'has_attachments' => false,
                    ]
                );
                $synced++;
            }

            $account->update(['last_synced_at' => now()]);

            return $synced;
        } finally {
            imap_close($imap);
        }
    }

    private function mailboxString(EmailAccount $account, string $folder): string
    {
        $flags = ['/imap'];

        if ($account->imap_encryption === 'ssl') {
            $flags[] = '/ssl';
        } elseif ($account->imap_encryption === 'tls') {
            $flags[] = '/tls';
        } else {
            $flags[] = '/notls';
        }

        return sprintf('{%s:%d%s}%s', $account->imap_host, $account->imap_port, implode('', $flags), $folder);
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

    private function decodeHeader(?string $value): ?string
    {
        if ($value === null || ! function_exists('imap_mime_header_decode')) {
            return $value;
        }

        return collect(imap_mime_header_decode($value))
            ->map(fn ($part) => $part->text ?? '')
            ->implode('');
    }

    private function extractEmail(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);

        return $matches[0] ?? null;
    }
}
