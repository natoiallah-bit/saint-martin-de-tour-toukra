@extends('layouts.app')
@section('title', 'Vérifier un document')

@section('content')
    <h1>Vérifier un document</h1>

    <p style="max-width:640px; color:#5a5a5a;">
        Scannez le QR code d'un bulletin, d'une carte scolaire, d'un reçu ou d'une fiche de paie
        avec le lecteur de QR codes : le lien s'inscrit tout seul dans le champ ci-dessous.
        Vous pouvez aussi coller le lien lu par un téléphone.
        La vérification fonctionne quelle que soit l'adresse ou le réseau du document imprimé.
    </p>

    @if($errors->any())
        <div style="background:#f8e1df; color:#9b2c22; padding:12px 16px; border-radius:6px; margin:12px 0; max-width:640px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('verification.document.verifier') }}" method="POST" style="max-width:640px;">
        @csrf
        <label>Contenu du QR code</label>
        <input type="text" name="code" value="{{ old('code') }}" autofocus autocomplete="off" required
               placeholder="http://.../verification/...">
        <div style="margin-top:16px;">
            <button type="submit" class="btn">Vérifier</button>
        </div>
    </form>
@endsection
