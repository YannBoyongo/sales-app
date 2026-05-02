<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Client;
use App\Models\Payment;
use App\Services\AccountingJournal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $clients = Client::query()
            ->withSum(['creditSales as total_credit_amount' => fn ($q) => $q], 'line_total')
            ->withSum('payments', 'amount')
            ->orderBy('name')
            ->paginate(20);

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:clients,name'],
        ]);

        $client = Client::create($data);

        return redirect()->route('clients.show', $client)->with('success', 'Client créé.');
    }

    public function show(Client $client): View
    {
        $ids = $this->branchFilterIds();
        if ($ids !== null) {
            $accessible = $client->creditSales()
                ->whereHas('salesSession', fn ($q) => $q->whereIn('branch_id', $ids))
                ->exists();
            abort_unless($accessible, 403, 'Accès non autorisé pour ce client.');
        }

        $client->load([
            'creditSales' => fn ($q) => $q->latest()->with(['salesSession.branch', 'product']),
            'payments' => fn ($q) => $q->latest()->with('user'),
        ]);

        $totalCredit = $client->totalCreditAmount();
        $totalPaid = $client->totalPaidAmount();
        $balance = $client->debtBalance();

        return view('clients.show', compact('client', 'totalCredit', 'totalPaid', 'balance'));
    }

    public function storePayment(Request $request, Client $client): RedirectResponse
    {
        $ids = $this->branchFilterIds();
        if ($ids !== null) {
            $accessible = $client->creditSales()
                ->whereHas('salesSession', fn ($q) => $q->whereIn('branch_id', $ids))
                ->exists();
            abort_unless($accessible, 403, 'Accès non autorisé pour ce client.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $currentDebt = (float) $client->debtBalance();
        $amount = (float) $data['amount'];

        if ($amount > $currentDebt) {
            return back()->withInput()->withErrors([
                'amount' => 'Le montant dépasse la dette actuelle du client.',
            ]);
        }

        DB::transaction(function () use ($request, $client, $amount, $data) {
            $payment = Payment::create([
                'client_id' => $client->id,
                'user_id' => $request->user()->id,
                'amount' => number_format($amount, 2, '.', ''),
                'paid_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            AccountingJournal::recordClientPayment($payment, $client, $request->user());
        });

        return redirect()->route('clients.show', $client)->with('success', 'Paiement enregistré.');
    }
}
