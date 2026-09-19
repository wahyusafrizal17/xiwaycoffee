<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['name', 'email', 'phone', 'avatar', 'password', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use AppliesFillableAttribute, HasFactory, Notifiable, SoftDeletes;

    // ponytail: $fillable kept — some hosts run Laravel that ignores #[Fillable]
    protected $fillable = ['name', 'email', 'phone', 'avatar', 'password', 'is_active', 'last_login_at'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'outlet_users')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function role(): ?Role
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->first();
        }

        return $this->roles()->first();
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;
        $assigned = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')
            : $this->roles()->pluck('name');

        return $assigned->intersect($roles)->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function canSwitchOutlet(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole('admin');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        return $this->roles->contains(
            fn (Role $role) => $role->permissions->contains('name', $permission)
                || $role->permissions->contains('name', '*')
        );
    }

    public function canAccessOutlet(?int $outletId): bool
    {
        if (! $outletId) {
            return false;
        }

        if ($this->isSuperAdmin() || $this->hasRole('admin')) {
            return true;
        }

        return $this->outlets()->where('outlets.id', $outletId)->exists();
    }

    public function defaultOutlet(): ?Outlet
    {
        return $this->outlets()->wherePivot('is_default', true)->first()
            ?? $this->outlets()->first();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = $parts[0][0] ?? 'U';
        $last = isset($parts[1]) ? $parts[1][0] : '';

        return strtoupper($first.$last);
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        return asset('storage/'.$this->avatar);
    }

    public function toModalArray(): array
    {
        $this->loadMissing(['roles', 'outlets']);
        $role = $this->roles->first();

        return [
            'id' => $this->id,
            'name' => $this->name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'role_id' => $role ? (string) $role->id : '',
            'role_label' => $role?->label ?? '—',
            'outlet_ids' => $this->outlets->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'outlets_label' => $this->outlets->pluck('name')->join(', ') ?: '—',
            'is_active' => (bool) $this->is_active,
            'status_label' => $this->is_active ? 'Aktif' : 'Nonaktif',
            'initials' => $this->initials(),
            'update_url' => route('users.update', $this),
        ];
    }
}
