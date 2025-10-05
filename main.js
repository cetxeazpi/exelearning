// main.js — Tabs Option B (renderer-managed <webview> tabs on Win/Linux, native tabs on macOS)
// Notes:
//  - macOS: native tab bar (always visible) using tabbingIdentifier + one-time toggle.
//  - Windows/Linux: the renderer owns a simple HTML tab bar; each tab is a <webview>.
//  - We forward window.open/new-tab actions to the proper target per OS.
//  - Comments are in English. Indentation uses spaces.

const { app, BrowserWindow, dialog, session, ipcMain, Menu } = require('electron');
const { autoUpdater } = require('electron-updater');
const log              = require('electron-log');
const path             = require('path');
const i18n             = require('i18n');
const { spawn, execFileSync } = require('child_process');
const fs               = require('fs');
const AdmZip           = require('adm-zip');
const http             = require('http');
const https            = require('https');

const isMac = process.platform === 'darwin';

// ───────────────── Base paths / logging ─────────────────
const basePath = app.isPackaged ? process.resourcesPath : app.getAppPath();

log.transports.file.resolvePath = () =>
  path.join(app.getPath('userData'), 'logs', 'main.log');

const origConsole = { log: console.log, error: console.error, warn: console.warn };
console.log = (...args) => { log.info(...args); origConsole.log(...args); };
console.warn = (...args) => { log.warn(...args); origConsole.warn(...args); };
console.error = (...args) => { log.error(...args); origConsole.error(...args); };

process.on('uncaughtException', (e) => log.error('uncaughtException:', e));
process.on('unhandledRejection', (e) => log.error('unhandledRejection:', e));

// ───────────────── i18n ─────────────────
const translationsDir = app.isPackaged
  ? path.join(process.resourcesPath, 'translations')
  : path.join(__dirname, 'translations');

const defaultLocale = app.getLocale().startsWith('es') ? 'es' : 'en';
console.log(`Default locale: ${defaultLocale}.`);

i18n.configure({
  locales: ['en', 'es'],
  directory: translationsDir,
  defaultLocale: defaultLocale,
  objectNotation: true
});
i18n.setLocale(defaultLocale);

// ───────────────── Updater ─────────────────
autoUpdater.logger = log;
autoUpdater.logger.transports.file.level = 'info';
autoUpdater.autoDownload = false;

/**
 * Initialise listeners and launch the first check.
 * Call this once your main window is ready.
 */
function initUpdates(win) {
  // IMPORTANT! REMOVE THIS WHEN OPEN THE GH REPOSITORY!
  if (!process.env.GH_TOKEN && app.isPackaged) {
    log.warn('GH_TOKEN not present: updater disabled on this boot');
    return;
  }
  // IMPORTANT! REMOVE THIS WHEN OPEN THE GH REPOSITORY!

  const showBox = (opts) => dialog.showMessageBox(win, opts);

  autoUpdater.on('error', (err) => {
    dialog.showErrorBox(
      i18n.__('updater.errorTitle'),
      err == null ? 'unknown' : (err.stack || err).toString()
    );
  });

  autoUpdater.on('update-available', (info) => {
    showBox({
      type: 'info',
      title:   i18n.__('updater.updateAvailableTitle'),
      message: i18n.__('updater.updateAvailableMessage', { version: info.version }),
      buttons: [i18n.__('updater.download'), i18n.__('updater.later')],
      defaultId: 0,
      cancelId: 1
    }).then(({ response }) => {
      if (response === 0) autoUpdater.downloadUpdate();
    });
  });

  autoUpdater.on('update-not-available', () => {
    log.info('No update found');
  });

  autoUpdater.on('update-downloaded', () => {
    showBox({
      type: 'info',
      title:   i18n.__('updater.readyTitle'),
      message: i18n.__('updater.readyMessage'),
      buttons: [i18n.__('updater.restart'), i18n.__('updater.later')],
      defaultId: 0,
      cancelId: 1
    }).then(({ response }) => {
      if (response === 0) setImmediate(() => autoUpdater.quitAndInstall());
    });
  });

  autoUpdater.checkForUpdates();
}

// ───────────────── Globals ─────────────────
let phpBinaryPath;
let appDataPath;
let databasePath;

let mainWindow;
let loadingWindow;
let phpServer;
let isShuttingDown = false;

let customEnv;
let env;

// ───────────────── Save/Export helpers ─────────────────
function inferKnownExt(suggestedName) {
  try {
    const ext = (path.extname(suggestedName || '') || '').toLowerCase().replace(/^\./, '');
    if (!ext) return null;
    if (ext === 'elp' || ext === 'zip' || ext === 'epub' || ext === 'xml') return `.${ext}`;
    return null;
  } catch (_e) {
    return null;
  }
}

