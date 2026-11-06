<?php

namespace App\Http\Controllers;

use App\Models\JournalFinancier;
use Illuminate\Http\Request;

// Journal des corrections et suppressions financières : contrôle par la Direction
class JournalFinancierController extends Controller
{
    public function index(Request $request)
    {
        $journal = JournalFinancier::with(['auteur', 'cycle'])
            ->when($request->filled('objet'), fn ($q) => $q->where('objet', $request->objet))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('du'), fn ($q) => $q->whereDate('created_at', '>=', $request->du))
            ->when($request->filled('au'), fn ($q) => $q->whereDate('created_at', '<=', $request->au))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('journal-financier.index', ['journal' => $journal]);
    }
}
