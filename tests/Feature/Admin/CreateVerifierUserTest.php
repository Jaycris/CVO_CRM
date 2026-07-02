<?php

use App\Mail\UserInvitationMail;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('an admin can create a verifier user in lead generation', function () {
    Mail::fake();

    Department::firstOrCreate(['name' => 'Lead Generation']);
    $brand = Brand::firstOrCreate(['imprint_name' => 'CreatiVision Outsourcing'], [
        'primary_color' => '#064e3b',
        'accent_color' => '#f59e0b',
    ]);
    $adminRole = Role::firstOrCreate(['name' => 'Admin'], [
        'slug' => 'administration-admin',
        'department' => 'Administration',
    ]);
    $verifierRole = Role::firstOrCreate(['name' => 'Verifier', 'department' => 'Lead Generation'], [
        'slug' => 'lead-generation-verifier',
    ]);
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'brand_id' => $brand->id,
        'department' => 'Administration',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'first_name' => 'Vera',
        'last_name' => 'Verifier',
        'department' => 'Lead Generation',
        'brand_id' => $brand->id,
        'email' => 'vera.verifier@example.com',
        'phone_number' => '',
        'role_id' => $verifierRole->id,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', [
        'email' => 'vera.verifier@example.com',
        'department' => 'Lead Generation',
        'role_id' => $verifierRole->id,
    ]);
    Mail::assertSent(UserInvitationMail::class);
});
