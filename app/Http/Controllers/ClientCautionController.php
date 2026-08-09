<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\Client;
use Illuminate\View\View;

class ClientCautionController extends Controller
{
    use RespectsUserBranch;

    public function index(): View
    {
        abort_unless(auth()->user()?->canViewClientsLedger(), 403);

        $query = Client::query()
            ->withSum('cautionDeposits as caution_total', 'amount')
            ->withSum('cautionUsages as caution_used', 'amount')
            ->where(function ($query) {
                $query->whereHas('cautionDeposits')
                    ->orWhereHas('cautionUsages');
            });

        $this->applyBranchFilter($query);

        $clients = $query->orderBy('name')->paginate(20);

        return view('caution.index', compact('clients'));
    }
}
