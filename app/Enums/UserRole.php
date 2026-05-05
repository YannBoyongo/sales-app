<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case PosUser = 'pos_user';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::User => 'Utilisateur',
            self::PosUser => 'Caissier (terminal)',
            self::Accountant => 'Comptable',
        };
    }
}
