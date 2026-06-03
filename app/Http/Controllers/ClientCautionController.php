<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\View\View;

class ClientCautionController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->canViewClientsLedger(), 403);

        $clients = Client::query()
            ->withSum('cautionDeposits as caution_total', 'amount')
            ->withSum('cautionUsages as caution_used', 'amount')
            ->where(function ($query) {
                $query->whereHas('cautionDeposits')
                    ->orWhereHas('cautionUsages');
            })
            ->orderBy('name')
            ->paginate(20);

        return view('caution.index', compact('clients'));
    }
}