function ensureExt(filePath, suggestedName) {
  if (!filePath) return filePath;
  const hasExt = !!path.extname(filePath);
  if (hasExt) return filePath;
  const inferred = inferKnownExt(suggestedName);
  return inferred ? (filePath + inferred) : filePath;
}

// ───────────────── Settings file ─────────────────
const SETTINGS_FILE = () => path.join(app.getPath('userData'), 'settings.json');

function readSettings() {
  try {
    const p = SETTINGS_FILE();
    if (!fs.existsSync(p)) return {};
    const data = fs.readFileSync(p, 'utf8');
    return JSON.parse(data || '{}');
  } catch (_e) {
    return {};
  }
}

function writeSettings(obj) {
  try {
    fs.mkdirSync(path.dirname(SETTINGS_FILE()), { recursive: true });
    fs.writeFileSync(SETTINGS_FILE(), JSON.stringify(obj, null, 2), 'utf8');
  } catch (_e) {
    // best-effort
  }
}

function getSavedPath(key) {
  const s = readSettings();
  return (s.savePath && s.savePath[key]) || null;
}

function setSavedPath(key, filePath) {
  const s = readSettings();
  s.savePath = s.savePath || {};
  s.savePath[key] = filePath;
  writeSettings(s);
}

function clearSavedPath(key) {
  const s = readSettings();
  if (s.savePath && key in s.savePath) {
    delete s.savePath[key];
    writeSettings(s);
  }
}

// Small preferences helper (used for macOS tab bar one-time toggle)
function getPref(key, defVal = null) {
  const s = readSettings();
  return Object.prototype.hasOwnProperty.call(s, key) ? s[key] : defVal;
}
function setPref(key, val) {
  const s = readSettings();
  s[key] = val;
  writeSettings(s);
}

// ───────────────── Download helpers ─────────────────
const nextDownloadKeyByWC = new Map();
const nextDownloadNameByWC = new Map();
const lastDownloadByWC = new Map();

// ───────────────── FS/paths bootstrap ─────────────────
function ensureWritableDirectory(dirPath) {
  if (!fs.existsSync(dirPath)) {
    console.log(`Directory does not exist: ${dirPath}. Creating it...`);
    fs.mkdirSync(dirPath, { recursive: true });
    console.log(`Directory created: ${dirPath}`);
  } else {
    console.log(`Directory already exists: ${dirPath}`);
  }
  try {
    fs.chmodSync(dirPath, 0o777);
    console.log(`Permissions set to 0777 for: ${dirPath}`);
  } catch (error) {
    console.warn(`Could not set permissions on ${dirPath}: ${error.message}`);
  }
}

function ensureAllDirectoriesWritable(env) {
  ensureWritableDirectory(env.FILES_DIR);
  ensureWritableDirectory(env.CACHE_DIR);
  ensureWritableDirectory(env.LOG_DIR);
  const idevicesAdminDir = path.join(env.FILES_DIR, 'perm', 'idevices', 'users', 'admin');
  ensureWritableDirectory(idevicesAdminDir);
}

function initializePaths() {
  phpBinaryPath = getPhpBinaryPath();
  appDataPath = app.getPath('userData');
  databasePath = path.join(appDataPath, 'exelearning.db');

  console.log(`PHP binary path: ${phpBinaryPath}`);
  console.log(`APP data path: ${appDataPath}`);
  console.log('Database path:', databasePath);
}

function initializeEnv() {
  const isDev = determineDevMode();
  const appEnv = isDev ? 'dev' : 'prod';

  customEnv = {
    APP_ENV: process.env.APP_ENV || appEnv,
    APP_DEBUG: process.env.APP_DEBUG ?? (isDev ? 1 : 0),
    EXELEARNING_DEBUG_MODE: (process.env.EXELEARNING_DEBUG_MODE ?? (isDev ? '1' : '0')).toString(),
    APP_SECRET: process.env.APP_SECRET || 'CHANGE_THIS_FOR_A_SECRET',
    APP_PORT: process.env.APP_PORT || '41309',
    APP_ONLINE_MODE: process.env.APP_ONLINE_MODE ?? 0,
    APP_AUTH_METHODS: process.env.APP_AUTH_METHODS || 'none',
    TEST_USER_EMAIL: process.env.TEST_USER_EMAIL || 'localuser@exelearning.net',
    TEST_USER_USERNAME: process.env.TEST_USER_USERNAME || 'localuser',
    TEST_USER_PASSWORD: process.env.TEST_USER_PASSWORD || 'RANDOMUNUSEDPASSWORD',
    TRUSTED_PROXIES: process.env.TRUSTED_PROXIES || '',
    MAILER_DSN: process.env.MAILER_DSN || 'smtp://localhost',
    CAS_URL: process.env.CAS_URL || '',
    DB_DRIVER: process.env.DB_DRIVER || 'pdo_sqlite',
    DB_CHARSET: process.env.DB_CHARSET || 'utf8',
    DB_PATH: process.env.DB_PATH || databasePath,
    DB_SERVER_VERSION: process.env.DB_SERVER_VERSION || '3.32',
    FILES_DIR: process.env.FILES_DIR || path.join(appDataPath, 'data'),
    CACHE_DIR: process.env.CACHE_DIR || path.join(appDataPath, 'cache'),
    LOG_DIR: process.env.LOG_DIR || path.join(appDataPath, 'log'),
    MERCURE_URL: process.env.MERCURE_URL || '',
    API_JWT_SECRET: process.env.API_JWT_SECRET || 'CHANGE_THIS_FOR_A_SECRET',
    ONLINE_THEMES_INSTALL: 1,
    ONLINE_IDEVICES_INSTALL: 1,
  };
}

