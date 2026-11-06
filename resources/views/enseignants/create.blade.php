@extends('layouts.app')
@section('title', 'Ajouter un enseignant')

@section('content')
    <h1>Ajouter un enseignant</h1>
    <form action="{{ route('enseignants.store') }}" method="POST">
        @csrf
        <label>Nom</label>
        <input type="text" name="nom" required>
        <label>Prénom</label>
        <input type="text" name="prenom" required>
        <label>Téléphone</label>
        <input type="text" name="telephone">
        <label>Email</label>
        <input type="email" name="email">
        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
            <a href="{{ route('enseignants.index') }}">Annuler</a>
        </div>
    </form>
@endsection
