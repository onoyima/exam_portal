const { app, BrowserWindow, globalShortcut, dialog, screen } = require('electron');
const path = require('path');
const fs = require('fs');

// ─── Configuration ────────────────────────────────────────────
function loadConfig() {
  const configPaths = [
    path.join(__dirname, 'config.json'),
    path.join(process.resourcesPath || '', 'config.json'),
    path.join(app.getPath('userData'), 'config.json'),
  ];
  for (const p of configPaths) {
    try {
      if (fs.existsSync(p)) {
        return JSON.parse(fs.readFileSync(p, 'utf8'));
      }
    } catch (e) {
      console.warn('Failed to load config from', p, e.message);
    }
  }
  return {
    examUrl: 'http://localhost/exam_portal/student_login',
    logoutPatterns: ['student_logout', 'login', 'logout'],
    appTitle: 'Veritas SEB Kiosk',
    allowedDomains: ['localhost', '127.0.0.1'],
    connectionTimeout: 30,
  };
}

const config = loadConfig();

// ─── Auto-detect server URL from command line ──────────────
const cmdUrl = process.argv.find(a => a.startsWith('--url='));
if (cmdUrl) {
  const parsed = cmdUrl.replace('--url=', '');
  try {
    new URL(parsed);
    config.examUrl = parsed;
  } catch {}
}

// ─── Copy default config to userData for easy editing ─────
try {
  const userConfigPath = path.join(app.getPath('userData'), 'config.json');
  if (!fs.existsSync(userConfigPath)) {
    const defaultConfig = path.join(__dirname, 'config.json');
    if (fs.existsSync(defaultConfig)) {
      const dir = path.dirname(userConfigPath);
      if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
      fs.copyFileSync(defaultConfig, userConfigPath);
    }
  }
} catch (e) {
  console.warn('Could not copy config to userData:', e.message);
}

// ─── State ────────────────────────────────────────────────────
let mainWindow = null;
let isExiting = false;

// ─── Kiosk Window ─────────────────────────────────────────────
function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1920,
    height: 1080,
    frame: false,
    fullscreen: true,
    kiosk: true,
    title: config.appTitle || 'Veritas SEB Kiosk',
    icon: path.join(__dirname, 'assets', 'icon.png'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: true,
      disableDialogs: true,
      webviewTag: false,
    },
    show: false,
  });

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
    mainWindow.focus();
  });

  mainWindow.webContents.on('will-navigate', (event, url) => {
    if (!isAllowedUrl(url)) event.preventDefault();
  });

  mainWindow.webContents.on('did-navigate', (event, url) => {
    checkForLogout(url);
  });

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (isAllowedUrl(url)) mainWindow.loadURL(url);
    return { action: 'deny' };
  });

  mainWindow.on('close', (event) => {
    if (!isExiting) event.preventDefault();
  });

  mainWindow.on('closed', () => { mainWindow = null; });

  mainWindow.loadURL(config.examUrl || 'http://localhost/exam_portal/student_login');
}

// ─── URL Validation ──────────────────────────────────────────
function isAllowedUrl(url) {
  try {
    const u = new URL(url);
    const allowed = config.allowedDomains || ['localhost', '127.0.0.1'];
    return allowed.some(d => u.hostname.includes(d));
  } catch {
    return false;
  }
}

function checkForLogout(url) {
  if (isExiting) return;
  try {
    const patterns = config.logoutPatterns || ['student_logout', 'login'];
    if (patterns.some(p => url.includes(p))) safeExit();
  } catch {}
}

// ─── Safe Exit ───────────────────────────────────────────────
function safeExit() {
  if (isExiting) return;
  isExiting = true;
  try { globalShortcut.unregisterAll(); } catch {}
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.removeAllListeners('close');
    mainWindow.close();
  }
  app.quit();
}

// ─── Global Shortcuts ──────────────────────────────────────
function registerShortcuts() {
  const shortcuts = [
    'CommandOrControl+Q', 'CommandOrControl+W',
    'CommandOrControl+N', 'CommandOrControl+T',
    'CommandOrControl+R', 'F5', 'F11', 'Escape',
    'CommandOrControl+Shift+I', 'F12',
    'CommandOrControl+U', 'CommandOrControl+S', 'CommandOrControl+P',
    'CommandOrControl+Shift+J', 'Alt+Left', 'Alt+Right',
  ];
  for (const key of shortcuts) {
    try { globalShortcut.register(key, () => false); } catch {}
  }
}

// ─── Inject Renderer Security ───────────────────────────────
function injectScripts() {
  if (!mainWindow) return;
  mainWindow.webContents.on('did-finish-load', () => {
    mainWindow.webContents.executeJavaScript(`
      document.addEventListener('contextmenu', e => e.preventDefault(), true);
      document.addEventListener('keydown', e => {
        if (e.key === 'F12' || e.key === 'F5' || e.key === 'F11' || e.key === 'Escape' ||
            e.key === 'PrintScreen' || e.key === 'ScrollLock' ||
            (e.ctrlKey && e.shiftKey && (e.key==='I'||e.key==='J')) ||
            (e.ctrlKey && ['U','n','t','w','s','p','r'].includes(e.key)) ||
            (e.metaKey && ['q','w','n','t','r','s','p'].includes(e.key))) {
          e.preventDefault(); e.stopPropagation(); return false;
        }
      }, true);
      document.addEventListener('selectstart', e => e.preventDefault(), true);
      document.addEventListener('copy', e => e.preventDefault(), true);
      document.addEventListener('cut', e => e.preventDefault(), true);
      document.addEventListener('paste', e => e.preventDefault(), true);
    `).catch(() => {});
  });
}

// ─── App Lifecycle ──────────────────────────────────────────
app.whenReady().then(() => {
  createWindow();
  registerShortcuts();
  injectScripts();
});

app.on('window-all-closed', () => {
  try { globalShortcut.unregisterAll(); } catch {}
  if (!isExiting) app.quit();
});

app.on('will-quit', () => {
  try { globalShortcut.unregisterAll(); } catch {}
});

app.on('activate', () => {
  if (mainWindow === null) createWindow();
});