function determineDevMode() {
  const cliArg = process.argv.find(arg => arg.startsWith('--dev='));
  if (cliArg) {
    const value = cliArg.split('=')[1].toLowerCase();
    return value === 'true' || value === '1';
  }
  const envVal = process.env.EXELEARNING_DEBUG_MODE;
  if (envVal) {
    const value = envVal.toLowerCase();
    return value === 'true' || value === '1';
  }
  return false;
}

function combineEnv() {
  env = Object.assign({}, customEnv, process.env);
}

// ───────────────── window.open handler ─────────────────
// macOS: create a real BrowserWindow that joins the native tab group.
// Win/Linux: deny and ask the renderer to create a new <webview> tab.
function attachOpenHandler(win) {
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (isMac) {
      const childWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        show: true,
        tabbingIdentifier: 'mainGroup',
        webPreferences: {
          nodeIntegration: false,
          contextIsolation: true,
          preload: path.join(__dirname, 'preload.js'),
          // webviewTag stays false on macOS; we rely on native tabs.
        }
      });
      childWindow.loadURL(url);
      attachOpenHandler(childWindow);
      return { action: 'deny' };
    } else {
      try {
        win.webContents.send('tabs:create', { title: 'New', url });
      } catch (_e) {}
      return { action: 'deny' };
    }
  });
}

// ───────────────── Feature flags ─────────────────
const ALLOW_UI_IN_CI = process.env.ALLOW_UI_IN_CI === '1' || process.env.ALLOW_UI_IN_CI === 'true';
const IS_E2E = process.env.E2E_TEST === '1' || (process.env.CI === 'true' && !ALLOW_UI_IN_CI);

