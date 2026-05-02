<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesSession;
use App\Models\SessionExpense;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\AccountingJournal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class SalesSessionController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        $query = SalesSession::query()
            ->select([
                'id',
                'branch_id',
                'opened_by',
                'closed_by',
                'status',
                'opened_at',
                'closed_at',
                'closure_bank_reference',
            ])
            ->with([
                'branch:id,name',
                'opener:id,name',
                'closer:id,name',
            ])
            ->withSum('saleItems', 'line_total')
            ->withSum(['sales as session_cash_total' => fn ($q) => $q->where('payment_type', 'cash')], 'total_amount')
            ->withSum(['sales as session_credit_total' => fn ($q) => $q->where('payment_type', 'credit')], 'total_amount')
            ->withSum(['expenses as session_expenses_total' => fn ($q) => $q], 'amount')
            ->latest('id');

        $this->applyBranchFilter($query);

        $sessions = $query->simplePaginate(20);

        return view('sales_sessions.index', compact('sessions'));
    }

    public function create(): View
    {
        $user = auth()->user()?->fresh();
        abort_if($user === null, 403);

        if (! $user->is_admin) {
            abort_unless($user->branch_id, 403, 'Aucune branche n’est assignée à votre compte.');

            $userBranch = Branch::query()->whereKey($user->branch_id)->firstOrFail();

            return view('sales_sessions.create', compact('userBranch'));
        }

        $branches = Branch::query()->orderBy('name')->get();

        return view('sales_sessions.create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->fresh();

        if ($user->is_admin) {
            $data = $request->validate([
                'branch_id' => ['required', 'exists:branches,id'],
            ]);
            $branchId = (int) $data['branch_id'];
        } else {
            abort_unless($user->branch_id, 403, 'Aucune branche n’est assignée à votre compte.');
            $branchId = (int) $user->branch_id;
            abort_unless($branchId > 0, 403, 'Branche invalide pour votre compte.');
        }

        $branchName = Branch::query()->whereKey($branchId)->value('name');

        $openExists = SalesSession::query()
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->exists();

        if ($openExists) {
            $label = $branchName ? "« {$branchName} » (n°{$branchId})" : "n°{$branchId}";

            return back()->withInput()->withErrors([
                'session' => "Une session ouverte existe déjà pour la branche {$label}. Si vous venez de changer d’utilisateur ou de branche, déconnectez-vous puis reconnectez-vous pour actualiser votre profil.",
            ]);
        }

        $sessionTodayExists = SalesSession::query()
            ->where('branch_id', $branchId)
            ->whereDate('opened_at', now()->toDateString())
            ->exists();

        if ($sessionTodayExists) {
            $label = $branchName ? "« {$branchName} » (n°{$branchId})" : "n°{$branchId}";

            return back()->withInput()->withErrors([
                'session' => "Une session existe déjà pour aujourd’hui pour la branche {$label}. Un administrateur peut rouvrir la session clôturée si besoin.",
            ]);
        }

        SalesSession::create([
            'branch_id' => $branchId,
            'opened_by' => $request->user()->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return redirect()->route('sales-sessions.index')->with('success', 'Session journalière ouverte.');
    }

    public function show(SalesSession $salesSession): View
    {
        $this->ensureUserCanAccessSalesSession($salesSession);

        $salesSession->load([
            'branch',
            'opener',
            'closer',
            'expenses' => fn ($q) => $q->with('user:id,name')->orderByDesc('spent_at')->orderByDesc('id'),
        ]);

        $salesQuery = Sale::query()
            ->where('sales_session_id', $salesSession->id)
            ->with('client:id,name')
            ->select([
                'id',
                'reference',
                'sales_session_id',
                'payment_type',
                'client_id',
                'total_amount',
                'subtotal_amount',
                'sale_status',
                'discount_requested_amount',
                'sold_at',
            ])
            ->latest('id');

        $sales = $salesQuery->simplePaginate(30)->withQueryString();

        $computedTotal = $salesSession->computedTotal();
        $cashTotal = $salesSession->cashSalesTotal();
        $creditTotal = $salesSession->creditSalesTotal();
        $expensesTotal = $salesSession->expensesTotal();
        $netTotal = $salesSession->netCashTotal();

        return view('sales_sessions.show', compact('salesSession', 'computedTotal', 'cashTotal', 'creditTotal', 'expensesTotal', 'netTotal', 'sales'));
    }

    public function closure(SalesSession $salesSession): View|RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);

        if (! $salesSession->isOpen()) {
            return redirect()->route('sales-sessions.closure-recap', $salesSession);
        }

        if ($salesSession->hasPendingDiscountSales()) {
            return redirect()
                ->route('sales-sessions.show', $salesSession)
                ->withErrors([
                    'close' => 'Impossible de clôturer la session : une ou plusieurs ventes ont une remise en attente d’approbation. Traitez-les ou refusez la remise avant la clôture.',
                ]);
        }

        return view('sales_sessions.closure', $this->prepareClosureRecapData($salesSession));
    }

    /**
     * Read-only recap of a closed session (same figures as the pre-close screen, without the form).
     */
    public function closureRecap(SalesSession $salesSession): View|RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);

        if ($salesSession->isOpen()) {
            return redirect()->route('sales-sessions.closure', $salesSession);
        }

        return view('sales_sessions.closure-recap', $this->prepareClosureRecapData($salesSession));
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareClosureRecapData(SalesSession $salesSession): array
    {
        $salesSession->load(['branch', 'opener', 'closer', 'saleItems.product.department', 'expenses.user']);

        $computedTotal = $salesSession->computedTotal();
        $cashTotal = $salesSession->cashSalesTotal();
        $creditTotal = $salesSession->creditSalesTotal();
        $expensesTotal = $salesSession->expensesTotal();
        $netTotal = $salesSession->netCashTotal();

        $departmentBreakdown = $salesSession->saleItems
            ->groupBy(fn (SaleItem $item) => $item->product->department_id)
            ->map(function ($items) {
                /** @var Collection<int, SaleItem> $items */
                $first = $items->first();
                $department = $first?->product?->department;

                return [
                    'name' => $department?->name ?? '—',
                    'quantity' => (int) $items->sum('quantity'),
                    'total' => (string) $items->sum('line_total'),
                ];
            })
            ->values()
            ->sortBy('name');

        return compact(
            'salesSession',
            'computedTotal',
            'cashTotal',
            'creditTotal',
            'expensesTotal',
            'netTotal',
            'departmentBreakdown',
        );
    }

    public function close(Request $request, SalesSession $salesSession): RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);

        if (! $salesSession->isOpen()) {
            return redirect()->route('sales-sessions.show', $salesSession)->withErrors([
                'close' => 'Cette session est déjà clôturée.',
            ]);
        }

        if ($salesSession->hasPendingDiscountSales()) {
            return redirect()
                ->route('sales-sessions.show', $salesSession)
                ->withErrors([
                    'close' => 'Impossible de clôturer la session : une ou plusieurs ventes ont une remise en attente d’approbation.',
                ]);
        }

        $data = $request->validate([
            'closure_total_amount' => ['required', 'numeric'],
            'closure_bank_reference' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $salesSession, $data) {
            $salesSession->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
                'closure_total_amount' => $data['closure_total_amount'],
                'closure_bank_reference' => $data['closure_bank_reference'],
            ]);

            $salesSession->refresh();
            AccountingJournal::recordSessionClosure($salesSession, $request->user());
        });

        return redirect()
            ->route('sales-sessions.closure-recap', $salesSession)
            ->with('success', 'Session clôturée. Montant et justificatif enregistrés.');
    }

    public function storeExpense(Request $request, SalesSession $salesSession): RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);
        abort_unless($salesSession->isOpen(), 403, 'Impossible d’ajouter une dépense sur une session clôturée.');

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'spent_at' => ['nullable', 'date'],
        ]);

        SessionExpense::create([
            'sales_session_id' => $salesSession->id,
            'user_id' => $request->user()->id,
            'label' => $data['label'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'spent_at' => $data['spent_at'] ?? now(),
        ]);

        return redirect()
            ->route('sales-sessions.show', $salesSession)
            ->with('success', 'Dépense de session enregistrée.');
    }

    public function destroyExpense(Request $request, SalesSession $salesSession, SessionExpense $expense): RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);
        abort_unless((int) $expense->sales_session_id === (int) $salesSession->id, 404);
        abort_unless($salesSession->isOpen(), 403, 'Impossible de supprimer une dépense d’une session clôturée.');

        $expense->delete();

        return redirect()
            ->route('sales-sessions.show', $salesSession)
            ->with('success', 'Dépense supprimée.');
    }

    public function reopen(Request $request, SalesSession $salesSession): RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);

        if ($salesSession->isOpen()) {
            return redirect()->route('sales-sessions.show', $salesSession)->withErrors([
                'reopen' => 'Cette session est déjà ouverte.',
            ]);
        }

        $otherOpenExists = SalesSession::query()
            ->where('branch_id', $salesSession->branch_id)
            ->where('status', 'open')
            ->whereKeyNot($salesSession->getKey())
            ->exists();

        if ($otherOpenExists) {
            return redirect()->route('sales-sessions.show', $salesSession)->withErrors([
                'reopen' => 'Une autre session ouverte existe déjà pour cette branche.',
            ]);
        }

        $salesSession->update([
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
            'closure_total_amount' => null,
            'closure_bank_reference' => null,
        ]);

        return redirect()->route('sales-sessions.show', $salesSession)->with('success', 'Session rouverte.');
    }

    public function destroy(Request $request, SalesSession $salesSession): RedirectResponse
    {
        $this->ensureUserCanAccessSalesSession($salesSession);

        try {
            DB::transaction(function () use ($salesSession) {
                $saleItems = SaleItem::query()
                    ->where('sales_session_id', $salesSession->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $itemIds = $saleItems->pluck('id');

                StockMovement::query()
                    ->where(function ($q) use ($salesSession, $itemIds) {
                        $q->where('sales_session_id', $salesSession->id);
                        if ($itemIds->isNotEmpty()) {
                            $q->orWhereIn('sale_item_id', $itemIds);
                        }
                    })
                    ->delete();

                foreach ($saleItems as $item) {
                    Stock::modifyQuantity((int) $item->product_id, (int) $item->location_id, (int) $item->quantity);
                }

                $salesSession->delete();
            });
        } catch (RuntimeException $e) {
            return redirect()->route('sales-sessions.show', $salesSession)->withErrors([
                'delete' => $e->getMessage(),
            ]);
        }

        return redirect()->route('sales-sessions.index')->with('success', 'Session supprimée. Le stock a été réajusté pour les ventes annulées.');
    }
}
