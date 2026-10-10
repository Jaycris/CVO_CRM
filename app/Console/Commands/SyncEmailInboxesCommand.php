<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailInboxSyncService;
use Illuminate\Console\Command;

class SyncEmailInboxesCommand extends Command
{
    protected $signature = 'email:sync-inboxes {--account= : Sync one email account ID only} {--limit=50 : Maximum messages to inspect per mailbox}';

    protected $description = 'Sync connected SiteGround inboxes even when users are not logged in.';

    public function handle(EmailInboxSyncService $syncService): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $accountId = $this->option('account');
        $query = EmailAccount::query()
            ->whereNotNull('imap_host')
            ->whereNotNull('username')
            ->orderBy('id');

        if ($accountId) {
            $query->whereKey((int) $accountId);
        }

        $totalSynced = 0;
        $failed = 0;

        $query->chunkById(25, function ($accounts) use ($syncService, $limit, &$totalSynced, &$failed) {
            foreach ($accounts as $account) {
                try {
                    $synced = $syncService->sync($account, $limit);
                    $totalSynced += $synced;
                    $this->line("{$account->email_address}: {$synced} updated");
                } catch (\Throwable $exception) {
                    $failed++;
                    report($exception);
                    $this->warn("{$account->email_address}: sync failed");
                }
            }
        });

        $this->info("Email sync complete. {$totalSynced} message(s) updated.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