// ───────────────── Main window lifecycle ─────────────────
function createWindow() {
  initializePaths();
  initializeEnv();
  combineEnv();

  ensureAllDirectoriesWritable(env);

  if (!IS_E2E) {
    createLoadingWindow();
  }

  checkAndCreateDatabase();
  runSymfonyCommands();
  startPhpServer();

  waitForServer(() => {
    if (loadingWindow) loadingWindow.close();

    const isDev = determineDevMode();

    // Main window:
    //  - macOS: native tabs (no webviewTag needed)
    //  - Win/Linux: renderer-managed tabs => enable webviewTag
    mainWindow = new BrowserWindow({
      width: 1250,
      height: 800,
      autoHideMenuBar: !isDev,
      tabbingIdentifier: 'mainGroup',
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        preload: path.join(__dirname, 'preload.js'),
        webviewTag: !isMac // only for Windows/Linux
      },
      show: ALLOW_UI_IN_CI ? true : !IS_E2E,
    });

    mainWindow.setMenuBarVisibility(isDev);

    if (!IS_E2E) {
      mainWindow.maximize();
      mainWindow.show();
    }

    if (ALLOW_UI_IN_CI) {
      mainWindow.setAlwaysOnTop(true, 'screen-saver');
      mainWindow.show();
      mainWindow.focus();
      setTimeout(() => mainWindow.setAlwaysOnTop(false), 2500);
    }

    // Ensure native tab bar on macOS is visible at least once
    if (isMac) {
      const alreadyForced = getPref('macTabBarForced', false);
      if (!alreadyForced) {
        try { Menu.sendActionToFirstResponder('toggleTabBar:'); } catch (_e) {}
        setPref('macTabBarForced', true);
      }
    }

    // Menu with a “New Tab” item across OSes
    buildAppMenu();

    mainWindow.webContents.on('did-create-window', (childWindow) => {
      console.log('Child window created');
      childWindow.on('close', () => {
        console.log('Child window closed');
        childWindow.destroy();
      });
    });

    mainWindow.loadURL(`http://localhost:${customEnv.APP_PORT}`);

    // Forward any download into our save logic
    session.defaultSession.on('will-download', async (event, item, webContents) => {
      try {
        const wc = webContents && !webContents.isDestroyed?.() ? webContents : (mainWindow ? mainWindow.webContents : null);
        const wcId = wc && !wc.isDestroyed?.() ? wc.id : null;

        try {
          const url = (typeof item.getURL === 'function') ? item.getURL() : undefined;
          if (wcId && url) {
            const now = Date.now();
            const last = lastDownloadByWC.get(wcId);
            if (last && last.url === url && (now - last.time) < 1500) {
              event.preventDefault();
              return;
            }
            lastDownloadByWC.set(wcId, { url, time: now });
          }
        } catch (_e) {}

        const overrideName = wcId ? nextDownloadNameByWC.get(wcId) : null;
        if (wcId && nextDownloadNameByWC.has(wcId)) nextDownloadNameByWC.delete(wcId);
        const suggestedName = overrideName || item.getFilename() || 'document.elp';

        let projectKey = 'default';
        if (wcId && nextDownloadKeyByWC.has(wcId)) {
          projectKey = nextDownloadKeyByWC.get(wcId) || 'default';
          nextDownloadKeyByWC.delete(wcId);
        } else if (wc) {
          try {
            projectKey = await wc.executeJavaScript('window.__currentProjectId || "default"', true);
          } catch (_e) {}
        }

        let targetPath = getSavedPath(projectKey);

        if (!targetPath) {
          const owner = wc ? BrowserWindow.fromWebContents(wc) : mainWindow;
          const { filePath, canceled } = await dialog.showSaveDialog(owner, {
            title: tOrDefault('save.dialogTitle', defaultLocale === 'es' ? 'Guardar proyecto' : 'Save project'),
            defaultPath: suggestedName,
            buttonLabel: tOrDefault('save.button', defaultLocale === 'es' ? 'Guardar' : 'Save')
          });
          if (canceled || !filePath) {
            event.preventDefault();
            return;
          }
          targetPath = ensureExt(filePath, suggestedName);
          setSavedPath(projectKey, targetPath);
        } else {
          const fixed = ensureExt(targetPath, suggestedName);
          if (fixed !== targetPath) {
            targetPath = fixed;
            setSavedPath(projectKey, targetPath);
          }
        }

        item.setSavePath(targetPath);

        item.on('updated', (_e, state) => {
          if (state === 'progressing') {
            if (wc && !wc.isDestroyed?.()) wc.send('download-progress', {
              received: item.getReceivedBytes(),
              total: item.getTotalBytes()
            });
          } else if (state === 'interrupted') {
            try { if (item.canResume()) item.resume(); } catch (_err) {}
          }
        });

        item.once('done', (_e, state) => {
          const send = (payload) => {
            if (wc && !wc.isDestroyed?.()) wc.send('download-done', payload);
            else if (mainWindow && !mainWindow.isDestroyed()) mainWindow.webContents.send('download-done', payload);
          };
          if (state === 'completed') {
            send({ ok: true, path: targetPath });
            return;
          }
          if (state === 'interrupted') {
            try {
              const total = item.getTotalBytes() || 0;
              const exists = fs.existsSync(targetPath);
              const size = exists ? fs.statSync(targetPath).size : 0;
              if (exists && (total === 0 || size >= total)) {
                send({ ok: true, path: targetPath });
                return;
              }
            } catch (_err) {}
          }
          send({ ok: false, error: state });
        });
      } catch (err) {
        event.preventDefault();
        if (mainWindow && !mainWindow.isDestroyed()) {
          mainWindow.webContents.send('download-done', { ok: false, error: err.message });
        }
      }
    });

    if (!IS_E2E) {
      initUpdates(mainWindow);
    }

    // Force-destroy, ignore any preventDefault in renderers
    mainWindow.on('close', (e) => {
      console.log('Window is being forced to close...');
      e.preventDefault();
      mainWindow.destroy();
    });

    mainWindow.on('closed', () => { mainWindow = null; });

    // Forward window.open handling
    attachOpenHandler(mainWindow);

    // On macOS, support native new tab button (+) in the title bar
    app.on('new-window-for-tab', () => {
      openNewDocument(`http://localhost:${customEnv.APP_PORT}`);
    });

    // App exit hooks
    handleAppExit();
  });
}

// ───────────────── Loading window ─────────────────
function createLoadingWindow() {
  loadingWindow = new BrowserWindow({
    width: 400,
    height: 300,
    frame: false,
    transparent: true,
    alwaysOnTop: true,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
    },
  });
  loadingWindow.loadFile(path.join(basePath, 'public', 'loading.html'));
}

// ───────────────── Server wait ─────────────────
function waitForServer(callback) {
  const options = {
    host: 'localhost',
    port: customEnv.APP_PORT,
    timeout: 1000,
  };

  const checkServer = () => {
    const req = http.request(options, (res) => {
      if (res.statusCode >= 200 && res.statusCode <= 400) {
        console.log('PHP server available.');
        callback();
      } else {
        console.log(`Server status: ${res.statusCode}. Retrying...`);
        setTimeout(checkServer, 1000);
      }
    });
    req.on('error', () => {
      console.log('PHP server not available, retrying...');
      setTimeout(checkServer, 1000);
    });
    req.end();
  };

  checkServer();
}

