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

    public function isLogistician(): bool
    {
        return $this->hasRole(UserRole::Logistician);
    }

    /** Admin, comptable ou logisticien : vue agrégée multi-branches (filtre désactivé). */
    public function canBypassBranchScope(): bool
    {
        return $this->isAdmin() || $this->isAccountant() || $this->isLogistician();
    }

    public function canAccessAccounting(): bool
    {
        return $this->isAdmin() || $this->isAccountant();
    }

    /** Shifts fermés, clients, bons de caisse : comptable/admin ou caissier. */
    public function canAccessCashDeskFinanceFeatures(): bool
    {
        return $this->canAccessAccounting() || $this->isCashier();
    }

    /** Depuis un shift fermé : créer l’écriture + bon de caisse (caissier responsable ou compta). */
    public function canPushClosedShiftCashEntry(): bool
    {
        return $this->canAccessAccounting() || $this->isCashier();
    }

    /** Structure boutique, utilisateurs, paramètres, BC admin, etc. */
    public function canManageApplication(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Consultation stocks / produits / transferts / mouvements / PO sans action d’écriture.
     * Les administrateurs restent modifiables même s’ils ont aussi le rôle comptable.
     */
    public function isInventoryReadOnly(): bool
    {
        return $this->isAccountant() && ! $this->isAdmin();
    }

    /** Créer ou enregistrer un transfert de stock (hors caisse / point de vente / comptable en lecture seule). */
    public function canManageStockTransfers(): bool
    {
        return ! $this->isPosUser() && ! $this->isCashier() && ! $this->isAccountant();
    }

    /** Voir la liste et le détail des transferts (y compris comptable). */
    public function canViewStockTransfers(): bool
    {
        return $this->canManageStockTransfers() || $this->isAccountant();
    }

    /**
     * Accès au flux de vente (choix terminal, workspace, nouvelle vente).
     * Le comptable seul n’y accède pas ; un admin reste autorisé même avec le rôle comptable.
     * Logisticien : comme l’admin sur les terminaux (via canBypassBranchScope), sans approbation de remise.
     */
    public function canAccessPosSales(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($this->isLogistician()) {
            return true;
        }
        if ($this->isAccountant()) {
            return false;
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
