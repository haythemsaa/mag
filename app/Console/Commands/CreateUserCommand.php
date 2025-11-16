<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create
                            {--name= : The user name}
                            {--email= : The user email}
                            {--password= : The user password}
                            {--organization= : The organization ID}
                            {--role= : The role name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user with a role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 FleetManager Pro - User Creation');
        $this->newLine();

        // Get or ask for name
        $name = $this->option('name') ?: $this->ask('Name');

        // Get or ask for email
        $email = $this->option('email') ?: $this->ask('Email');

        // Validate email uniqueness
        if (User::where('email', $email)->exists()) {
            $this->error("❌ User with email {$email} already exists!");
            return Command::FAILURE;
        }

        // Get or ask for password
        $password = $this->option('password') ?: $this->secret('Password');

        // Get or ask for organization
        $organizationId = $this->option('organization');
        if (!$organizationId) {
            $organizations = Organization::all();
            if ($organizations->isEmpty()) {
                $this->warn('No organizations found. Creating user without organization.');
                $organizationId = null;
            } else {
                $this->table(['ID', 'Name'], $organizations->map(fn($org) => [$org->id, $org->name])->toArray());
                $organizationId = $this->ask('Organization ID (leave empty for none)') ?: null;
            }
        }

        // Get or ask for role
        $roleName = $this->option('role');
        if (!$roleName) {
            $roles = Role::all();
            if ($roles->isEmpty()) {
                $this->error('❌ No roles found! Run php artisan db:seed --class=RolePermissionSeeder first.');
                return Command::FAILURE;
            }

            $this->table(['Name', 'Permissions Count'], $roles->map(fn($role) => [
                $role->name,
                $role->permissions->count()
            ])->toArray());
            $roleName = $this->ask('Role name');
        }

        // Validate role exists
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            $this->error("❌ Role '{$roleName}' not found!");
            return Command::FAILURE;
        }

        // Create user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'organization_id' => $organizationId,
            'is_active' => true,
        ]);

        // Assign role
        $user->assignRole($role);

        $this->newLine();
        $this->info('✅ User created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Organization ID', $user->organization_id ?? 'None'],
                ['Role', $roleName],
                ['Permissions', $role->permissions->count()],
                ['Active', $user->is_active ? 'Yes' : 'No'],
            ]
        );

        return Command::SUCCESS;
    }
}
