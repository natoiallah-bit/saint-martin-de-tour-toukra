<style>
    .cours-grille{ display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:14px; }
    .cours-carte{ background:#fff; border:1px solid #e3ddcf; border-radius:10px; padding:16px; text-decoration:none; color:inherit; display:block; }
    .cours-carte:hover{ border-color:#c9973b; box-shadow:0 4px 14px rgba(0,0,0,0.06); }
    .cours-carte b{ font-size:17px; color:#141c33; }
    .compteurs{ display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; font-size:12.5px; color:#5a5a5a; }
    .compteurs span{ background:#f3efe6; border-radius:20px; padding:3px 10px; }
    .bloc{ background:#fff; border:1px solid #e3ddcf; border-radius:10px; padding:16px 18px; margin-bottom:18px; }
    .bloc-titre{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
    .bloc-titre h3{ margin:0; }
    .element{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; padding:10px 0; border-top:1px solid #efe9dc; }
    .element:first-of-type{ border-top:0; }
    .element .infos small{ color:#6b6b6b; display:block; margin-top:2px; }
    .pastille{ display:inline-block; font-size:11px; font-weight:700; border-radius:20px; padding:2px 9px; background:#e8eaf0; color:#243365; }
    .pastille.vert{ background:#e2ede6; color:#3f6b55; } .pastille.rouge{ background:#fbe6df; color:#a8503c; } .pastille.or{ background:#fbf1dc; color:#8a6a2a; }
    .vide{ color:#888; font-size:13px; padding:6px 0; }
    .erreurs{ background:#fbe9e7; color:#a33; padding:10px 14px; border-radius:6px; margin-bottom:14px; }
    .formulaire{ background:#fff; padding:20px; border-radius:10px; max-width:820px; }
    .formulaire textarea{ width:100%; padding:8px; border:1px solid #e3ddcf; border-radius:6px; margin-top:4px; font-family:inherit; font-size:14px; }
    .aide{ font-size:12px; color:#6b6b6b; margin-top:4px; }
    .pieces a{ display:inline-flex; align-items:center; gap:6px; margin:4px 10px 4px 0; }
</style>
