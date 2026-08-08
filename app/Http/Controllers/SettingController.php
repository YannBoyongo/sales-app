<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Location;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        $setting = Setting::current();
        $setting->load(['fieldPosStockBranch:id,name', 'fieldPosStockLocation:id,name,kind,branch_id']);

        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
        $locationsByBranch = Location::query()
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'kind'])
            ->groupBy('branch_id')
            ->map(fn ($locations) => $locations->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'kind' => Location::kindLabel($location->kind),
            ])->values());

        return view('settings.edit', compact('setting', 'branches', 'locationsByBranch'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = Setting::current();

        $data = $request->validate([
            'shopname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'rccm' => ['required', 'string', 'max:100'],
            'idnat' => ['required', 'string', 'max:100'],
            'nif' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'field_pos_stock_branch_id' => ['nullable', 'integer', 'exists:branches,id', 'required_with:field_pos_stock_location_id'],
            'field_pos_stock_location_id' => [
                'nullable',
                'integer',
                'required_with:field_pos_stock_branch_id',
                Rule::exists('locations', 'id')->where(
                    fn ($q) => $q->where('branch_id', $request->integer('field_pos_stock_branch_id'))
                ),
            ],
        ]);

        if ($request->hasFile('logo')) {
            if ($setting->logo) {
                Storage::disk('public')->delete($setting->logo);
            }
            $data['logo'] = $request->file('logo')->store('settings', 'public');
        } else {
            unset($data['logo']);
        }

        $data['field_pos_stock_branch_id'] = filled($data['field_pos_stock_branch_id'] ?? null)
            ? (int) $data['field_pos_stock_branch_id']
            : null;
        $data['field_pos_stock_location_id'] = filled($data['field_pos_stock_location_id'] ?? null)
            ? (int) $data['field_pos_stock_location_id']
            : null;

        $setting->update($data);

        return redirect()->route('parametre.edit')->with('success', 'Paramètre mis à jour.');
    }

    public function destroyLogo(): RedirectResponse
    {
        $setting = Setting::query()->firstOrFail();

        if ($setting->logo) {
            Storage::disk('public')->delete($setting->logo);
            $setting->update(['logo' => null]);
        }

        return redirect()
            ->route('parametre.edit')
            ->with('success', 'Logo supprimé.');
    }
}
