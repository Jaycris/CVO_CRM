<?php

namespace Tests\Feature;

use App\Mail\{ContractSignatureCcNotificationMail, ContractSignatureRequestMail, UserInvitationMail};
use App\Models\{Brand, BrandMailSetting, EmailAccount, ProductionProject, Role, SalesEndorsement, User};
use App\Notifications\{ContractSignatureRequestSentNotification, ProductionProjectCompletedNotification};
use App\Support\BrandMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Notification};
use Tests\TestCase;

class BrandMailSettingTest extends TestCase
{
    use RefreshDatabase;

    private Brand $inkspire;
    private Brand $horizon;
    private User $admin;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->inkspire = Brand::updateOrCreate(['imprint_name' => 'Inkspire Media House'], ['is_sales_brand' => true]);
        $this->horizon = Brand::create(['imprint_name' => 'Horizon Maple Media', 'is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'brand_id' => $this->inkspire->id]);
        $this->actingAs($this->admin);
    }

    private function payload(string $domain = 'horizon.test'): array
    {
        return ['is_enabled' => 1, 'display_name' => 'Brand Team', 'email_address' => 'contracts@'.$domain,
            'reply_to_address' => 'support@'.$domain, 'smtp_host' => 'mail.'.$domain, 'smtp_port' => 465,
            'smtp_encryption' => 'ssl', 'username' => 'contracts@'.$domain, 'password' => 'test-only-mail-password'];
    }

    private function configure(Brand $brand, string $domain): BrandMailSetting
    {
        $this->from(route('admin.brands.email.edit', $brand))->put(route('admin.brands.email.update', $brand), $this->payload($domain))
            ->assertRedirect(route('admin.brands.email.edit', $brand))->assertSessionHasNoErrors();
        return BrandMailSetting::where('brand_id', $brand->id)->firstOrFail();
    }

    public function test_sender_settings_are_per_brand_encrypted_and_not_exposed_in_edit_page(): void
    {
        $first = $this->configure($this->inkspire, 'inkspire.test');
        $second = $this->configure($this->horizon, 'horizon.test');
        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame('test-only-mail-password', $second->getRawOriginal('encrypted_password'));
        $this->assertSame('test-only-mail-password', $second->plainPassword());
        $this->assertArrayNotHasKey('encrypted_password', $second->toArray());
        $this->get(route('admin.brands.email.edit', $this->horizon))->assertOk()->assertSee('contracts@horizon.test')
            ->assertDontSee('test-only-mail-password')->assertDontSee($second->encrypted_password, false);
        $this->assertSame($second->id, BrandMailer::account($this->horizon->id)->id);
        Mail::assertNothingSent();
    }

    public function test_blank_password_keeps_saved_secret_and_invalid_setup_is_atomic(): void
    {
        $setting = $this->configure($this->horizon, 'horizon.test');
        $cipher = $setting->encrypted_password;
        $this->put(route('admin.brands.email.update', $this->horizon), array_replace($this->payload(), ['password' => '']))->assertSessionHasNoErrors();
        $this->assertSame($cipher, $setting->fresh()->encrypted_password);
        $this->put(route('admin.brands.email.update', $this->horizon), array_replace($this->payload(), ['smtp_port' => 99999]))->assertSessionHasErrors('smtp_port');
        $this->assertNull(session('_old_input.password'));
        $this->assertSame(465, $setting->fresh()->smtp_port);
        $this->put(route('admin.brands.email.update', $this->inkspire), array_replace($this->payload('inkspire.test'), ['password' => '']))->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('brand_mail_settings', ['brand_id' => $this->inkspire->id]);
    }

    public function test_only_admin_can_read_or_change_brand_smtp_settings(): void
    {
        $role = Role::firstOrCreate(['name' => 'Sales Agent'], ['slug' => 'sales-agent', 'department' => 'Sales']);
        $user = User::factory()->create(['role_id' => $role->id, 'brand_id' => $this->horizon->id]);
        $this->actingAs($user)->get(route('admin.brands.email.edit', $this->horizon))->assertForbidden();
        $this->put(route('admin.brands.email.update', $this->horizon), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('brand_mail_settings', 0);
    }

    private function endorsement(Brand $brand, User $agent): SalesEndorsement
    {
        return SalesEndorsement::create(['brand_id' => $brand->id, 'agent_id' => $agent->id,
            'author_name' => 'Mail Author', 'email' => 'author@example.test', 'contact_number' => '5550100',
            'street_name' => 'Street', 'city_state' => 'City', 'zip_code' => '1000', 'book_title' => 'Mail Book',
            'isbn' => '123', 'services' => 'Publishing', 'amount' => 1000, 'amount_to_be_paid' => 1000, 'payment' => 'Full Payment']);
    }

    public function test_contract_requests_and_cc_use_sale_brand_not_sending_admins_primary_brand(): void
    {
        $inkspire = $this->configure($this->inkspire, 'inkspire.test');
        $horizon = $this->configure($this->horizon, 'horizon.test');
        $agent = User::factory()->create(['brand_id' => $this->inkspire->id, 'multiple_brands_enabled' => true]);
        $agent->accessibleBrands()->sync([$this->inkspire->id => ['email' => 'agent@inkspire.test'], $this->horizon->id => ['email' => 'agent@horizon.test']]);
        foreach ([[$this->inkspire, $inkspire], [$this->horizon, $horizon]] as [$brand, $setting]) {
            $endorsement = $this->endorsement($brand, $agent);
            $cc = $agent->emailForBrand($brand->id);
            $this->post(route('finance.contracts.esign.send', $endorsement), ['recipient_email' => 'author@example.test', 'cc_emails' => $cc])
                ->assertRedirect(route('finance.contracts.esign', $endorsement))->assertSessionHasNoErrors();
            Mail::assertSent(ContractSignatureRequestMail::class, fn ($mail) => $mail->endorsement->id === $endorsement->id
                && $mail->mailer === 'brand_sender_'.$setting->id && $mail->packet['senderEmail'] === $setting->email_address
                && $mail->envelope()->from->address === $setting->email_address
                && $mail->envelope()->from->name === 'Brand Team'
                && str_contains($mail->envelope()->subject, $brand->imprint_name)
                && $mail->envelope()->replyTo[0]->address === $setting->reply_to_address
                && $mail->packet['agentEmail'] === $cc);
            Mail::assertSent(ContractSignatureCcNotificationMail::class, fn ($mail) => $mail->hasTo($cc)
                && $mail->mailer === 'brand_sender_'.$setting->id && $mail->envelope()->from->address === $setting->email_address);
            $this->assertSame('sent', $endorsement->fresh()->contract_status);
        }
        Notification::assertSentTo($agent, ContractSignatureRequestSentNotification::class);
        $this->assertSame('mail.horizon.test', config('mail.mailers.brand_sender_'.$horizon->id.'.host'));
        $this->assertSame('mail.inkspire.test', config('mail.mailers.brand_sender_'.$inkspire->id.'.host'));
    }

    public function test_invitations_and_project_completion_use_their_brand_sender(): void
    {
        $setting = $this->configure($this->horizon, 'horizon.test');
        $user = User::factory()->create(['brand_id' => $this->horizon->id]);
        BrandMailer::send(BrandMailer::account($user->brand_id), $user->email, new UserInvitationMail($user));
        Mail::assertSent(UserInvitationMail::class, fn ($mail) => $mail->hasTo($user->email)
            && $mail->mailer === 'brand_sender_'.$setting->id && $mail->from[0]['address'] === 'contracts@horizon.test');
        $endorsement = $this->endorsement($this->horizon, $user);
        $project = ProductionProject::create(['brand_id' => $this->horizon->id, 'sales_endorsement_id' => $endorsement->id, 'tracker_type' => 'publishing', 'status' => 'fulfilled']);
        $notification = new ProductionProjectCompletedNotification($project);
        $message = $notification->toMail($user);
        $this->assertSame('brand_sender_'.$setting->id, $message->mailer);
        $this->assertSame('contracts@horizon.test', $message->from[0]);
        $user->update(['brand_id' => $this->inkspire->id, 'multiple_brands_enabled' => true]);
        $user->accessibleBrands()->sync([$this->inkspire->id, $this->horizon->id => ['email' => 'employee@horizon.test']]);
        $this->assertSame('employee@horizon.test', $user->routeNotificationFor('mail', $notification));
        $this->assertSame($user->email, $user->routeNotificationFor('mail', new \Illuminate\Auth\Notifications\ResetPassword('test-reset-token')));
    }

    public function test_failed_brand_smtp_send_does_not_mark_contract_sent_or_expose_server_details(): void
    {
        $this->configure($this->horizon, 'horizon.test');
        $endorsement = $this->endorsement($this->horizon, $this->admin);
        Mail::shouldReceive('purge')->once();
        Mail::shouldReceive('mailer')->once()->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('Private upstream connection detail'));
        $response = $this->from(route('finance.contracts.esign', $endorsement))->post(route('finance.contracts.esign.send', $endorsement),
            ['recipient_email' => 'author@example.test']);
        $response->assertRedirect(route('finance.contracts.esign', $endorsement))->assertSessionHasErrors('recipient_email');
        $this->assertNull($endorsement->fresh()->contract_sent_at);
        $this->assertNotSame('sent', $endorsement->fresh()->contract_status);
        $this->assertStringNotContainsString('Private upstream', session('errors')->first('recipient_email'));
        $this->get(route('finance.contracts.esign', $endorsement))->assertOk()->assertSee('The contract email could not be sent')->assertDontSee('Private upstream');
    }

    public function test_legacy_shared_brand_mailbox_and_system_fallback_still_work(): void
    {
        $legacy = EmailAccount::create(['brand_id' => $this->inkspire->id, 'display_name' => 'Legacy Inkspire',
            'email_address' => 'legacy@inkspire.test', 'username' => 'legacy@inkspire.test', 'is_shared' => true,
            'imap_host' => 'mail.inkspire.test', 'imap_port' => 993, 'imap_encryption' => 'ssl',
            'smtp_host' => 'mail.inkspire.test', 'smtp_port' => 465, 'smtp_encryption' => 'ssl']);
        $legacy->setPlainPassword('legacy-test-password');
        $legacy->save();
        $this->assertInstanceOf(EmailAccount::class, BrandMailer::account($this->inkspire->id));
        $this->assertNull(BrandMailer::account($this->horizon->id));
        $setting = $this->configure($this->inkspire, 'inkspire.test');
        $this->assertInstanceOf(BrandMailSetting::class, BrandMailer::account($this->inkspire->id));
        $this->put(route('admin.brands.email.update', $this->inkspire), array_replace($this->payload('inkspire.test'), ['is_enabled' => 0, 'password' => '']))->assertSessionHasNoErrors();
        $this->assertInstanceOf(EmailAccount::class, BrandMailer::account($this->inkspire->id));
        $this->assertNotNull($setting->fresh()->encrypted_password);
        BrandMailer::send(null, $this->admin->email, new UserInvitationMail($this->admin));
        Mail::assertSent(UserInvitationMail::class);
    }
}
