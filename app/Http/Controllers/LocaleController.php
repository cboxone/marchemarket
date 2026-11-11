<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale)
    {
        abort_unless(in_array($locale, SetLocale::AVAILABLE, true), 404);

        $request->session()->put('locale', $locale);

        return back();
    }
}
