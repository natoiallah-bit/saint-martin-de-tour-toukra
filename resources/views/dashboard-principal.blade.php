@extends('layouts.app')
@section('title', 'Espace Principal')

@section('content')
    <h1>Espace pédagogique — Principal</h1>

@if(auth()->user()->peut('rapport_pedagogique') || auth()->user()->peut('rapport_financier'))
    <a href="{{ route('rapports.index') }}" class="btn" style="display:inline-flex; align-items:center; gap:8px; padding:12px 20px; font-size:15px; margin:-6px 0 22px;">
        📊 Rapport trimestriel / annuel
    </a>
@endif

    <div style="display:flex; gap:16px; margin:18px 0 26px; flex-wrap:wrap;">
        <div style="background:#eaf2fb; border:1px solid #0d4a8f; border-radius:8px; padding:14px 22px; min-width:140px; text-align:center;">
            <div style="font-size:26px; font-weight:bold; color:#0d4a8f;">{{ $totalClasses }}</div>
            <div style="color:#5a5a5a; font-size:13px;">Classes</div>
        </div>
        <div style="background:#eaf2fb; border:1px solid #0d4a8f; border-radius:8px; padding:14px 22px; min-width:140px; text-align:center;">
            <div style="font-size:26px; font-weight:bold; color:#0d4a8f;">{{ $totalEleves }}</div>
            <div style="color:#5a5a5a; font-size:13px;">Élèves</div>
        </div>
        <div style="background:#eaf2fb; border:1px solid #0d4a8f; border-radius:8px; padding:14px 22px; min-width:140px; text-align:center;">
            <div style="font-size:26px; font-weight:bold; color:#0d4a8f;">{{ $totalEnseignants }}</div>
            <div style="color:#5a5a5a; font-size:13px;">Enseignants</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px;">

        <a href="{{ route('notes.choix') }}" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #d7e3f0; border-radius:8px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:block;">
            <div style="font-size:24px;">📝</div>
            <div style="font-weight:bold; margin-top:6px; color:#0d2b4e;">Notes</div>
            <div style="color:#5a5a5a; font-size:13px; margin-top:4px;">Saisir et consulter les notes par classe et matière.</div>
        </a>

        <a href="{{ route('bulletins.choix') }}" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #d7e3f0; border-radius:8px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:block;">
            <div style="font-size:24px;">📄</div>
            <div style="font-weight:bold; margin-top:6px; color:#0d2b4e;">Bulletins</div>
            <div style="color:#5a5a5a; font-size:13px; margin-top:4px;">Consulter et imprimer les bulletins scolaires.</div>
        </a>

        <a href="{{ route('fiches-passage.choix') }}" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #d7e3f0; border-radius:8px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:block;">
            <div style="font-size:24px;">🎓</div>
            <div style="font-weight:bold; margin-top:6px; color:#0d2b4e;">Fiche de passage</div>
            <div style="color:#5a5a5a; font-size:13px; margin-top:4px;">Décisions de passage en fin d'année, par classe.</div>
        </a>

        <a href="{{ route('emplois-temps.choix') }}" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #d7e3f0; border-radius:8px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:block;">
            <div style="font-size:24px;">🗓️</div>
            <div style="font-weight:bold; margin-top:6px; color:#0d2b4e;">Emplois du temps</div>
            <div style="color:#5a5a5a; font-size:13px; margin-top:4px;">Consulter et organiser les emplois du temps par classe.</div>
        </a>

        <a href="{{ route('presences.choix') }}" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #d7e3f0; border-radius:8px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:block;">
            <div style="font-size:24px;">✅</div>
            <div style="font-weight:bold; margin-top:6px; color:#0d2b4e;">Présences</div>
            <div style="color:#5a5a5a; font-size:13px; margin-top:4px;">Cahier d'appel : présences des élèves par classe.</div>
        </a>

        <a href="{{ route('classes.index') }}" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #d7e3f0; border-radius:8px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:block;">
            <div style="font-size:24px;">🏫</div>
            <div style="font-weight:bold; margin-top:6px; color:#0d2b4e;">Classes</div>
            <div style="color:#5a5a5a; font-size:13px; margin-top:4px;">Gérer les classes et désigner les professeurs principaux.</div>
        </a>

        <a href="{{ route('matieres-gestion.index') }}" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #d7e3f0; border-radius:8px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:block;">
            <div style="font-size:24px;">📚</div>
            <div style="font-weight:bold; margin-top:6px; color:#0d2b4e;">Matières</div>
            <div style="color:#5a5a5a; font-size:13px; margin-top:4px;">Gérer les matières et leur affectation aux enseignants.</div>
        </a>

        <a href="{{ route('eleves.index') }}" style="text-decoration:none; color:inherit; background:#fff; border:1px solid #d7e3f0; border-radius:8px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:block;">
            <div style="font-size:24px;">🧑‍🎓</div>
            <div style="font-weight:bold; margin-top:6px; color:#0d2b4e;">Élèves</div>
            <div style="color:#5a5a5a; font-size:13px; margin-top:4px;">Consulter la liste des élèves et leurs dossiers.</div>
        </a>

    </div>
@endsection
