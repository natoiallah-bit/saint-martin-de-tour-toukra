@extends('layouts.app')
@section('title', 'Sauvegarde')

@section('content')
    <h1>Sauvegarde des données</h1>

    <div style="background:#fff; padding:20px; border-radius:10px; max-width:720px;">
        <p>Téléchargez une copie complète de l'école : la base de données (élèves, notes, paiements, personnel), les photos et documents, et les images de l'école (bannière, emblème).</p>
        <p style="margin-top:10px;"><b>Faites-le régulièrement</b> (chaque semaine, et avant la fin de chaque trimestre), puis gardez le fichier sur un autre appareil : clé USB, ordinateur de la Direction. Une sauvegarde qui reste sur le même serveur ne protège pas d'une panne de ce serveur.</p>
        <form action="{{ route('sauvegarde.telecharger') }}" method="POST" style="margin-top:16px;">
            @csrf
            <button type="submit" class="btn">Télécharger la sauvegarde (ZIP)</button>
        </form>
        <p style="font-size:13px; color:#5a5a5a; margin-top:14px;">La préparation peut durer une minute ou plus s'il y a beaucoup de photos. Cette page reste disponible même si la licence est terminée. Pour restaurer : voir « Sauvegarde et restauration » dans INSTALLATION.md.</p>
    </div>
@endsection
