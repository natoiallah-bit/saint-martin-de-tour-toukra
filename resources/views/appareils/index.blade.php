@extends('layouts.app')
@section('title', 'Appareils connectés')

@section('content')
    <h1>Appareils connectés</h1>

    <p style="max-width:820px; color:#5a5a5a;">
        Téléphones et ordinateurs où l'application est connectée pour travailler hors ligne
        (appel, notes, encaissements). Si un téléphone est perdu ou si une personne quitte l'école,
        déconnectez son appareil : il devra se reconnecter avec un e-mail et un mot de passe.
    </p>

    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Appareil</th>
                    <th>Compte</th>
                    <th>Dernière synchronisation</th>
                    <th>Saisies reçues</th>
                    <th>État</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($appareils as $appareil)
                    <tr>
                        <td>{{ $appareil->nom }}</td>
                        <td>{{ $appareil->user?->name }} <span style="color:#777;">{{ $appareil->user?->email }}</span></td>
                        <td>{{ $appareil->derniere_synchro?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $envois[$appareil->id] ?? 0 }}</td>
                        <td>{{ $appareil->jeton_hash ? 'Connecté' : 'Déconnecté' }}</td>
                        <td>
                            @if($appareil->jeton_hash)
                                <form action="{{ route('appareils.deconnecter', $appareil) }}" method="POST"
                                      onsubmit="return confirm('Déconnecter cet appareil ? Les saisies pas encore envoyées resteront sur le téléphone jusqu\'à la reconnexion.');">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Déconnecter</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center; color:#777;">Aucun appareil connecté pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