// ───────────────── Stream download ─────────────────
function streamToFile(downloadUrl, targetPath, wc, redirects = 0) {
  return new Promise(async (resolve) => {
    try {
      let baseOrigin = `http://localhost:${(customEnv && customEnv.APP_PORT) ? customEnv.APP_PORT : 80}/`;
      try {
        if (wc && !wc.isDestroyed?.()) {
          const current = wc.getURL && wc.getURL();
          if (current) baseOrigin = current;
        }
      } catch (_e) {}
      let urlObj;
      try { urlObj = new URL(downloadUrl); }
      catch (_e) { urlObj = new URL(downloadUrl, baseOrigin); }

      const client = urlObj.protocol === 'https:' ? https : http;
      let cookieHeader = '';
      try {
        const cookieList = await session.defaultSession.cookies.get({ url: `${urlObj.protocol}//${urlObj.host}` });
        cookieHeader = cookieList.map(c => `${c.name}=${c.value}`).join('; ');
      } catch (_e) {}

      const request = client.request({
        protocol: urlObj.protocol,
        hostname: urlObj.hostname,
        port: urlObj.port || (urlObj.protocol === 'https:' ? 443 : 80),
        path: urlObj.pathname + (urlObj.search || ''),
        method: 'GET',
        headers: Object.assign({}, cookieHeader ? { 'Cookie': cookieHeader } : {})
      }, (res) => {
        if (res.statusCode && res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
          if (redirects > 5) {
            if (wc && !wc.isDestroyed?.()) wc.send('download-done', { ok: false, error: 'Too many redirects' });
            resolve(false);
            return;
          }
          const nextUrl = new URL(res.headers.location, downloadUrl).toString();
          res.resume();
          streamToFile(nextUrl, targetPath, wc, redirects + 1).then(resolve);
          return;
        }
        if (res.statusCode !== 200) {
          if (wc && !wc.isDestroyed?.()) wc.send('download-done', { ok: false, error: `HTTP ${res.statusCode}` });
          resolve(false);
          return;
        }
        const total = parseInt(res.headers['content-length'] || '0', 10) || 0;
        let received = 0;
        const out = fs.createWriteStream(targetPath);
        res.on('data', (chunk) => {
          received += chunk.length;
          if (wc && !wc.isDestroyed?.()) wc.send('download-progress', { received, total });
        });
        res.on('error', (err) => {
          try { out.close(); } catch (_e) {}
          if (wc && !wc.isDestroyed?.()) wc.send('download-done', { ok: false, error: err.message });
          resolve(false);
        });
        out.on('error', (err) => {
          try { res.destroy(); } catch (_e) {}
          if (wc && !wc.isDestroyed?.()) wc.send('download-done', { ok: false, error: err.message });
          resolve(false);
        });
        out.on('finish', () => {
          if (wc && !wc.isDestroyed?.()) wc.send('download-done', { ok: true, path: targetPath });
          resolve(true);
        });
        res.pipe(out);
      });
      request.on('error', (err) => {
        if (wc && !wc.isDestroyed?.()) wc.send('download-done', { ok: false, error: err.message });
        resolve(false);
      });
      request.end();
    } catch (err) {
      if (wc && !wc.isDestroyed?.()) wc.send('download-done', { ok: false, error: err.message });
      resolve(false);
    }
  });
}

// ───────────────── Export ZIP ─────────────────
ipcMain.handle('app:exportToFolder', async (e, { downloadUrl }) => {
  const senderWindow = BrowserWindow.fromWebContents(e.sender);
  try {
    const { canceled, filePaths } = await dialog.showOpenDialog(senderWindow, {
      title: tOrDefault('export.folder.dialogTitle', defaultLocale === 'es' ? 'Exportar a carpeta' : 'Export to folder'),
      properties: ['openDirectory', 'createDirectory']
    });
    if (canceled || !filePaths || !filePaths.length) return { ok: false, canceled: true };
    const destDir = filePaths[0];

    const wc = e && e.sender ? e.sender : (mainWindow ? mainWindow.webContents : null);
    const tmpZip = path.join(app.getPath('temp'), `exe-export-${Date.now()}.zip`);
    const ok = await streamToFile(downloadUrl, tmpZip, null);
    if (!ok || !fs.existsSync(tmpZip)) {
      try { fs.existsSync(tmpZip) && fs.unlinkSync(tmpZip); } catch (_e) {}
      return { ok: false, error: 'download-failed' };
    }

    try {
      const zip = new AdmZip(tmpZip);
      zip.extractAllTo(destDir, true);
    } finally {
      try { fs.unlinkSync(tmpZip); } catch (_e) {}
    }

    try { if (wc && !wc.isDestroyed?.()) wc.send('download-done', { ok: true, path: destDir }); } catch (_e) {}
    return { ok: true, dir: destDir };
  } catch (err) {
    return { ok: false, error: err && err.message ? err.message : 'unknown' };
  }
});

