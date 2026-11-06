// Adresse du serveur de l'école : saisie une fois, gardée sur l'appareil.
(function (racine) {
  // « 192.168.1.20/saint-martin-toukra/public » → « http://192.168.1.20/saint-martin-toukra/public »
  function normaliserAdresse(saisie) {
    var texte = String(saisie || '').trim();
    if (!texte) return null;
    if (!/^https?:\/\//i.test(texte)) texte = 'http://' + texte;
    try {
      var url = new URL(texte);
      if (!url.hostname) return null;
      var chemin = url.pathname.replace(/\/+$/, '');
      return url.protocol + '//' + url.host + chemin;
    } catch (e) {
      return null;
    }
  }

  var CLE = 'adresseServeur';
  function lireAdresse() {
    try { return localStorage.getItem(CLE); } catch (e) { return null; }
  }
  function enregistrerAdresse(adresse) {
    try { localStorage.setItem(CLE, adresse); } catch (e) { /* stockage indisponible */ }
  }

  var api = { normaliserAdresse: normaliserAdresse, lireAdresse: lireAdresse, enregistrerAdresse: enregistrerAdresse };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else racine.AdresseServeur = api;
})(this);
