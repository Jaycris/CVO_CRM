<?php

use App\Mail\UserInvitationMail;
use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('an admin can reset another users password and send a new setup link', function () {
    Mail::fake();

    $brand = Brand::firstOrCreate(['imprint_name' => 'CreatiVision Outsourcing'], [
        'primary_color' => '#064e3b',
        'accent_color' => '#f59e0b',
    ]);
    $adminRole = Role::firstOrCreate(['name' => 'Admin'], [
        'slug' => 'administration-admin',
        'department' => 'Administration',
    ]);
    $salesRole = Role::firstOrCreate(['name' => 'Branding Specialist'], [
        'slug' => 'sales-branding-specialist',
        'department' => 'Sales',
    ]);
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'brand_id' => $brand->id,
        'department' => 'Administration',
    ]);
    $user = User::factory()->create([
        'role_id' => $salesRole->id,
        'brand_id' => $brand->id,
        'department' => 'Sales',
        'password' => Hash::make('old-password'),
        'password_created_at' => now(),
        'invitation_expires_at' => null,
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.users.reset-password', $user));

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success');
    $user->refresh();

    expect($user->password_created_at)->toBeNull()
        ->and($user->invitation_expires_at)->not->toBeNull()
        ->and(Hash::check('old-password', $user->password))->toBeFalse();

    Mail::assertSent(UserInvitationMail::class, fn (UserInvitationMail $mail) => $mail->user->is($user));
});

test('an admin cannot reset their own password from the user directory', function () {
    Mail::fake();

    $brand = Brand::firstOrCreate(['imprint_name' => 'CreatiVision Outsourcing'], [
        'primary_color' => '#064e3b',
        'accent_color' => '#f59e0b',
    ]);
    $adminRole = Role::firstOrCreate(['name' => 'Admin'], [
        'slug' => 'administration-admin',
        'department' => 'Administration',
    ]);
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'brand_id' => $brand->id,
        'department' => 'Administration',
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.users.reset-password', $admin));

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('error');
    Mail::assertNothingSent();
});
