<?php

namespace App\Http\Controllers;

use App\Mail\ContractSignatureCcNotificationMail;
use App\Mail\ContractSignatureRequestMail;
use App\Models\EmailAccount;
use App\Models\SalesEndorsement;
use App\Models\User;
use App\Notifications\ContractSignedNotification;
use App\Notifications\ContractSignatureRequestSentNotification;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ContractSignatureController extends Controller
{
    public function overview(Request $request, SalesEndorsement $endorsement): View
    {
        abort_unless($this->userHasPermission($request, 'view_contract_records'), 403);
        abort_unless($this->userCanAccessBrand($request, $endorsement->brand_id), 403);

        $endorsement->loadMissing(['agent.team.manager', 'agent.team.teamLeader', 'brand', 'contractSender', 'documents.uploader']);

        return view('finance.contract-esign', [
            'endorsement' => $endorsement,
            'packet' => $this->packet($endorsement),
            'signUrl' => URL::temporarySignedRoute('contracts.sign.show', now()->addDays(30), ['endorsement' => $endorsement]),
            'downloadUrl' => route('finance.contracts.esign.download', $endorsement),
            'previewUrl' => route('finance.contracts.esign.preview', $endorsement),
            'sendUrl' => route('finance.contracts.esign.send', $endorsement),
            'fieldsUrl' => route('finance.contracts.esign.fields', $endorsement),
            'editorUrl' => route('finance.contracts.esign.editor', $endorsement),
            'canManageContracts' => $this->userHasPermission($request, 'manage_contract_records'),
        ]);
    }

    public function editor(Request $request, SalesEndorsement $endorsement): View
    {
        abort_unless($this->userHasPermission($request, 'manage_contract_records'), 403);
        abort_unless($this->userCanAccessBrand($request, $endorsement->brand_id), 403);

        $endorsement->loadMissing(['agent.team.manager', 'agent.team.teamLeader', 'brand', 'contractSender', 'documents.uploader']);

        return view('finance.contract-esign-editor', [
            'endorsement' => $endorsement,
            'packet' => $this->packet($endorsement),
            'packetUrl' => route('finance.contracts.esign', $endorsement),
            'previewUrl' => route('finance.contracts.esign.preview', $endorsement),
            'fieldsUrl' => route('finance.contracts.esign.fields', $endorsement),
        ]);
    }

    public function send(Request $request, SalesEndorsement $endorsement): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'manage_contract_records'), 403);
        abort_unless($this->userCanAccessBrand($request, $endorsement->brand_id), 403);

        $endorsement->loadMissing(['agent.team.manager', 'agent.team.teamLeader', 'brand']);

        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
            'cc_emails' => ['nullable', 'string', 'max:2000'],
        ]);

        $ccEmails = $this->parseEmailList($validated['cc_emails'] ?? '')
            ->reject(fn (string $email) => strcasecmp($email, $validated['recipient_email']) === 0)
            ->values()
            ->all();
        $packet = $this->packet($endorsement, $request->user());
        $mailAccount = $this->contractMailAccount($endorsement);
        $packet = $this->withContractMailAccount($packet, $mailAccount);
        $signUrl = URL::temporarySignedRoute('contracts.sign.show', now()->addDays(30), ['endorsement' => $endorsement]);

        $this->sendContractMail(
            $mailAccount,
            $validated['recipient_email'],
            new ContractSignatureRequestMail($endorsement, $signUrl, $packet)
        );

        $endorsement->forceFill([
            'contract_status' => $endorsement->contract_status === 'signed' ? 'signed' : 'sent',
            'contract_sent_at' => $endorsement->contract_sent_at ?? now(),
            'contract_recipient_email' => $validated['recipient_email'],
            'contract_cc_emails' => $ccEmails,
            'contract_sent_by' => $request->user()?->id,
        ])->save();

        $this->sendContractCcMail(
            $mailAccount,
            $endorsement,
            $ccEmails,
            $packet,
            $request->user()
        );
        $this->notifyCcUsers($endorsement->fresh(['agent.team.manager', 'agent.team.teamLeader']), $ccEmails);

        return redirect()
            ->route('finance.contracts.esign', $endorsement)
            ->with('success', 'Contract has been sent to ' . $validated['recipient_email'] . '.');
    }

    public function updateFields(Request $request, SalesEndorsement $endorsement): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'manage_contract_records'), 403);
        abort_unless($this->userCanAccessBrand($request, $endorsement->brand_id), 403);

        $validated = $request->validate([
            'fields' => ['nullable', 'json'],
        ]);

        $fields = collect(json_decode($validated['fields'] ?? '[]', true) ?: [])
            ->filter(fn ($field) => is_array($field))
            ->map(function (array $field) {
                $type = in_array($field['type'] ?? '', ['signature', 'initials', 'date', 'text', 'checkbox'], true)
                    ? $field['type']
                    : 'text';

                return [
                    'id' => (string) ($field['id'] ?? Str::uuid()),
                    'type' => $type,
                    'label' => Str::limit((string) ($field['label'] ?? Str::headline($type)), 80, ''),
                    'page' => max(1, (int) ($field['page'] ?? 1)),
                    'x' => max(0, min(100, (float) ($field['x'] ?? 10))),
                    'y' => max(0, min(100, (float) ($field['y'] ?? 10))),
                    'w' => max(6, min(100, (float) ($field['w'] ?? 22))),
                    'h' => max(4, min(60, (float) ($field['h'] ?? 7))),
                    'fontSize' => max(8, min(48, (float) ($field['fontSize'] ?? 14))),
                    'required' => (bool) ($field['required'] ?? true),
                ];
            })
            ->values()
            ->all();

        $endorsement->forceFill([
            'contract_esign_fields' => $fields,
            'contract_status' => $endorsement->contract_status ?: 'sent',
            'contract_sent_at' => $endorsement->contract_sent_at ?? now(),
        ])->save();

        return redirect()
            ->route('finance.contracts.esign', $endorsement)
            ->with('success', 'Fields saved successfully.');
    }

    public function show(Request $request, SalesEndorsement $endorsement): View
    {
        $endorsement->loadMissing(['agent', 'brand', 'contractSender', 'documents.uploader']);

        return view('contracts.sign', [
            'endorsement' => $endorsement,
            'packet' => $this->packet($endorsement),
            'submitUrl' => URL::temporarySignedRoute('contracts.sign.submit', now()->addDay(), ['endorsement' => $endorsement]),
            'downloadUrl' => URL::temporarySignedRoute('contracts.sign.download', now()->addDay(), ['endorsement' => $endorsement]),
            'previewUrl' => URL::temporarySignedRoute('contracts.sign.preview', now()->addDay(), ['endorsement' => $endorsement]),
        ]);
    }

    public function submit(Request $request, SalesEndorsement $endorsement): RedirectResponse
    {
        if ($endorsement->contract_status === 'signed') {
            return redirect()
                ->to(URL::temporarySignedRoute('contracts.sign.show', now()->addDay(), ['endorsement' => $endorsement]))
                ->with('success', 'This contract is already signed.');
        }

        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_email' => ['nullable', 'email', 'max:255'],
            'signature_text' => ['required', 'string', 'max:255'],
            'field_values' => ['nullable', 'array'],
            'field_values.*' => ['nullable', 'string', 'max:1000'],
            'accepted_terms' => ['accepted'],
        ]);

        $fieldValues = collect($validated['field_values'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_string($value) ? trim($value) : $value])
            ->all();

        $endorsement->forceFill([
            'contract_status' => 'signed',
            'contract_sent_at' => $endorsement->contract_sent_at ?? now(),
            'contract_signed_at' => now(),
            'contract_signer_name' => $validated['signer_name'],
            'contract_signer_email' => $validated['signer_email'] ?: $endorsement->email,
            'contract_signature_text' => $validated['signature_text'],
            'contract_signer_ip' => $request->ip(),
            'contract_signer_user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'contract_esign_field_values' => $fieldValues,
        ])->save();

        $this->notifyContractSigned($endorsement->fresh(['agent.team.manager', 'agent.team.teamLeader', 'contractSender']));

        return redirect()
            ->to(URL::temporarySignedRoute('contracts.sign.show', now()->addDay(), ['endorsement' => $endorsement]))
            ->with('success', 'Contract signed successfully.');
    }

    public function preview(Request $request, SalesEndorsement $endorsement): Response
    {
        return $this->streamContract($endorsement, true);
    }

    public function download(Request $request, SalesEndorsement $endorsement): Response
    {
        return $this->streamContract($endorsement, false);
    }

    public function publicPreview(Request $request, SalesEndorsement $endorsement): Response
    {
        return $this->streamContract($endorsement, true);
    }

    public function publicDownload(Request $request, SalesEndorsement $endorsement): Response
    {
        return $this->streamContract($endorsement, false);
    }

    private function packet(SalesEndorsement $endorsement, ?User $currentSender = null): array
    {
        $brand = $endorsement->brand;
        $senderName = $brand?->imprint_name ?? 'CreatiVision Outsourcing';
        $crmName = $brand?->crm_display_name ?: 'VisionFlow CRM';
        $brandLogoPath = $brand?->logo_path ?: $brand?->site_logo_path;
        $brandLogoUrl = $brandLogoPath
            ? asset('storage/' . $brandLogoPath) . ($brand?->updated_at ? '?v=' . $brand->updated_at->timestamp : '')
            : match ($senderName) {
                'Inkspire Media House' => asset('images/inkspire-logo.png') . '?v=' . @filemtime(public_path('images/inkspire-logo.png')),
                'CreatiVision Outsourcing' => asset('images/CreativeVision LOGO - v2-01.png') . '?v=' . @filemtime(public_path('images/CreativeVision LOGO - v2-01.png')),
                default => null,
            };
        $brandSiteIconUrl = $brand?->site_logo_path
            ? asset('storage/' . $brand->site_logo_path) . ($brand?->updated_at ? '?v=' . $brand->updated_at->timestamp : '')
            : match ($senderName) {
                'Inkspire Media House' => asset('images/inkspire-logo-navsite.png') . '?v=' . @filemtime(public_path('images/inkspire-logo-navsite.png')),
                default => asset('images/CreativeVision LOGO-navsite.png') . '?v=' . @filemtime(public_path('images/CreativeVision LOGO-navsite.png')),
            };
        $senderEmail = $currentSender?->email ?: $endorsement->contractSender?->email;
        $authorEmail = $endorsement->email ?: null;
        $agentName = trim(($endorsement->agent?->first_name ?? '') . ' ' . ($endorsement->agent?->last_name ?? ''));
        $agentEmail = $endorsement->agent?->email;
        $signedAt = $endorsement->contract_signed_at;
        $sentAt = $endorsement->contract_sent_at;

        return [
            'title' => $endorsement->contract_file_name ?: 'Service Contract for ' . $senderName . ' - ' . $endorsement->author_name,
            'documentId' => $endorsement->endorsement_code ?: 'SE-' . $endorsement->id,
            'status' => $endorsement->contract_status === 'signed' ? 'Signed' : ($endorsement->contract_status === 'sent' ? 'Sent' : 'Draft'),
            'senderName' => $senderName,
            'senderEmail' => $senderEmail,
            'brandName' => $senderName,
            'brandLogoUrl' => $brandLogoUrl,
            'brandSiteIconUrl' => $brandSiteIconUrl,
            'brandPrimaryColor' => $brand?->primary_color ?: '#047857',
            'crmName' => $crmName,
            'displayTimezone' => 'America/New_York',
            'sentAt' => $sentAt,
            'lastActivityAt' => $signedAt ?: $sentAt ?: $endorsement->updated_at,
            'signedAt' => $signedAt,
            'signerName' => $endorsement->contract_signer_name ?: $endorsement->author_name,
            'signerEmail' => $endorsement->contract_signer_email ?: $authorEmail,
            'recipientEmail' => $endorsement->contract_recipient_email ?: $authorEmail,
            'ccEmails' => $this->defaultCcEmails($endorsement)->merge($endorsement->contract_cc_emails ?: [])->unique(fn ($email) => strtolower($email))->values()->all(),
            'signatureText' => $endorsement->contract_signature_text ?: $endorsement->author_name,
            'signerIp' => $endorsement->contract_signer_ip,
            'signerUserAgent' => $endorsement->contract_signer_user_agent,
            'fields' => $endorsement->contract_esign_fields ?: [],
            'fieldValues' => $endorsement->contract_esign_field_values ?: [],
            'agentName' => $agentName ?: 'Sales Agent',
            'agentEmail' => $agentEmail,
            'hasContractFile' => (bool) ($endorsement->contract_file_path && Storage::disk('local')->exists($endorsement->contract_file_path)),
            'previewKind' => $this->previewKind($endorsement),
            'pageCount' => 1,
        ];
    }

    private function previewKind(SalesEndorsement $endorsement): string
    {
        $extension = strtolower(pathinfo((string) $endorsement->contract_file_name, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'pdf',
            'jpg', 'jpeg', 'png' => 'image',
            default => 'download',
        };
    }

    private function contractMailAccount(SalesEndorsement $endorsement): ?EmailAccount
    {
        return EmailAccount::query()
            ->where('brand_id', $endorsement->brand_id)
            ->where('is_shared', true)
            ->orderBy('id')
            ->first();
    }

    private function withContractMailAccount(array $packet, ?EmailAccount $account): array
    {
        if (! $account) {
            return $packet;
        }

        $displayName = trim((string) $account->display_name);

        return array_merge($packet, [
            'senderName' => $displayName !== '' ? $displayName : $packet['senderName'],
            'senderEmail' => $account->email_address,
            'brandName' => $displayName !== '' ? $displayName : $packet['brandName'],
        ]);
    }

    private function sendContractMail(?EmailAccount $account, string $to, Mailable $mailable): void
    {
        if (! $account) {
            Mail::to($to)->send($mailable);

            return;
        }

        $mailer = 'contract_brand_' . $account->id;

        config([
            'mail.mailers.' . $mailer => [
                'transport' => 'smtp',
                'scheme' => $account->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
                'host' => $account->smtp_host,
                'port' => $account->smtp_port,
                'username' => $account->username,
                'password' => $account->plainPassword() ?? '',
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ],
        ]);

        Mail::purge($mailer);
        Mail::mailer($mailer)->to($to)->send($mailable);
    }

    private function sendContractCcMail(
        ?EmailAccount $account,
        SalesEndorsement $endorsement,
        array $ccEmails,
        array $packet,
        ?User $sender
    ): void {
        if ($ccEmails === []) {
            return;
        }

        $users = User::query()
            ->whereIn('email', collect($ccEmails)->map(fn ($email) => strtolower($email))->all())
            ->get()
            ->keyBy(fn (User $user) => strtolower($user->email));

        $senderName = $this->userDisplayName($sender) ?: ($packet['senderName'] ?? 'The sender');
        $recipientName = $packet['signerName'] ?? $endorsement->author_name ?? $packet['recipientEmail'];

        foreach ($ccEmails as $email) {
            $user = $users->get(strtolower($email));
            $ccName = $this->userDisplayName($user) ?: $this->nameFromEmail($email);

            $this->sendContractMail(
                $account,
                $email,
                new ContractSignatureCcNotificationMail(
                    $endorsement,
                    $packet,
                    $ccName,
                    $senderName,
                    $recipientName
                )
            );
        }
    }

    private function userDisplayName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : $user->email;
    }

    private function nameFromEmail(string $email): string
    {
        $name = trim(strstr($email, '@', true) ?: $email);

        return $name !== '' ? $name : $email;
    }

    private function defaultCcEmails(SalesEndorsement $endorsement)
    {
        return collect([
            $endorsement->agent?->email,
            $endorsement->agent?->team?->manager?->email,
            $endorsement->agent?->team?->teamLeader?->email,
            $endorsement->agent?->reportsToUser?->email,
        ])
            ->merge(User::query()
                ->whereHas('role', fn ($query) => $query->where('name', 'Admin'))
                ->whereNull('suspended_at')
                ->pluck('email'))
            ->filter()
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique(fn ($email) => strtolower($email))
            ->values();
    }

    private function notifyCcUsers(SalesEndorsement $endorsement, array $ccEmails): void
    {
        if ($ccEmails === []) {
            return;
        }

        $normalizedEmails = collect($ccEmails)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->all();

        User::query()
            ->whereNull('suspended_at')
            ->whereIn('email', $normalizedEmails)
            ->get()
            ->each(fn (User $user) => $user->notify(new ContractSignatureRequestSentNotification($endorsement)));
    }

    private function notifyContractSigned(SalesEndorsement $endorsement): void
    {
        $emails = $this->defaultCcEmails($endorsement)
            ->merge($endorsement->contract_cc_emails ?: [])
            ->push($endorsement->contractSender?->email)
            ->filter()
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            return;
        }

        User::query()
            ->whereNull('suspended_at')
            ->whereIn('email', $emails)
            ->get()
            ->each(fn (User $user) => $user->notify(new ContractSignedNotification($endorsement)));
    }

    private function parseEmailList(string $value)
    {
        return str($value)
            ->replace(["\r\n", "\n", ';'], ',')
            ->explode(',')
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique(fn ($email) => strtolower($email))
            ->values();
    }

    private function streamContract(SalesEndorsement $endorsement, bool $inline): Response
    {
        abort_unless($endorsement->contract_file_path && Storage::disk('local')->exists($endorsement->contract_file_path), 404);

        $fileName = $endorsement->contract_file_name ?: "contract-{$endorsement->endorsement_code}.pdf";

        if ($endorsement->contract_status === 'signed' && $this->previewKind($endorsement) === 'pdf') {
            $endorsement->loadMissing(['agent.team.manager', 'agent.team.teamLeader', 'brand', 'contractSender']);

            $signedFileName = Str::beforeLast($fileName, '.') ?: $fileName;
            $signedFileName = Str::of($signedFileName)->replaceMatches('/(?:-signed)+$/i', '')->toString() . '-signed.pdf';

            return response($this->signedContractPdf($endorsement), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $signedFileName) . '"',
            ]);
        }

        if (! $inline) {
            return Storage::disk('local')->download($endorsement->contract_file_path, $fileName);
        }

        return Storage::disk('local')->response($endorsement->contract_file_path, $fileName, [
            'Content-Disposition' => 'inline; filename="' . str_replace('"', '', $fileName) . '"',
        ]);
    }

    private function signedContractPdf(SalesEndorsement $endorsement): string
    {
        $pdf = Storage::disk('local')->get($endorsement->contract_file_path);

        try {
            return $this->appendSignatureCertificatePage($pdf, $endorsement);
        } catch (\Throwable $exception) {
            report($exception);

            return $pdf;
        }
    }

    private function appendSignatureCertificatePage(string $pdf, SalesEndorsement $endorsement): string
    {
        abort_unless(str_starts_with($pdf, '%PDF-'), 422, 'The contract file is not a valid PDF.');

        if ($this->hasSignatureCertificatePage($pdf)) {
            return $pdf;
        }

        preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/s', $pdf, $startXrefMatch);
        preg_match_all('/trailer\s*<<(.*?)>>/s', $pdf, $trailerMatches);
        abort_unless($startXrefMatch && $trailerMatches[1] !== [], 422, 'The contract PDF could not be prepared.');

        $trailer = end($trailerMatches[1]);
        preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $trailer, $rootMatch);
        preg_match('/\/Size\s+(\d+)/', $trailer, $sizeMatch);
        abort_unless($rootMatch && $sizeMatch, 422, 'The contract PDF structure could not be prepared.');

        $objects = $this->pdfObjects($pdf);
        $rootRef = (int) $rootMatch[1];
        $root = $objects[$rootRef] ?? null;
        abort_unless($root && preg_match('/\/Pages\s+(\d+)\s+\d+\s+R/', $root, $pagesMatch), 422, 'The contract PDF pages could not be prepared.');

        $pagesRef = (int) $pagesMatch[1];
        $pages = $objects[$pagesRef] ?? null;
        abort_unless($pages && preg_match('/\/Kids\s*\[(.*?)\]/s', $pages, $kidsMatch), 422, 'The contract PDF pages could not be prepared.');
        preg_match('/\/Count\s+(\d+)/', $pages, $countMatch);

        $oldPageCount = max(1, (int) ($countMatch[1] ?? 1));
        $pageRefs = $this->pdfPageRefs($pagesRef, $objects);
        $nextObject = (int) $sizeMatch[1];
        $fontRegularRef = $nextObject++;
        $fontBoldRef = $nextObject++;
        $fontScriptRef = $nextObject++;
        $fieldOverlayObjects = $this->signatureFieldPdfObjects($endorsement, $objects, $pageRefs, $fontRegularRef, $fontBoldRef, $fontScriptRef, $nextObject);
        $nextObject = $fieldOverlayObjects['nextObject'];
        $certificateSignatureRef = null;
        $certificateSignatureImage = $this->signatureImagePdfObject($endorsement->contract_signature_text ?: $endorsement->contract_signer_name ?: $endorsement->author_name ?: '', 240, 78);

        if ($certificateSignatureImage) {
            $certificateSignatureRef = $nextObject++;
        }

        $contentRef = $nextObject++;
        $pageRef = $nextObject++;
        $pageCount = $oldPageCount + 1;

        $updatedPages = preg_replace(
            '/\/Kids\s*\[(.*?)\]/s',
            '/Kids [' . trim($kidsMatch[1]) . ' ' . $pageRef . ' 0 R]',
            $pages,
            1
        );
        $updatedPages = preg_replace('/\/Count\s+\d+/', '/Count ' . $pageCount, $updatedPages, 1);

        $content = $this->signatureCertificatePdfContent($endorsement, $pageCount, $certificateSignatureRef);
        $newObjects = [
            $pagesRef => $updatedPages,
            $fontRegularRef => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            $fontBoldRef => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            $fontScriptRef => '<< /Type /Font /Subtype /Type1 /BaseFont /ZapfChancery-MediumItalic >>',
        ] + $fieldOverlayObjects['objects'];

        if ($certificateSignatureRef && $certificateSignatureImage) {
            $newObjects[$certificateSignatureRef] = $certificateSignatureImage;
        }

        $certificateResources = '/Font << /F1 ' . $fontRegularRef . ' 0 R /F2 ' . $fontBoldRef . ' 0 R /F3 ' . $fontScriptRef . ' 0 R >>';

        if ($certificateSignatureRef) {
            $certificateResources .= ' /XObject << /CERTSIG ' . $certificateSignatureRef . ' 0 R >>';
        }

        $newObjects += [
            $contentRef => $this->pdfStreamObject($content),
            $pageRef => '<< /Type /Page /Parent ' . $pagesRef . ' 0 R /MediaBox [0 0 612 792] /Resources << ' . $certificateResources . ' >> /Contents ' . $contentRef . ' 0 R >>',
        ];

        $append = "\n";
        $offsets = [];

        foreach ($newObjects as $objectRef => $body) {
            $offsets[$objectRef] = strlen($pdf) + strlen($append);
            $append .= $objectRef . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf) + strlen($append);
        $append .= $this->pdfXref($offsets);
        $append .= "trailer\n<< /Size " . $nextObject . " /Root " . $rootRef . " 0 R /Prev " . (int) $startXrefMatch[1] . " >>\n";
        $append .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf . $append;
    }

    private function hasSignatureCertificatePage(string $pdf): bool
    {
        return str_contains($pdf, '(Signature Certificate)')
            && str_contains($pdf, '(Document completed by all parties on ')
            && str_contains($pdf, '(Sender information)');
    }

    private function pdfObjects(string $pdf): array
    {
        preg_match_all('/(\d+)\s+\d+\s+obj\b/', $pdf, $matches, PREG_OFFSET_CAPTURE);

        $objects = [];

        foreach ($matches[1] as $index => $match) {
            $ref = (int) $match[0];
            $objectStart = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $objectEnd = strpos($pdf, 'endobj', $objectStart);

            if ($objectEnd !== false) {
                $objects[$ref] = trim(substr($pdf, $objectStart, $objectEnd - $objectStart));
            }
        }

        return $objects;
    }

    private function pdfPageRefs(int $pageTreeRef, array $objects): array
    {
        $object = $objects[$pageTreeRef] ?? '';

        if (preg_match('/\/Type\s*\/Page\b/', $object) && ! preg_match('/\/Type\s*\/Pages\b/', $object)) {
            return [$pageTreeRef];
        }

        if (! preg_match('/\/Kids\s*\[(.*?)\]/s', $object, $kidsMatch)) {
            return [];
        }

        preg_match_all('/(\d+)\s+\d+\s+R/', $kidsMatch[1], $kids);

        return collect($kids[1])
            ->flatMap(fn ($ref) => $this->pdfPageRefs((int) $ref, $objects))
            ->values()
            ->all();
    }

    private function signatureFieldPdfObjects(
        SalesEndorsement $endorsement,
        array $objects,
        array $pageRefs,
        int $fontRegularRef,
        int $fontBoldRef,
        int $fontScriptRef,
        int $nextObject
    ): array {
        $fields = collect($endorsement->contract_esign_fields ?: [])
            ->filter(fn ($field) => is_array($field))
            ->groupBy(fn ($field) => max(1, (int) ($field['page'] ?? 1)));
        $values = $endorsement->contract_esign_field_values ?: [];
        $newObjects = [];

        foreach ($pageRefs as $index => $pageRef) {
            $pageNumber = $index + 1;
            $pageFields = $fields->get($pageNumber, collect());

            if ($pageFields->isEmpty()) {
                continue;
            }

            $page = $objects[$pageRef] ?? null;

            if (! $page) {
                continue;
            }

            [$pageWidth, $pageHeight] = $this->pdfPageSize($page, $objects);
            $overlay = $this->signatureFieldPdfContent($pageFields, $values, $endorsement, $pageWidth, $pageHeight, $nextObject);
            $nextObject = $overlay['nextObject'];

            if ($overlay['content'] === '') {
                continue;
            }

            $overlayRef = $nextObject++;
            $newObjects += $overlay['objects'];
            $newObjects[$overlayRef] = $this->pdfStreamObject($overlay['content']);
            $resourceUpdate = $this->addPdfPageResources($page, $objects, $fontRegularRef, $fontBoldRef, $fontScriptRef, $overlay['xobjects']);
            $newObjects += $resourceUpdate['objects'];
            $newObjects[$pageRef] = $this->appendPdfPageContent($resourceUpdate['page'], $overlayRef);
        }

        return [
            'objects' => $newObjects,
            'nextObject' => $nextObject,
        ];
    }

    private function signatureFieldPdfContent($fields, array $values, SalesEndorsement $endorsement, float $pageWidth, float $pageHeight, int $nextObject): array
    {
        $commands = ['q', '0 0 0 rg'];
        $objects = [];
        $xobjects = [];
        $signedDate = $endorsement->contract_signed_at?->copy()->timezone('America/New_York')->format('m/d/Y') ?: now('America/New_York')->format('m/d/Y');
        $signatureIndex = 1;

        foreach ($fields as $field) {
            $type = (string) ($field['type'] ?? 'text');
            $value = trim((string) ($values[$field['id'] ?? ''] ?? ''));

            if ($type === 'date' && $value === '') {
                $value = $signedDate;
            }

            if ($type === 'signature' && $value === '') {
                $value = $endorsement->contract_signature_text ?: $endorsement->contract_signer_name ?: $endorsement->author_name ?: '';
            }

            if ($value === '' && $type !== 'checkbox') {
                continue;
            }

            $x = ((float) ($field['x'] ?? 0) / 100) * $pageWidth;
            $fieldWidth = ((float) ($field['w'] ?? 20) / 100) * $pageWidth;
            $fieldHeight = ((float) ($field['h'] ?? 6) / 100) * $pageHeight;
            $y = $pageHeight - (((float) ($field['y'] ?? 0) / 100) * $pageHeight) - $fieldHeight;
            $fontSize = max(7, min(48, (float) ($field['fontSize'] ?? 14)));

            if ($type === 'signature' || $type === 'initials') {
                $signatureSize = max(10, min($fontSize, floor(($fieldWidth * 1.4) / max(1, strlen($value)))));

                $signatureImage = $this->signatureImagePdfObject($value, max(90, (int) $fieldWidth - 10), max(24, (int) ($fieldHeight * 0.7)));

                if ($signatureImage) {
                    $signatureObjectRef = $nextObject++;
                    $signatureName = 'SIG' . $signatureIndex++;
                    $objects[$signatureObjectRef] = $signatureImage;
                    $xobjects[$signatureName] = $signatureObjectRef;
                    $commands[] = 'q ' . max(20, (int) ($fieldWidth - 10)) . ' 0 0 ' . max(12, (int) ($fieldHeight - 14)) . ' ' . (int) ($x + 5) . ' ' . (int) ($y + 8) . ' cm /' . $signatureName . ' Do Q';
                } else {
                    $commands[] = $this->pdfText($value, (int) ($x + 8), (int) ($y + max(12, ($fieldHeight / 2) - 2)), (int) $signatureSize, 'ESS');
                }

                $commands[] = $this->pdfText('E-Signed by:', (int) ($x + 3), (int) ($y + $fieldHeight - 12), 5, 'ESR');
                $commands[] = $this->pdfText($signedDate, (int) ($x + 3), (int) ($y + 4), 5, 'ESR');

                continue;
            }

            $displayValue = $type === 'checkbox' ? ($value ? 'Checked' : 'Unchecked') : $value;
            $commands[] = $this->pdfText($displayValue, (int) ($x + 4), (int) ($y + max(7, ($fieldHeight / 2) - 3)), (int) min($fontSize, 12), $type === 'date' ? 'ESB' : 'ESR');
        }

        $commands[] = 'Q';

        return [
            'content' => count($commands) > 3 ? implode("\n", $commands) : '',
            'objects' => $objects,
            'xobjects' => $xobjects,
            'nextObject' => $nextObject,
        ];
    }

    private function pdfPageSize(string $page, array $objects): array
    {
        $boxSource = $page;

        if (! preg_match('/\/MediaBox\s*\[\s*[-\d.]+\s+[-\d.]+\s+([-\d.]+)\s+([-\d.]+)\s*\]/', $boxSource, $boxMatch)
            && preg_match('/\/Parent\s+(\d+)\s+\d+\s+R/', $page, $parentMatch)) {
            $boxSource = $objects[(int) $parentMatch[1]] ?? '';
            preg_match('/\/MediaBox\s*\[\s*[-\d.]+\s+[-\d.]+\s+([-\d.]+)\s+([-\d.]+)\s*\]/', $boxSource, $boxMatch);
        }

        return [
            max(1, (float) ($boxMatch[1] ?? 612)),
            max(1, (float) ($boxMatch[2] ?? 792)),
        ];
    }

    private function appendPdfPageContent(string $page, int $contentRef): string
    {
        if (preg_match('/\/Contents\s*\[(.*?)\]/s', $page, $contentsMatch)) {
            return preg_replace(
                '/\/Contents\s*\[(.*?)\]/s',
                '/Contents [' . trim($contentsMatch[1]) . ' ' . $contentRef . ' 0 R]',
                $page,
                1
            );
        }

        if (preg_match('/\/Contents\s+(\d+\s+\d+\s+R)/', $page, $contentsMatch)) {
            return preg_replace(
                '/\/Contents\s+\d+\s+\d+\s+R/',
                '/Contents [' . $contentsMatch[1] . ' ' . $contentRef . ' 0 R]',
                $page,
                1
            );
        }

        return preg_replace('/>>\s*$/', '/Contents ' . $contentRef . ' 0 R >>', $page, 1);
    }

    private function addPdfPageResources(string $page, array $objects, int $fontRegularRef, int $fontBoldRef, int $fontScriptRef, array $xobjects = []): array
    {
        $fontResources = '/ESR ' . $fontRegularRef . ' 0 R /ESB ' . $fontBoldRef . ' 0 R /ESS ' . $fontScriptRef . ' 0 R';
        $newObjects = [];

        if (! $this->pdfDictionaryBounds($page, '/Resources')
            && preg_match('/\/Resources\s+(\d+)\s+\d+\s+R/', $page, $resourceMatch)) {
            $resourceRef = (int) $resourceMatch[1];
            $resources = $objects[$resourceRef] ?? '<< >>';
            $resources = $this->addPdfFontResources($resources, $fontResources, $objects, $newObjects);
            $resources = $this->addPdfXObjectResources($resources, $xobjects, $objects, $newObjects);
            $newObjects[$resourceRef] = $resources;

            return [
                'page' => $page,
                'objects' => $newObjects,
            ];
        }

        $resourceBounds = $this->pdfDictionaryBounds($page, '/Resources');

        if (! $resourceBounds) {
            $inheritedResources = $this->inheritedPdfPageResources($page, $objects);

            if ($inheritedResources) {
                [$resources, $resourceRef] = $inheritedResources;
                $resources = $this->addPdfFontResources($resources, $fontResources, $objects, $newObjects);
                $resources = $this->addPdfXObjectResources($resources, $xobjects, $objects, $newObjects);

                if ($resourceRef) {
                    $newObjects[$resourceRef] = $resources;

                    return [
                        'page' => $page,
                        'objects' => $newObjects,
                    ];
                }

                return [
                    'page' => preg_replace('/>>\s*$/', '/Resources ' . $resources . ' >>', $page, 1),
                    'objects' => $newObjects,
                ];
            }

            $xobjectResources = $this->pdfXObjectResources($xobjects);

            return [
                'page' => preg_replace(
                '/>>\s*$/',
                '/Resources << /Font << ' . $fontResources . ' >>' . $xobjectResources . ' >> >>',
                $page,
                1
                ),
                'objects' => [],
            ];
        }

        [$resourceStart, $resourceEnd] = $resourceBounds;
        $resources = substr($page, $resourceStart, $resourceEnd - $resourceStart);
        $resources = $this->mergeInheritedPdfPageResources($resources, $page, $objects);
        $resources = $this->addPdfFontResources($resources, $fontResources, $objects, $newObjects);
        $resources = $this->addPdfXObjectResources($resources, $xobjects, $objects, $newObjects);

        return [
            'page' => substr($page, 0, $resourceStart) . $resources . substr($page, $resourceEnd),
            'objects' => $newObjects,
        ];
    }

    private function inheritedPdfPageResources(string $page, array $objects): ?array
    {
        $current = $page;
        $visited = [];

        while (preg_match('/\/Parent\s+(\d+)\s+\d+\s+R/', $current, $parentMatch)) {
            $parentRef = (int) $parentMatch[1];

            if (isset($visited[$parentRef])) {
                return null;
            }

            $visited[$parentRef] = true;
            $parent = $objects[$parentRef] ?? null;

            if (! $parent) {
                return null;
            }

            if (! $this->pdfDictionaryBounds($parent, '/Resources')
                && preg_match('/\/Resources\s+(\d+)\s+\d+\s+R/', $parent, $resourceMatch)) {
                $resourceRef = (int) $resourceMatch[1];
                $resourceObject = $objects[$resourceRef] ?? null;

                if ($resourceObject && str_starts_with(trim($resourceObject), '<<')) {
                    return [$resourceObject, $resourceRef];
                }
            }

            $resourceBounds = $this->pdfDictionaryBounds($parent, '/Resources');

            if ($resourceBounds) {
                [$resourceStart, $resourceEnd] = $resourceBounds;

                return [substr($parent, $resourceStart, $resourceEnd - $resourceStart), null];
            }

            $current = $parent;
        }

        return null;
    }

    private function mergeInheritedPdfPageResources(string $resources, string $page, array $objects): string
    {
        $inheritedResources = $this->inheritedPdfPageResources($page, $objects);

        if (! $inheritedResources) {
            return $resources;
        }

        [$inherited] = $inheritedResources;

        foreach (['/Font', '/XObject', '/ExtGState', '/ColorSpace', '/Pattern', '/Shading', '/Properties', '/ProcSet'] as $name) {
            if (str_contains($resources, $name)) {
                continue;
            }

            $entry = $this->pdfDictionaryEntry($inherited, $name);

            if ($entry !== null) {
                $resources = preg_replace('/>>\s*$/', ' ' . $entry . ' >>', $resources, 1) ?? $resources;
            }
        }

        return $resources;
    }

    private function pdfDictionaryEntry(string $dictionary, string $name): ?string
    {
        $namePosition = strpos($dictionary, $name);

        if ($namePosition === false) {
            return null;
        }

        $valueStart = $namePosition + strlen($name);
        $length = strlen($dictionary);

        while ($valueStart < $length && ctype_space($dictionary[$valueStart])) {
            $valueStart++;
        }

        if ($valueStart >= $length) {
            return null;
        }

        $valueEnd = $this->pdfValueEnd($dictionary, $valueStart);

        if ($valueEnd <= $valueStart) {
            return null;
        }

        return $name . ' ' . trim(substr($dictionary, $valueStart, $valueEnd - $valueStart));
    }

    private function pdfValueEnd(string $content, int $start): int
    {
        $length = strlen($content);
        $first = substr($content, $start, 2);

        if ($first === '<<') {
            $depth = 0;

            for ($position = $start; $position < $length - 1; $position++) {
                $pair = substr($content, $position, 2);

                if ($pair === '<<') {
                    $depth++;
                    $position++;
                    continue;
                }

                if ($pair === '>>') {
                    $depth--;
                    $position++;

                    if ($depth === 0) {
                        return $position + 1;
                    }
                }
            }

            return $length;
        }

        if ($content[$start] === '[') {
            $depth = 0;

            for ($position = $start; $position < $length; $position++) {
                if ($content[$position] === '[') {
                    $depth++;
                } elseif ($content[$position] === ']') {
                    $depth--;

                    if ($depth === 0) {
                        return $position + 1;
                    }
                }
            }

            return $length;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/', $content, $match, 0, $start)) {
            return $start + strlen($match[0]);
        }

        $nextName = preg_match('/\s\/[A-Za-z0-9_.-]+\b/', $content, $match, PREG_OFFSET_CAPTURE, $start)
            ? $match[0][1]
            : strpos($content, '>>', $start);

        return $nextName === false ? $length : $nextName;
    }

    private function addPdfFontResources(string $resources, string $fontResources, array $objects, array &$newObjects): string
    {
        $fontBounds = $this->pdfDictionaryBounds($resources, '/Font');

        if ($fontBounds) {
            return $this->appendPdfDictionaryEntries($resources, $fontBounds, $fontResources);
        }

        if (preg_match('/\/Font\s+(\d+)\s+\d+\s+R/', $resources, $fontRefMatch)) {
            $fontRef = (int) $fontRefMatch[1];
            $fontObject = $newObjects[$fontRef] ?? $objects[$fontRef] ?? null;

            if ($fontObject && str_starts_with(trim($fontObject), '<<')) {
                $newObjects[$fontRef] = preg_replace(
                    '/>>\s*$/',
                    ' ' . $fontResources . ' >>',
                    $fontObject,
                    1
                ) ?? $fontObject;

                return $resources;
            }
        }

        return preg_replace('/>>\s*$/', ' /Font << ' . $fontResources . ' >> >>', $resources, 1) ?? $resources;
    }

    private function addPdfXObjectResources(string $resources, array $xobjects, array $objects, array &$newObjects): string
    {
        if ($xobjects === []) {
            return $resources;
        }

        $xobjectEntries = $this->pdfXObjectResourceEntries($xobjects);
        $xobjectBounds = $this->pdfDictionaryBounds($resources, '/XObject');

        if ($xobjectBounds) {
            return $this->appendPdfDictionaryEntries($resources, $xobjectBounds, $xobjectEntries);
        }

        if (preg_match('/\/XObject\s+(\d+)\s+\d+\s+R/', $resources, $xobjectRefMatch)) {
            $xobjectRef = (int) $xobjectRefMatch[1];
            $xobjectObject = $newObjects[$xobjectRef] ?? $objects[$xobjectRef] ?? null;

            if ($xobjectObject && str_starts_with(trim($xobjectObject), '<<')) {
                $newObjects[$xobjectRef] = preg_replace(
                    '/>>\s*$/',
                    ' ' . $xobjectEntries . ' >>',
                    $xobjectObject,
                    1
                ) ?? $xobjectObject;

                return $resources;
            }
        }

        return preg_replace('/>>\s*$/', ' /XObject << ' . $xobjectEntries . ' >> >>', $resources, 1) ?? $resources;
    }

    private function appendPdfDictionaryEntries(string $content, array $bounds, string $entries): string
    {
        [, $end] = $bounds;

        return substr($content, 0, $end - 2)
            . ' ' . $entries . ' '
            . substr($content, $end - 2);
    }

    private function pdfXObjectResources(array $xobjects): string
    {
        if ($xobjects === []) {
            return '';
        }

        return ' /XObject << ' . $this->pdfXObjectResourceEntries($xobjects) . ' >>';
    }

    private function pdfXObjectResourceEntries(array $xobjects): string
    {
        $resources = collect($xobjects)
            ->map(fn ($ref, $name) => '/' . $name . ' ' . $ref . ' 0 R')
            ->implode(' ');

        return $resources;
    }

    private function signatureImagePdfObject(string $text, int $width, int $height): ?string
    {
        $text = trim($text);
        $fontPath = $this->signaturePdfFontPath();

        if ($text === '' || ! $fontPath || ! function_exists('imagettftext')) {
            return null;
        }

        $imageWidth = max(180, $width * 3);
        $imageHeight = max(54, $height * 3);
        $image = imagecreatetruecolor($imageWidth, $imageHeight);

        if (! $image) {
            return null;
        }

        imageantialias($image, true);

        $background = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $imageWidth, $imageHeight, $background);

        $fontSize = min(86, (int) floor($imageHeight * 0.72));
        $bbox = false;

        while ($fontSize >= 12) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textWidth = abs(($bbox[2] ?? 0) - ($bbox[0] ?? 0));
            $textHeight = abs(($bbox[7] ?? 0) - ($bbox[1] ?? 0));

            if ($textWidth <= $imageWidth - 18 && $textHeight <= $imageHeight - 8) {
                break;
            }

            $fontSize -= 2;
        }

        if (! is_array($bbox)) {
            imagedestroy($image);

            return null;
        }

        $textWidth = abs(($bbox[2] ?? 0) - ($bbox[0] ?? 0));
        $textHeight = abs(($bbox[7] ?? 0) - ($bbox[1] ?? 0));
        $x = (int) max(4, (($imageWidth - $textWidth) / 2) - ($bbox[0] ?? 0));
        $y = (int) max($textHeight + 4, (($imageHeight - $textHeight) / 2) - ($bbox[7] ?? 0));
        imagettftext($image, $fontSize, 0, $x, $y, $ink, $fontPath, $text);

        $pixels = '';

        for ($row = 0; $row < $imageHeight; $row++) {
            for ($column = 0; $column < $imageWidth; $column++) {
                $rgb = imagecolorat($image, $column, $row);
                $pixels .= chr(($rgb >> 16) & 0xFF) . chr(($rgb >> 8) & 0xFF) . chr($rgb & 0xFF);
            }
        }

        imagedestroy($image);
        $compressedPixels = gzcompress($pixels);

        return "<< /Type /XObject /Subtype /Image /Width " . $imageWidth . " /Height " . $imageHeight . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedPixels) . " >>\nstream\n" . $compressedPixels . "\nendstream";
    }

    private function signaturePdfFontPath(): ?string
    {
        $candidates = [
            resource_path('fonts/GreatVibes-Regular.ttf'),
            public_path('fonts/GreatVibes-Regular.ttf'),
            storage_path('app/fonts/GreatVibes-Regular.ttf'),
            'C:\\Windows\\Fonts\\KUNSTLER.TTF',
            'C:\\Windows\\Fonts\\FRSCRIPT.TTF',
            'C:\\Windows\\Fonts\\segoesc.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function pdfDictionaryBounds(string $content, string $name): ?array
    {
        $namePosition = strpos($content, $name);

        if ($namePosition === false) {
            return null;
        }

        $start = strpos($content, '<<', $namePosition);

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $length = strlen($content);

        for ($position = $start; $position < $length - 1; $position++) {
            $pair = substr($content, $position, 2);

            if ($pair === '<<') {
                $depth++;
                $position++;
                continue;
            }

            if ($pair === '>>') {
                $depth--;
                $position++;

                if ($depth === 0) {
                    return [$start, $position + 1];
                }
            }
        }

        return null;
    }

    private function pdfStreamObject(string $content): string
    {
        return "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
    }

    private function pdfXref(array $offsets): string
    {
        ksort($offsets);

        $xref = "xref\n";
        $refs = array_keys($offsets);
        $index = 0;

        while ($index < count($refs)) {
            $start = $refs[$index];
            $section = [$start => $offsets[$start]];
            $index++;

            while ($index < count($refs) && $refs[$index] === array_key_last($section) + 1) {
                $section[$refs[$index]] = $offsets[$refs[$index]];
                $index++;
            }

            $xref .= $start . ' ' . count($section) . "\n";

            foreach ($section as $offset) {
                $xref .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
            }
        }

        return $xref;
    }

    private function signatureCertificatePdfContent(SalesEndorsement $endorsement, int $pageNumber, ?int $signatureImageRef = null): string
    {
        $brand = $endorsement->brand;
        $timezone = 'America/New_York';
        $senderName = $brand?->imprint_name ?? 'CreatiVision Outsourcing';
        $senderEmail = $endorsement->contractSender?->email;
        $documentId = $endorsement->endorsement_code ?: 'SE-' . $endorsement->id;
        $signerName = $endorsement->contract_signer_name ?: $endorsement->author_name ?: 'Signer';
        $signerEmail = $endorsement->contract_signer_email ?: $endorsement->email;
        $signature = $endorsement->contract_signature_text ?: $signerName;
        $completedAt = $this->certificatePdfDate($endorsement->contract_signed_at ?: now(), $timezone);
        $sentAt = $this->certificatePdfDate($endorsement->contract_sent_at ?: $endorsement->created_at, $timezone);
        $signedAt = $this->certificatePdfDate($endorsement->contract_signed_at, $timezone);
        $sender = $senderName . ($senderEmail ? ' (' . $senderEmail . ')' : '');
        $signerIp = $endorsement->contract_signer_ip ?: '-';

        $commands = [
            'q',
            '0.35 0.85 0.65 RG 3 w 28 28 556 736 re S',
            '0 0 0 rg',
            $this->pdfText('Signature Certificate', 50, 700, 28, 'F1'),
            $this->pdfText('Document completed by all parties on ' . $completedAt, 50, 672, 12, 'F1'),
            $this->pdfText('Document ID: ' . $documentId, 50, 650, 12, 'F1'),
            $this->pdfText('Sender information', 50, 606, 18, 'F1'),
            $this->pdfText('Sent On:', 50, 572, 11, 'F1'),
            $this->pdfText($sentAt, 138, 572, 11, 'F1'),
            $this->pdfText('Timezone:', 50, 550, 11, 'F1'),
            $this->pdfText('Eastern Time', 138, 550, 11, 'F1'),
            $this->pdfText('Sender:', 50, 528, 11, 'F1'),
            $this->pdfText($sender, 138, 528, 11, 'F1'),
            '0.78 0.82 0.88 RG 0.7 w 50 490 m 562 490 l S',
            '0.94 0.96 0.98 rg 50 350 512 108 re f',
            '0 0 0 rg',
            $this->pdfText($signerName, 62, 442, 12, 'F2'),
            $this->pdfText($signerEmail ?: '-', 62, 423, 10, 'F1'),
            $this->pdfText('Signed:', 62, 388, 10, 'F1'),
            $this->pdfText($signedAt ?: '-', 118, 388, 10, 'F1'),
            $this->pdfText('IP:', 62, 368, 10, 'F1'),
            $this->pdfText($signerIp, 118, 368, 10, 'F1'),
            '1 1 1 rg 378 366 164 64 re f',
            '0 0 0 rg',
            $signatureImageRef
                ? 'q 164 0 0 54 378 371 cm /CERTSIG Do Q'
                : $this->pdfText($signature, 398, 396, 24, 'F3'),
            $this->pdfText('Page ' . $pageNumber . ' of ' . $pageNumber, 50, 308, 10, 'F1'),
            'Q',
        ];

        return implode("\n", $commands);
    }

    private function certificatePdfDate($date, string $timezone): string
    {
        if (! $date) {
            return '';
        }

        return $date->copy()->timezone($timezone)->format('m/d/Y @ H:i T');
    }

    private function pdfText(string $text, int $x, int $y, int $size, string $font): string
    {
        return 'BT /' . $font . ' ' . $size . ' Tf ' . $x . ' ' . $y . ' Td (' . $this->pdfEscape($text) . ') Tj ET';
    }

    private function pdfEscape(string $text): string
    {
        return str_replace(
            ["\\", '(', ')', "\r", "\n"],
            ["\\\\", "\\(", "\\)", ' ', ' '],
            $text
        );
    }

    private function userHasPermission(Request $request, string $permission): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission($permission);
    }

    private function userCanAccessBrand(Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request->user())
            || (int) $request->user()?->brand_id === (int) $brandId;
    }
}
