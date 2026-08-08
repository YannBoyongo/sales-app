<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespectsUserBranch;
use App\Models\PosTerminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PointDeVenteEntryController extends Controller
{
    use RespectsUserBranch;

    public function create(): View|RedirectResponse
    {
        abort_unless(auth()->user()?->canAccessPosSales(), 403, 'Vous n’avez pas accès au point de vente.');

        if ($redirect = $this->ensureFieldPosStockConfigured()) {
            return $redirect;
        }

        $terminals = $this->posTerminalsForUser(null, false, PosTerminal::KIND_FIELD);
        if ($terminals->isEmpty()) {
            return view('point_de_vente.choose-pos', [
                'terminals' => $terminals,
                'isAdmin' => (bool) auth()->user()?->isAdmin(),
                'stockLocation' => $this->fieldPosStockLocation(),
            ]);
        }

        if ($terminals->count() === 1) {
            $terminal = $terminals->first()->load('branch');

            return redirect()->route('point-de-vente.workspace', [$terminal->branch, $terminal]);
        }

        $stockLocation = $this->fieldPosStockLocation();

        return view('point_de_vente.choose-pos', [
            'terminals' => $terminals->load('branch'),
            'openIds' => array_flip($this->openPosTerminalIds($terminals)),
            'isAdmin' => (bool) auth()->user()?->isAdmin(),
            'stockLocation' => $stockLocation,
        ]);
    }
}
