<?php

namespace Database\Seeders;

use App\Models\CostCenter;
use App\Models\CostTrackingEntry;
use App\Models\CostTransactionType;
use App\Services\CostTrackingBalanceService;
use Illuminate\Database\Seeder;

class CostTrackingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (CostTrackingEntry::query()->exists()) {
            return;
        }

        $transport = CostCenter::query()->firstOrCreate(['name' => 'Transport']);
        $operations = CostCenter::query()->firstOrCreate(['name' => 'Frais journaliers']);

        $fuel = CostTransactionType::query()->firstOrCreate(['name' => 'Achat carburant']);
        $toll = CostTransactionType::query()->firstOrCreate(['name' => 'Péage route']);
        $payment = CostTransactionType::query()->firstOrCreate(['name' => 'Paiement']);
        $deposit = CostTransactionType::query()->firstOrCreate(['name' => 'Dépôt portefeuille']);

        $entries = [
            [
                'occurred_on' => now()->subDays(12)->toDateString(),
                'direction' => CostTrackingEntry::DIRECTION_ENTRY,
                'cost_center_id' => $transport->id,
                'cost_transaction_type_id' => $deposit->id,
                'amount' => '500000.00',
                'description' => 'Dépôt initial portefeuille transport',
            ],
            [
                'occurred_on' => now()->subDays(10)->toDateString(),
                'direction' => CostTrackingEntry::DIRECTION_EXIT,
                'cost_center_id' => $transport->id,
                'cost_transaction_type_id' => $fuel->id,
                'amount' => '85000.00',
                'description' => 'Carburant motos livraison',
            ],
            [
                'occurred_on' => now()->subDays(8)->toDateString(),
                'direction' => CostTrackingEntry::DIRECTION_EXIT,
                'cost_center_id' => $transport->id,
                'cost_transaction_type_id' => $toll->id,
                'amount' => '12000.00',
                'description' => 'Péage route Bunia - Kasenyi',
            ],
            [
                'occurred_on' => now()->subDays(5)->toDateString(),
                'direction' => CostTrackingEntry::DIRECTION_ENTRY,
                'cost_center_id' => $operations->id,
                'cost_transaction_type_id' => $payment->id,
                'amount' => '144000.00',
                'description' => 'Paiement frais journaliers équipe',
            ],
            [
                'occurred_on' => now()->subDays(3)->toDateString(),
                'direction' => CostTrackingEntry::DIRECTION_EXIT,
                'cost_center_id' => $operations->id,
                'cost_transaction_type_id' => $fuel->id,
                'amount' => '45000.00',
                'description' => 'Achat carburant générateur',
            ],
            [
                'occurred_on' => now()->subDay()->toDateString(),
                'direction' => CostTrackingEntry::DIRECTION_EXIT,
                'cost_center_id' => $transport->id,
                'cost_transaction_type_id' => $toll->id,
                'amount' => '8000.00',
                'description' => 'Péage retour entrepôt',
            ],
        ];

        foreach ($entries as $entry) {
            CostTrackingEntry::query()->create($entry + ['balance_after' => '0.00']);
        }

        app(CostTrackingBalanceService::class)->recalculateAll();
    }
}
