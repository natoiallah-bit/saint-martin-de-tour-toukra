@extends('layouts.app')
@section('title', 'Convocations - Réunion')

@section('content')
<style>
  .lettre{
    max-width:680px; margin:0 auto 20px; background:#faf8f3; padding:34px 40px;
    border-radius:6px; box-shadow:0 4px 24px rgba(0,0,0,0.15);
  }
  .lettre + .lettre{ page-break-before:always; }
  .entete{ text-align:center; font-size:11px; font-weight:bold; margin-bottom:14px; line-height:1.5; }
  .devise{ font-style:italic; font-weight:500; }
  .logo-ecole{ width:100%; height:auto; display:block; margin-bottom:14px; }
  .bandeau{ text-align:center; font-family:'Fraunces', serif; font-weight:700; font-size:16px; color:#141c33; border-top:2px solid #141c33; border-bottom:2px solid #141c33; padding:8px 0; margin-bottom:18px; }
  .objet{ text-align:center; background:#f6ead0; border:1px solid #c9973b; color:#141c33; font-weight:bold; padding:8px; margin-bottom:18px; }
  .corps{ font-size:13.5px; line-height:1.8; }
  .details{ background:#fff; border:1px solid #e3ddcf; border-radius:8px; padding:14px 18px; margin:16px 0; font-size:13.5px; }
  .details div{ padding:3px 0; }
  .signature{ display:flex; justify-content:space-between; margin-top:34px; font-size:12px; }
  @media print { .btn, h1 { display:none; } }
</style>

<h1>Convocations générées ({{ $eleves->count() }})</h1>
<button onclick="window.print()" class="btn" style="margin-bottom:16px;">🖨️ Imprimer toutes les convocations</button>

@foreach($eleves as $eleve)
    <div class="lettre">
        <div class="entete">
            RÉPUBLIQUE DU TCHAD<br>
            <span class="devise">Unité – Travail – Progrès</span>
        </div>

        @if(\App\Support\Etablissement::banniere())
            <img src="{{ \App\Support\Etablissement::banniere() }}" alt="{{ \App\Support\Etablissement::nom() }}" class="logo-ecole">
        @else
            <div class="bandeau">{{ \App\Support\Etablissement::nom() }}</div>
        @endif

        <div class="objet">CONVOCATION — {{ strtoupper($sujet) }}</div>

        <div class="corps">
            <p>À l'attention de : <b>{{ $eleve->tuteur_nom ?? $eleve->pere_nom ?? 'Parent / Tuteur' }}</b></p>
            <p>Concerne l'élève : <b>{{ $eleve->nom }} {{ $eleve->prenom }}</b>, classe de <b>{{ $eleve->classe->nom ?? '—' }}</b></p>

            <p style="margin-top:14px;">
                Vous êtes prié(e) de bien vouloir vous présenter au complexe scolaire selon les
                modalités suivantes :
            </p>

            <div class="details">
                <div><b>Objet :</b> {{ $sujet }}</div>
                <div><b>Date :</b> {{ \Carbon\Carbon::parse($date_convocation)->format('d/m/Y') }}</div>
                <div><b>Heure :</b> {{ $heure }}</div>
                <div><b>Lieu :</b> {{ $lieu }}</div>
                @if($message)
                    <div><b>Précisions :</b> {{ $message }}</div>
                @endif
            </div>

            <p>Votre présence est vivement souhaitée. Nous vous remercions par avance de votre disponibilité.</p>
        </div>

        <div class="signature">
            <div>Fait à N'Djamena, le {{ now()->format('d/m/Y') }}</div>
            <div style="text-align:center;">
                <div style="margin-top:26px; border-top:1px solid #20222b; padding-top:4px; font-weight:bold;">Le Directeur du Complexe</div>
            </div>
        </div>
    </div>
@endforeach
@endsection