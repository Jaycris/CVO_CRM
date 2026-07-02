<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    private ?array $resolvedPermissionMap = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'brand_id',
        'team_id',
        'first_name',
        'last_name',
        'department',
        'email',
        'phone_number',
        'profile_photo_path',
        'password',
        'password_created_at',
        'invitation_expires_at',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function managedTeams()
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    public function ledTeams()
    {
        return $this->hasMany(Team::class, 'team_leader_id');
    }

    public function permissionOverrides()
    {
        return $this->belongsToMany(Permission::class)->withPivot('effect')->withTimestamps();
    }

    public function allowedPermissionOverrides()
    {
        return $this->permissionOverrides()->wherePivot('effect', 'allow');
    }

    public function deniedPermissionOverrides()
    {
        return $this->permissionOverrides()->wherePivot('effect', 'deny');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->resolvedPermissionMap()[$permission] ?? false;
    }

    private function resolvedPermissionMap(): array
    {
        if ($this->resolvedPermissionMap !== null) {
            return $this->resolvedPermissionMap;
        }

        $rolePermissionKeys = $this->role
            ? $this->role->permissionRecords()->pluck('key')->all()
            : [];

        $permissions = array_fill_keys($rolePermissionKeys, true);

        $this->permissionOverrides()
            ->select('permissions.key', 'permission_user.effect')
            ->get()
            ->each(function (Permission $permission) use (&$permissions) {
                $permissions[$permission->key] = $permission->pivot->effect === 'allow';
            });

        return $this->resolvedPermissionMap = $permissions;
    }

    public function verificationQueueLeads()
    {
        return $this->hasMany(Lead::class, 'verification_assigned_to');
    }

    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function assignedProductionProjects()
    {
        return $this->hasMany(ProductionProject::class, 'assigned_to');
    }

    public function fulfillmentProjects()
    {
        return $this->hasMany(ProductionProject::class, 'fulfillment_officer_id');
    }

    public function suspendedBy()
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_created_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
