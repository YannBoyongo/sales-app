<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChartOfAccountController extends Controller
{
    public function index(): View
    {
        $accounts = ChartOfAccount::query()
            ->orderBy('account_code')
            ->paginate(30);

        return view('chart_of_accounts.index', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_code' => ['required', 'string', 'max:30', 'unique:chart_of_accounts,account_code'],
            'name' => ['required', 'string', 'max:150'],
            'account_type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ChartOfAccount::query()->create([
            'account_code' => $data['account_code'],
            'name' => $data['name'],
            'account_type' => $data['account_type'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('chart-of-accounts.index')
            ->with('success', 'Compte comptable ajouté.');
    }
}
