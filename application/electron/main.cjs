// Application Windows : ouvre la gestion scolaire de l'école dans une fenêtre.
// Au premier lancement, l'écran www/index.html demande l'adresse du serveur
// (ordinateur de l'école ou hébergement) ; elle est gardée pour les fois suivantes.
const { app, BrowserWindow, Menu, shell } = require('electron');
const path = require('path');

const pageLocale = (nom) => ({ fichier: path.join(__dirname, '..', 'www', nom) });

function ouvrirPage(fenetre, nom, query) {
  const { fichier } = pageLocale(nom);
  fenetre.loadFile(fichier, query ? { query } : undefined);
}

function origine(url) {
  try { return new URL(url).origin; } catch { return null; }
}

function creerFenetre() {
  const fenetre = new BrowserWindow({
    width: 1280,
    height: 820,
    minWidth: 360,
    title: 'Saint Martin de Tour',
    icon: path.join(__dirname, '..', 'www', 'icone.png'),
    autoHideMenuBar: true,
    webPreferences: { contextIsolation: true, nodeIntegration: false, sandbox: true },
  });

  // Menu caché (touche Alt pour l'afficher) : imprimer, actualiser, changer de serveur
  Menu.setApplicationMenu(Menu.buildFromTemplate([
    {
      label: 'Application',
      submenu: [
        { label: 'Imprimer', accelerator: 'CmdOrCtrl+P', click: () => fenetre.webContents.print() },
        { label: 'Actualiser', accelerator: 'F5', click: () => fenetre.webContents.reload() },
        { label: 'Page précédente', accelerator: 'Alt+Left', click: () => fenetre.webContents.navigationHistory.goBack() },
        { type: 'separator' },
        { label: 'Travailler hors ligne', click: () => ouvrirPage(fenetre, 'hors-ligne/index.html') },
        { label: "Changer l'adresse du serveur", click: () => ouvrirPage(fenetre, 'index.html', { changer: '1' }) },
        { type: 'separator' },
        { label: 'Zoom +', accelerator: 'CmdOrCtrl+=', role: 'zoomIn' },
        { label: 'Zoom −', accelerator: 'CmdOrCtrl+-', role: 'zoomOut' },
        { label: 'Taille normale', accelerator: 'CmdOrCtrl+0', role: 'resetZoom' },
        { type: 'separator' },
        { label: 'Quitter', role: 'quit' },
      ],
    },
  ]));

  ouvrirPage(fenetre, 'index.html');

  // Serveur injoignable (Wi-Fi coupé, ordinateur de l'école éteint…) : page d'aide
  fenetre.webContents.on('did-fail-load', (_e, code, _description, _url, principal) => {
    if (principal && code !== -3) ouvrirPage(fenetre, 'erreur.html');
  });

  // Reçus, bulletins, cartes ouverts dans un nouvel onglet : nouvelle fenêtre de l'application
  // (même session, donc toujours connecté) ; un site extérieur s'ouvre dans le navigateur.
  fenetre.webContents.setWindowOpenHandler(({ url }) => {
    if (origine(url) && origine(url) === origine(fenetre.webContents.getURL())) {
      return { action: 'allow', overrideBrowserWindowOptions: { autoHideMenuBar: true, icon: path.join(__dirname, '..', 'www', 'icone.png') } };
    }
    if (/^https?:/.test(url)) shell.openExternal(url);
    return { action: 'deny' };
  });
}

if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    const [fenetre] = BrowserWindow.getAllWindows();
    if (fenetre) { if (fenetre.isMinimized()) fenetre.restore(); fenetre.focus(); }
  });
  app.whenReady().then(creerFenetre);
  app.on('window-all-closed', () => app.quit());
}
