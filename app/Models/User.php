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
            'role' => UserRole::class,
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

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAccountant(): bool
    {
        return $this->role === UserRole::Accountant;
    }

    public function isShopUser(): bool
    {
        return $this->role === UserRole::User;
    }

    public function isPosUser(): bool
    {
        return $this->role === UserRole::PosUser;
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
        return ! $this->isShopUser() && ! $this->isPosUser();
    }

    /** Accès à l’écran caisse (au moins un terminal ou périmètre élargi). */
    public function canAccessPosSales(): bool
    {
        if ($this->isAdmin() || $this->isAccountant()) {
            return true;
        }
        if ($this->isPosUser()) {
            return $this->posTerminals()->exists();
        }

        return $this->isShopUser() && $this->branch_id !== null;
    }

    /** Approuver ou refuser une remise sur une vente (workflow caisse). */
    public function canApproveSaleDiscounts(): bool
    {
        return $this->isAdmin();
    }
}
