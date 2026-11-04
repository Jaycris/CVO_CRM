<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailInboxSyncService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailboxDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private EmailAccount $account;
    private EmailMessage $message;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.mailbox_enabled' => false]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $brand = Brand::create(['imprint_name' => 'Mailbox Test Brand']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'department' => 'Admin', 'brand_id' => $brand->id]);
        $this->account = EmailAccount::create([
            'user_id' => $this->admin->id, 'brand_id' => $brand->id, 'display_name' => 'Saved mailbox',
            'email_address' => 'saved@example.test', 'username' => 'saved@example.test', 'imap_host' => 'invalid.test',
        ]);
        $this->message = EmailMessage::create(['email_account_id' => $this->account->id, 'uid' => 1, 'folder' => 'INBOX', 'subject' => 'Saved message', 'is_seen' => false]);
        $this->actingAs($this->admin);
    }

    public function test_email_menu_and_tour_are_hidden_without_querying_mailbox_tables(): void
    {
        $queries = [];
        \Illuminate\Support\Facades\DB::listen(function ($event) use (&$queries) { $queries[] = $event->sql; });
        $this->get(route('notes.index'))->assertOk()
            ->assertDontSee('data-feature-tour="email-menu"', false)
            ->assertDontSee('CRM Email')->assertDontSee(route('email.index'));
        $this->assertFalse(collect($queries)->contains(fn ($sql) => str_contains($sql, 'email_messages') || str_contains($sql, 'email_accounts')));
    }

    public function test_every_mailbox_endpoint_is_disabled_and_saved_data_is_untouched(): void
    {
        $beforeAccount = $this->account->fresh()->getAttributes();
        $beforeMessage = $this->message->fresh()->getAttributes();
        $this->mock(EmailInboxSyncService::class, function ($mock) { $mock->shouldNotReceive('sync'); $mock->shouldNotReceive('markSeen'); });
        foreach ([
            ['GET', route('email.index')],
            ['POST', route('email.accounts.store')],
            ['PUT', route('email.accounts.update', $this->account)],
            ['POST', route('email.accounts.sync', $this->account)],
            ['DELETE', route('email.messages.destroy', $this->account)],
            ['PATCH', route('email.messages.read', [$this->account, $this->message])],
            ['PATCH', route('email.signature.update')],
            ['POST', route('email.send')],
        ] as [$method, $url]) {
            $this->json($method, $url, ['message_ids' => [$this->message->id]])->assertNotFound();
        }
        $this->assertSame($beforeAccount, $this->account->fresh()->getAttributes());
        $this->assertSame($beforeMessage, $this->message->fresh()->getAttributes());
        $this->assertDatabaseCount('email_accounts', 1);
        $this->assertDatabaseCount('email_messages', 1);
    }

    public function test_manual_sync_and_scheduler_skip_mailboxes_when_disabled(): void
    {
        $this->mock(EmailInboxSyncService::class, fn ($mock) => $mock->shouldNotReceive('sync'));
        $this->artisan('email:sync-inboxes')->expectsOutput('CRM mailbox is disabled. Inbox sync skipped.')->assertSuccessful();
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'email:sync-inboxes'));
        $this->assertNotNull($event);
        $this->assertFalse($event->filtersPass($this->app));
        $this->assertNull($this->account->fresh()->last_synced_at);
    }

    public function test_sync_service_itself_does_not_connect_when_disabled(): void
    {
        $service = new EmailInboxSyncService;
        $this->assertSame(0, $service->sync($this->account));
        $service->markSeen($this->account, $this->message);
        $this->assertFalse($this->message->fresh()->is_seen);
    }

    public function test_mailbox_can_be_reenabled_without_recreating_saved_accounts(): void
    {
        config(['features.mailbox_enabled' => true]);
        $this->get(route('notes.index'))->assertOk()->assertSee('data-feature-tour="email-menu"', false);
        $this->get(route('email.index'))->assertOk()->assertSee('Saved mailbox');
        $this->mock(EmailInboxSyncService::class, fn ($mock) => $mock->shouldReceive('sync')->once()->andReturn(0));
        $this->artisan('email:sync-inboxes')->assertSuccessful();
    }

    public function test_password_reset_notifications_still_work_with_mailbox_disabled(): void
    {
        Notification::fake();
        auth()->logout();
        $this->post(route('password.email'), ['email' => $this->admin->email])->assertSessionHas('status');
        Notification::assertSentTo($this->admin, ResetPassword::class);
    }
}