// ───────────────── Hook the window-open handler on any new BrowserWindow ─────────────────
app.on('browser-window-created', (_event, window) => {
  attachOpenHandler(window);
});

// ───────────────── Tabs API: openNewDocument ─────────────────
/**
 * Opens a new tabbed window on macOS (native tab) or asks the renderer to add a <webview> tab on Win/Linux.
 */
function openNewDocument(url) {
  if (isMac) {
    const win = new BrowserWindow({
      width: 1200,
      height: 800,
      show: true,
      tabbingIdentifier: 'mainGroup',
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        preload: path.join(__dirname, 'preload.js'),
      },
    });
    win.loadURL(url);
    return win;
  } else {
    const target = mainWindow || BrowserWindow.getAllWindows()[0];
    if (target && !target.isDestroyed()) {
      target.webContents.send('tabs:create', { title: 'Document', url });
      target.show();
      target.focus();
      return target;
    }
    const win = new BrowserWindow({
      width: 1200,
      height: 800,
      show: true,
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        preload: path.join(__dirname, 'preload.js'),
        webviewTag: true
      },
    });
    win.loadURL(`http://localhost:${customEnv.APP_PORT}`);
    win.once('ready-to-show', () => {
      win.webContents.send('tabs:create', { title: 'Document', url });
    });
    return win;
  }
}

// ───────────────── CI / ready ─────────────────
if (IS_E2E) app.disableHardwareAcceleration();
app.whenReady().then(createWindow);

// ───────────────── Quit lifecycle ─────────────────
app.on('window-all-closed', function () {
  if (phpServer) {
    phpServer.kill('SIGTERM');
    console.log('Closed PHP server.');
  }
  if (!isMac) app.quit();
});

function handleAppExit() {
  const cleanup = () => {
    if (isShuttingDown) return;
    isShuttingDown = true;

    if (phpServer) {
      phpServer.kill('SIGTERM');
      phpServer = null;
    }
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.destroy();
    }
    setTimeout(() => process.exit(0), 500);
  };

  process.on('SIGINT', cleanup);
  process.on('SIGTERM', cleanup);
  process.on('exit', cleanup);
  app.on('window-all-closed', cleanup);
  app.on('before-quit', cleanup);
}

app.on('activate', () => {
  if (mainWindow === null) {
    createWindow();
  }
});

// ───────────────── IPC: Save / Save As / Pickers ─────────────────
ipcMain.handle('app:save', async (e, { downloadUrl, projectKey, suggestedName }) => {
  if (typeof downloadUrl !== 'string' || !downloadUrl) return false;
  try {
    const wc = e && e.sender ? e.sender : (mainWindow ? mainWindow.webContents : null);
    let key = projectKey || 'default';
    try {
      if (!projectKey && wc && !wc.isDestroyed?.()) {
        key = await wc.executeJavaScript('window.__currentProjectId || "default"', true);
      }
    } catch (_er) {}
    let targetPath = getSavedPath(key);
    if (!targetPath) {
      const owner = wc ? BrowserWindow.fromWebContents(wc) : mainWindow;
      const { filePath, canceled } = await dialog.showSaveDialog(owner, {
        title: tOrDefault('save.dialogTitle', defaultLocale === 'es' ? 'Guardar proyecto' : 'Save project'),
        defaultPath: suggestedName || 'document.elp',
        buttonLabel: tOrDefault('save.button', defaultLocale === 'es' ? 'Guardar' : 'Save')
      });
      if (canceled || !filePath) return false;
      targetPath = ensureExt(filePath, suggestedName || 'document.elp');
      setSavedPath(key, targetPath);
    } else {
      const fixed = ensureExt(targetPath, suggestedName || 'document.elp');
      if (fixed !== targetPath) {
        targetPath = fixed;
        setSavedPath(key, targetPath);
      }
    }
    return await streamToFile(downloadUrl, targetPath, wc);
  } catch (_e) {
    return false;
  }
});

ipcMain.handle('app:saveAs', async (e, { downloadUrl, projectKey, suggestedName }) => {
  const senderWindow = BrowserWindow.fromWebContents(e.sender);
  const wc = e && e.sender ? e.sender : (mainWindow ? mainWindow.webContents : null);
  const key = projectKey || 'default';
  const { filePath, canceled } = await dialog.showSaveDialog(senderWindow, {
    title: tOrDefault('saveAs.dialogTitle', defaultLocale === 'es' ? 'Guardar como…' : 'Save as…'),
    defaultPath: suggestedName || 'document.elp',
    buttonLabel: tOrDefault('save.button', defaultLocale === 'es' ? 'Guardar' : 'Save')
  });
  if (canceled || !filePath) return false;
  const finalPath = ensureExt(filePath, suggestedName || 'document.elp');
  setSavedPath(key, finalPath);
  if (typeof downloadUrl === 'string' && downloadUrl && wc) {
    return await streamToFile(downloadUrl, finalPath, wc);
  }
  return false;
});

