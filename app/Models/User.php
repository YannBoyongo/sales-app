<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'branch_id',
    ];

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
            'password' => 'hashed',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function posTerminals(): BelongsToMany
    {
        return $this->belongsToMany(PosTerminal::class, 'pos_terminal_user')->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(UserRole|string $role): bool
    {
        $slug = $role instanceof UserRole ? $role->value : (string) $role;

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $r) => $r->slug === $slug);
        }

        return $this->roles()->where('slug', $slug)->exists();
    }

    /**
     * @return list<string>
     */
    public function roleSlugs(): array
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles
                ->pluck('slug')
                ->filter()
                ->map(fn ($slug) => (string) $slug)
                ->values()
                ->all();
        }

        return $this->roles()->pluck('slug')->map(fn ($slug) => (string) $slug)->all();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    public function isAccountant(): bool
    {
        return $this->hasRole(UserRole::Accountant);
    }

    public function isManager(): bool
    {
        return $this->hasRole(UserRole::Manager);
    }

    public function isPosUser(): bool
    {
        return $this->hasRole(UserRole::PosUser);
    }

    public function isCashier(): bool
    {
        return $this->hasRole(UserRole::Cashier);
    }

    /** Admin ou comptable : vue agrégée multi-branches (filtre désactivé). */
    public function canBypassBranchScope(): bool
    {
        return $this->isAdmin() || $this->isAccountant();
    }

    public function canAccessAccounting(): bool
    {
        return $this->isAdmin() || $this->isAccountant();
    }

    /** Structure boutique, utilisateurs, paramètres, BC admin, etc. */
    public function canManageApplication(): bool
    {
        return $this->isAdmin();
    }

    /** Transferts inter-emplacements (hors rôle caisse / point de vente). */
    public function canManageStockTransfers(): bool
    {
        return ! $this->isPosUser() && ! $this->isCashier();
    }

    /** Accès à l’écran caisse (au moins un terminal ou périmètre élargi). */
    public function canAccessPosSales(): bool
    {
        if ($this->isAdmin() || $this->isAccountant()) {
            return true;
        }
        if ($this->isPosUser() || $this->isCashier()) {
            return $this->posTerminals()->exists();
        }

        return $this->isManager() && $this->branch_id !== null;
    }

    /** Approuver ou refuser une remise sur une vente (workflow caisse). */
    public function canApproveSaleDiscounts(): bool
    {
        return $this->isAdmin();
    }
}
