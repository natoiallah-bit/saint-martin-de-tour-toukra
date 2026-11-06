<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AuthController extends Controller
{
    // Page d'accueil du compte connecté : la première page qu'il a le droit d'ouvrir
    protected function pageAccueil(): string
    {
        $route = Auth::user()->pageAccueil() ?? 'dashboard';

        return Route::has($route) ? route($route) : url('/');
    }

    public function loginForm()
    {
        if (Auth::check()) {
            return redirect($this->pageAccueil());
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:150',
            'password' => 'required',
        ]);

        $email = trim($request->input('email'));

        if (Auth::attempt(['email' => $email, 'password' => $request->input('password')], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended($this->pageAccueil());
        }

        return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Vous êtes déconnecté. À bientôt !');
    }
}
