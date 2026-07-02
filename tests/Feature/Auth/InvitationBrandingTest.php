<?php

use App\Mail\UserInvitationMail;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('the password invitation page uses the CreatiVision identity', function () {
    $brand = Brand::create([
        'imprint_name' => 'Aurora Books',
        'crm_display_name' => 'Aurora CRM',
        'logo_path' => 'brands/aurora-logo.png',
        'primary_color' => '#123456',
        'accent_color' => '#abcdef',
    ]);
    $user = User::factory()->create([
        'brand_id' => $brand->id,
        'invitation_expires_at' => now()->addDays(7),
    ]);

    $url = URL::temporarySignedRoute(
        'invitation.password.create',
        now()->addHour(),
        ['user' => $user->id]
    );

    $this->get($url)
        ->assertOk()
        ->assertSee('CreatiVision CRM')
        ->assertSee('images/CreativeVision LOGO 1.png', false)
        ->assertSee('--brand-primary: #065f46', false)
        ->assertDontSee('storage/brands/aurora-logo.png', false)
        ->assertDontSee('images/inkspire-logo.png', false);
});

test('the invitation email uses the CreatiVision email identity', function () {
    $brand = Brand::create([
        'imprint_name' => 'Aurora Books',
        'crm_display_name' => 'Aurora CRM',
        'logo_path' => 'brands/aurora-logo.png',
        'primary_color' => '#123456',
        'accent_color' => '#abcdef',
    ]);
    $user = User::factory()->create(['brand_id' => $brand->id]);

    $mail = new UserInvitationMail($user);
    $html = $mail->render();

    expect($mail->envelope()->subject)->toBe('Create Your CreatiVision CRM Password')
        ->and($html)->toContain('Your CreatiVision CRM account has been created successfully.')
        ->and($html)->toContain('images/CreativeVision LOGO 1.png')
        ->and($html)->toContain('linear-gradient(135deg, #064e3b 0%, #022c22 45%, #050505 100%)')
        ->and($html)->not->toContain('storage/brands/aurora-logo.png')
        ->and($html)->not->toContain('images/inkspire-logo.png');
});
