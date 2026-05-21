# Veritas SEB Kiosk — Secure Exam Browser

A **Secure Exam Browser** for the Veritas Exam Portal. It locks the computer into fullscreen exam mode so students cannot open other apps, browse the web, copy answers, or cheat. Works on **Windows**, **macOS**, and **Linux**.

---

## For Students — How to Take Your Exam

Only **3 steps**:

### Step 1: Install the Browser

| If you have this computer | Download this file | How to install |
|---|---|---|
| **Windows** | `VeritasSEB-1.0.0-win.exe` | Download the file, then **double-click** it. The exam browser opens fullscreen. No installation needed — just run it. |
| **Mac** | `VeritasSEB-1.0.0-mac.dmg` | Double-click the `.dmg` file → drag the app into the **Applications** folder. |
| **Linux (Ubuntu/Debian)** | `VeritasSEB-1.0.0-linux.deb` | Double-click → click **Install**. |
| **Linux (any)** | `VeritasSEB-1.0.0-linux.AppImage` | Right-click → **Properties → Permissions** → tick **Allow executing as program** → then double-click. |

> **Got a problem?** If the installer says "Windows protected your PC", click **More info → Run anyway**. This only happens the first time because the app is new.

### Step 2: Open the Browser

**Windows:** Double-click the `VeritasSEB-1.0.0-win.exe` file you downloaded.

**Mac / Linux:** Open the app from your Applications folder or the AppImage file.

The browser opens **fullscreen** — no address bar, no menus, no way to close it except by finishing your exam. This is normal.

### Step 3: Log In and Take Your Exam

- The exam portal will load automatically.
- Enter your **Matric Number** and **Password** — same as always.
- Click **Start Exam** and answer your questions.
- When you finish and click **Submit** (or log out), the browser closes itself automatically.

**That's it.** You cannot open other websites, copy/paste, or use keyboard shortcuts. The browser only lets you access the exam. Once you log out, it closes.

> **Need help?** Tell your exam invigilator. They can close the browser for you with an admin password.

---

## For IT Admins — How to Set Up and Deploy

### Before You Start

You need:

