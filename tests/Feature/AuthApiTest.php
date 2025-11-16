<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        Role::create(['name' => 'viewer']);
        Role::create(['name' => 'super-admin']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $user->assignRole('viewer');

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'organization_id',
                'is_active',
                'roles',
                'permissions',
            ],
        ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals('test@example.com', $response->json('user.email'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_inactive_account(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'organization_id' => $this->organization->id,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertStringContainsString('deactivated', $response->json('errors.email.0'));
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_register_new_account(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_id' => $this->organization->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'token',
            'user' => ['id', 'name', 'email', 'organization_id', 'roles'],
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->hasRole('viewer'));
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_id' => $this->organization->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
            'organization_id' => $this->organization->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logged out successfully']);

        // Token should be revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_user_can_logout_from_all_devices(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        // Create multiple tokens
        $token1 = $user->createToken('device-1')->plainTextToken;
        $user->createToken('device-2');
        $user->createToken('device-3');

        $this->assertEquals(3, $user->tokens()->count());

        $response = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/logout-all');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logged out from all devices successfully']);

        // All tokens should be revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_user_can_get_their_profile(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'organization_id',
                'is_active',
                'organization',
                'roles',
                'permissions',
                'created_at',
            ],
        ]);

        $this->assertEquals($user->id, $response->json('data.id'));
        $this->assertContains('super-admin', $response->json('data.roles'));
    }

    public function test_unauthenticated_user_cannot_access_me_endpoint(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => Hash::make('oldpassword'),
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'current_password' => 'oldpassword',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profile updated successfully',
            'user' => [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ],
        ]);

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_update_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }
}
