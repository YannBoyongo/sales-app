<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case StockManager = 'stock_manager';
    case Logistician = 'logistician';
    case PosUser = 'pos_user';
    case Cashier = 'cashier';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Manager => 'Manager',
            self::StockManager => 'Magasinier',
            self::Logistician => 'Logisticien',
            self::PosUser => 'Utilisateur POS',
            self::Cashier => 'Caissier',
            self::Accountant => 'Comptable',
        };
    }
}