ipcMain.handle('app:setSavedPath', async (_e, { projectKey, filePath }) => {
  if (!projectKey || !filePath) return false;
  setSavedPath(projectKey, filePath);
  return true;
});

ipcMain.handle('app:openElp', async (e) => {
  const senderWindow = BrowserWindow.fromWebContents(e.sender);
  const { canceled, filePaths } = await dialog.showOpenDialog(senderWindow, {
    title: tOrDefault('open.dialogTitle', defaultLocale === 'es' ? 'Abrir proyecto' : 'Open project'),
    properties: ['openFile'],
    filters: [{ name: 'eXeLearning project', extensions: ['elp', 'zip'] }]
  });
  if (canceled || !filePaths || !filePaths.length) return null;
  return filePaths[0];
});

ipcMain.handle('app:readFile', async (_e, { filePath }) => {
  try {
    if (!filePath) return { ok: false, error: 'No path' };
    const data = fs.readFileSync(filePath);
    const stat = fs.statSync(filePath);
    return { ok: true, base64: data.toString('base64'), mtimeMs: stat.mtimeMs };
  } catch (err) {
    return { ok: false, error: err.message };
  }
});

// ───────────────── DB / Symfony ─────────────────
function checkAndCreateDatabase() {
  if (!fs.existsSync(databasePath)) {
    console.log('The database does not exist. Creating the database...');
    fs.openSync(databasePath, 'w');
  } else {
    console.log('The database already exists.');
  }
}

function runSymfonyCommands() {
  try {
    const publicDir = path.join(basePath, 'public');
    if (!fs.existsSync(publicDir)) {
      showErrorDialog(`The public directory was not found at the path: ${publicDir}`);
      app.quit();
    }

    const consolePath = path.join(basePath, 'bin', 'console');
    if (!fs.existsSync(consolePath)) {
      showErrorDialog(`The bin/console file was not found at the path: ${consolePath}`);
      app.quit();
    }
    try {
      console.log('Clearing Symfony cache...');
      execFileSync(phpBinaryPath, ['bin/console', 'cache:clear'], {
        env: env, cwd: basePath, windowsHide: true, stdio: 'inherit',
      });
    } catch (cacheError) {
      console.error('Error clearing cache (non-critical):', cacheError.message);
    }

    console.log('Creating database tables in SQLite...');
    execFileSync(phpBinaryPath, ['bin/console', 'doctrine:schema:update', '--force'], {
      env: env, cwd: basePath, windowsHide: true, stdio: 'inherit',
    });

    if (!app.isPackaged) {
      try {
        console.log('Installing assets in public (dev/local only)...');
        execFileSync(phpBinaryPath, ['bin/console', 'assets:install', 'public', '--no-debug', '--env=prod'], {
          env, cwd: basePath, windowsHide: true, stdio: 'inherit',
        });
      } catch (e) {
        console.warn('Skipping assets:install:', e.message);
      }
    } else {
      console.log('Skipping assets:install (packaged app is read-only).');
    }

    console.log('Creating test user...');
    execFileSync(phpBinaryPath, [
      'bin/console',
      'app:create-user',
      customEnv.TEST_USER_EMAIL,
      customEnv.TEST_USER_PASSWORD,
      customEnv.TEST_USER_USERNAME,
      '--no-fail',
    ], {
      env: env, cwd: basePath, windowsHide: true, stdio: 'inherit',
    });

    console.log('Symfony commands executed successfully.');
  } catch (err) {
    showErrorDialog(`Error executing Symfony commands: ${err.message}`);
    app.quit();
  }
}

function phpIniArgs() {
  return [
    '-dopcache.enable=1',
    '-dopcache.enable_cli=1',
    '-dopcache.memory_consumption=128',
    '-dopcache.interned_strings_buffer=16',
    '-dopcache.max_accelerated_files=20000',
    '-dopcache.validate_timestamps=0',
    '-drealpath_cache_size=4096k',
    '-drealpath_cache_ttl=600',
  ];
}