- **The installer file** for the computers your students will use (see [Building the Installer](#building-the-installer) below).
- **Your exam portal URL** — the address students type to log in (e.g. `https://exam.yourschool.edu.ng/student_login`).
- A way to distribute the installer to student computers (USB drive, email link, network share, or deploy via Group Policy / MDM).

---

### Step 1: Edit the Exam URL

After installing the browser, you must tell it where your exam portal is. Open the **config.json** file:

#### On Windows

1. Press **Windows Key + R**, type this and press Enter:
   ```
   %APPDATA%\Veritas SEB Kiosk
   ```
2. Open the **config.json** file with Notepad (right-click → Open with → Notepad).
3. Change the `"examUrl"` line to your exam portal address:
   ```json
   "examUrl": "https://exam.yourschool.edu.ng/student_login"
   ```
4. Save the file (Ctrl+S) and close Notepad.

#### On Mac

1. Open **Finder**, press **Cmd+Shift+G**, type this and press Enter:
   ```
   ~/Library/Application Support/Veritas SEB Kiosk
   ```
2. Open **config.json** with TextEdit.
3. Change the `"examUrl"` line:
   ```json
   "examUrl": "https://exam.yourschool.edu.ng/student_login"
   ```
4. Save and close.

#### On Linux

1. Open a terminal and run:
   ```
   nano ~/.config/Veritas\ SEB\ Kiosk/config.json
   ```
2. Change the `"examUrl"` line.
3. Press **Ctrl+X**, then **Y**, then **Enter** to save.

---

### Step 2: Test the Browser

1. Open the browser — it should load your exam portal fullscreen.
2. Log in as a test student — everything should work normally.
3. Log out — the browser should close automatically.

> **Can't close the browser?** Press **Alt+F4** (Windows/Linux) or **Cmd+Q** (Mac) to force-quit. On Windows, you can also press **Ctrl+Shift+Esc** to open Task Manager, find `Veritas SEB Kiosk`, and click **End task**.

---

### Step 3: Deploy to Student Computers

| Method | What to do |
|---|---|
| **USB drive** | Copy the installer to a USB stick, plug into each computer, install, then configure config.json. |
| **Email / download link** | Upload the installer to Google Drive / your portal, send students the link + a 1-minute guide. |
| **Group Policy (Windows domain)** | Deploy the `.exe` via Group Policy Software Installation (Computer Configuration → Software Settings → Software Installation → New → Package). |
| **MDM (mobile device management)** | Deploy as a managed app — consult your MDM provider's docs. |

> **Tip for mass deployment:** After configuring config.json on one computer, copy the file to all others. On Windows, the config is at `%APPDATA%\Veritas SEB Kiosk\config.json`. On Mac, `~/Library/Application Support/Veritas SEB Kiosk/config.json`. On Linux, `~/.config/Veritas SEB Kiosk/config.json`.

---

## Configuration Reference (for Admins)

The **config.json** file controls what the browser does:

```json
{
  "examUrl": "https://exam.yourschool.edu.ng/student_login",
  "logoutPatterns": ["student_logout", "login"],
  "appTitle": "Veritas SEB Kiosk",
  "allowedDomains": ["exam.yourschool.edu.ng"],
  "connectionTimeout": 30
}
```

| Setting | What it does |
|---|---|
| `examUrl` | **Required.** The exam portal page the browser opens when launched. Change this to your school's portal address. |
| `logoutPatterns` | Tells the browser when to close. If the page URL contains any of these words, the browser closes. The defaults (`student_logout`, `login`) work with the Veritas portal. |
| `allowedDomains` | Only these web addresses can be accessed. Everything else is blocked. Set this to your exam portal's domain. |
| `appTitle` | What the browser calls itself in Task Manager / Activity Monitor. You can leave this as is. |
| `connectionTimeout` | Reserved for future use. Leave it at 30. |

---

## What the Browser Blocks

When a student uses this browser, they **cannot**:

- Close or minimize the window (no X button, no Alt+F4, no Escape)
- Open other programs or websites
- Right-click anything
- Copy, cut, or paste text
- Press F5 / F11 / F12 / Ctrl+R / Ctrl+W / Ctrl+T / Ctrl+N
- Open Developer Tools (F12 / Ctrl+Shift+I)
- Take screenshots (Print Screen)
- Open new tabs or pop-up windows
- Navigate away from the exam portal

The browser **closes automatically** when the student logs out or submits the exam.

---

## Building the Installer

You only need to do this if you are setting up the SEB for the first time or customising it.

### What You Need

- A computer with **Windows**, **macOS**, or **Linux**
- **Node.js** (free, download from https://nodejs.org — get the **LTS** version)
- Internet connection (to download dependencies once)

### Build It in 3 Commands

Open a **Command Prompt** (Windows) or **Terminal** (Mac/Linux) and run:

```bash
cd seb-client/src
npm install
npm run build:win       # for Windows installer
# or
npm run build:mac       # for macOS installer (must be on a Mac)
# or
npm run build:linux     # for Linux installer (must be on Linux)
```

The installer will appear in the `seb-client/installers/` folder.

### One-Click Scripts

Run these instead of typing commands:

| Script | OS | How to run |
|---|---|---|
| `build-installer.bat` | Windows | Double-click the file |
| `build-installer.ps1` | Windows | Right-click → Run with PowerShell |
| `build-installer.sh` | Mac / Linux | Open terminal, type `./build-installer.sh` |

The script will detect your OS, install dependencies, and build the correct installer automatically.

### Pre-built Installers

| OS | File | How it works |
|---|---|---|
| **Windows** | `VeritasSEB-1.0.0-win.exe` | **Standalone app** — double-click to run. No installation wizard, no admin rights needed. The exam portal opens automatically. |
| **macOS** | `VeritasSEB-1.0.0-mac.dmg` | Drag into Applications folder, then open. |
| **Linux (AppImage)** | `VeritasSEB-1.0.0-linux.AppImage` | Make executable, double-click to run. |
| **Linux (Debian/Ubuntu)** | `VeritasSEB-1.0.0-linux.deb` | Install with `dpkg -i`, then launch from menu. |

---

## Project Files

```
seb-client/
├── build-installer.bat      ─ Double-click to build for Windows
├── build-installer.ps1      ─ Right-click → Run with PowerShell
├── build-installer.sh       ─ Run in Terminal on Mac/Linux
├── README.md                ─ This file
├── .gitignore
└── src/
    ├── package.json          ─ Project settings & build config
    ├── main.js               ─ The browser engine (kiosk, shortcuts, security)
    ├── preload.js            ─ Security bridge
    ├── config.json           ─ Exam URL & behaviour settings
    ├── assets/               ─ App icon goes here
    │   └── icon.png
    └── build/
        └── nsis-config.nsh   ─ Windows installer customisation
```
