<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use Illuminate\View\View;

class SaleEntryController extends Controller
{
    use RespectsUserBranch;

    public function create(): View
    {
        abort_unless(auth()->user()?->canAccessPosSales(), 403, 'Vous n’avez pas accès à la caisse.');

        $branches = $this->branchesForUser();
        if ($branches->isEmpty()) {
            abort(403, 'Aucune branche accessible pour enregistrer une vente.');
        }

        $terminals = $this->posTerminalsForUser(null);
        $openTerminalIds = $this->openPosTerminalIds($terminals);
        $openIds = array_flip($openTerminalIds);

        return view('sale_entry.choose-pos', compact('terminals', 'openIds'));
    }
}