function startPhpServer() {
  try {
    phpServer = spawn(
      phpBinaryPath,
      [...phpIniArgs(), '-S', `localhost:${customEnv.APP_PORT}`, '-t', 'public', 'public/router.php'],
      { env, cwd: basePath, windowsHide: true }
    );

    phpServer.on('error', (err) => {
      console.error('Error starting PHP server:', err.message);
      if (err.message.includes('EADDRINUSE')) {
        showErrorDialog(`Port ${customEnv.APP_PORT} is already in use. Close the process using it and try again.`);
      } else {
        showErrorDialog(`Error starting PHP server: ${err.message}`);
      }
      app.quit();
    });

    phpServer.stdout.on('data', (data) => {
      console.log(`PHP: ${data}`);
    });

    phpServer.stderr.on('data', (data) => {
      const errorMessage = data.toString();
      console.error(`PHP Error: ${errorMessage}`);
      if (errorMessage.includes('Address already in use')) {
        showErrorDialog(`Port ${customEnv.APP_PORT} is already in use. Close the process using it and try again.`);
        app.quit();
      }
    });

    phpServer.on('close', (code) => {
      console.log(`The PHP server closed with code ${code}`);
      if (code !== 0) {
        app.quit();
      }
    });
  } catch (err) {
    showErrorDialog(`Error starting PHP server: ${err.message}`);
    app.quit();
  }
}

function showErrorDialog(message) {
  dialog.showErrorBox('Error', message);
}

function getPhpBinaryPath() {
  const bundledDir = path.join(process.resourcesPath, 'php-bin', 'php-8.4');
  const bundledBin = path.join(bundledDir, process.platform === 'win32' ? 'php.exe' : 'php');
  if (fs.existsSync(bundledBin)) return bundledBin;

  const platform = process.platform;
  const arch = process.arch;

  const phpBinaryDir = path.join(app.getPath('userData'), 'php-bin', 'php-8.4');

  const phpZipPath = path.join(
    basePath,
    'vendor',
    'nativephp',
    'php-bin',
    'bin',
    platform === 'win32' ? 'win' : platform === 'darwin' ? 'mac' : 'linux',
    arch === 'arm64' && platform === 'darwin' ? 'arm64' : 'x64',
    'php-8.4.zip'
  );

  if (!fs.existsSync(phpBinaryDir)) {
    console.log('Extracting PHP in', phpBinaryDir);
    const zip = new AdmZip(phpZipPath);
    zip.extractAllTo(phpBinaryDir, true);
    console.log('Extraction completed');

    if (platform !== 'win32') {
      const phpBinary = path.join(phpBinaryDir, 'php');
      try {
        fs.chmodSync(phpBinary, 0o755);
        console.log('Execution permissions applied successfully to the PHP binary');
      } catch (err) {
        showErrorDialog(`Error applying chmod to the PHP binary: ${err.message}`);
        app.quit();
      }
    }
  }

  const phpBinary = platform === 'win32' ? 'php.exe' : 'php';
  const phpBinaryPathFinal = path.join(phpBinaryDir, phpBinary);

  if (!fs.existsSync(phpBinaryPathFinal)) {
    showErrorDialog(`The PHP binary was not found at the path: ${phpBinaryPathFinal}`);
    app.quit();
  }

  return phpBinaryPathFinal;
}

function tOrDefault(key, fallback) {
  try {
    const val = i18n.__(key);
    if (!val || val === key) return fallback;
    return val;
  } catch (_e) {
    return fallback;
  }
}

// ───────────────── Menu builder ─────────────────
function buildAppMenu() {
  // Build a minimal menu with New Tab and native tab helpers on macOS
  const template = [];

  if (isMac) {
    template.push({
      label: app.name,
      submenu: [
        { role: 'about' },
        { type: 'separator' },
        { role: 'services' },
        { type: 'separator' },
        { role: 'hide' },
        { role: 'hideOthers' },
        { role: 'unhide' },
        { type: 'separator' },
        { role: 'quit' }
      ]
    });
    template.push({
      label: 'File',
      submenu: [
        {
          label: 'New Tab',
          accelerator: 'CmdOrCtrl+T',
          click: () => openNewDocument(`http://localhost:${customEnv.APP_PORT}`)
        },
        { type: 'separator' },
        { role: 'close' }
      ]
    });
    template.push({
      label: 'Window',
      role: 'windowMenu',
      submenu: [
        { role: 'minimize' },
        { role: 'zoom' },
        { type: 'separator' },
        { role: 'toggleTabBar' },
        { role: 'selectPreviousTab' },
        { role: 'selectNextTab' },
        { role: 'mergeAllWindows' },
        { role: 'moveTabToNewWindow' },
        { type: 'separator' },
        { role: 'front' }
      ]
    });
  } else {
    template.push({
      label: 'File',
      submenu: [
        {
          label: 'New Tab',
          accelerator: 'Ctrl+T',
          click: () => {
            const target = mainWindow || BrowserWindow.getAllWindows()[0];
            if (target && !target.isDestroyed()) {
              target.webContents.send('tabs:create', {
                title: 'New',
                url: `http://localhost:${customEnv.APP_PORT}/`
              });
              target.show();
              target.focus();
            }
          }
        },
        { role: 'quit' }
      ]
    });
    template.push({ role: 'editMenu' });
    template.push({ role: 'viewMenu' });
    template.push({ role: 'windowMenu' });
    template.push({ role: 'help', submenu: [] });
  }

  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}
