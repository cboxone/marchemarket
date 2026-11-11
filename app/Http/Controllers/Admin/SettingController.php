<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'required',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $type = $setting->type;
                $group = $setting->group;
                $description = $setting->description;

                // Convertir selon le type
                $processedValue = match ($type) {
                    'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'integer' => (int) $value,
                    'float' => (float) $value,
                    'json' => is_array($value) ? json_encode($value) : $value,
                    default => $value,
                };

                Setting::set($key, $processedValue, $type, $group, $description);
            }
        }

        Setting::clearCache();

        return back()->with('status', 'Paramètres mis à jour avec succès.');
    }
}
