<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        $setting = Setting::query()->firstOrCreate(
            ['id' => 1],
            [
                'shopname' => config('app.name', 'Sales App'),
                'phone' => '',
                'email' => '',
                'address' => '',
                'rccm' => '',
                'idnat' => '',
                'nif' => '',
            ]
        );

        return view('settings.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = Setting::query()->firstOrCreate(
            ['id' => 1],
            [
                'shopname' => config('app.name', 'Sales App'),
                'phone' => '',
                'email' => '',
                'address' => '',
                'rccm' => '',
                'idnat' => '',
                'nif' => '',
            ]
        );

        $data = $request->validate([
            'shopname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'rccm' => ['required', 'string', 'max:100'],
            'idnat' => ['required', 'string', 'max:100'],
            'nif' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($setting->logo) {
                Storage::disk('public')->delete($setting->logo);
            }
            $data['logo'] = $request->file('logo')->store('settings', 'public');
        } else {
            unset($data['logo']);
        }

        $setting->update($data);

        return redirect()->route('parametre.edit')->with('success', 'Paramètre mis à jour.');
    }
}
