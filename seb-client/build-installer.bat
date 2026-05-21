@echo off
chcp 65001 >nul
title Veritas SEB Kiosk - Installer Builder
echo ======================================================
echo   Veritas SEB Kiosk - Cross-Platform Installer Builder
echo   For Windows, macOS, and Linux
echo ======================================================
echo.

cd /d "%~dp0src"

REM Check Node.js
where node >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Node.js is not installed.
    echo         Download from: https://nodejs.org/ (LTS version)
    pause
    exit /b 1
)

echo [1/4] Node.js found: 
call node --version

REM Install dependencies
echo [2/4] Installing dependencies...
call npm install
if errorlevel 1 (
    echo [ERROR] npm install failed
    pause
    exit /b 1
)
echo   Done.

REM Choose platform
echo.
echo Select platform to build:
echo   [1] Windows (.exe installer)
echo   [2] macOS (.dmg) - requires macOS build machine
echo   [3] Linux (.AppImage + .deb) - requires Linux build machine
echo   [4] All platforms (requires all build machines)
echo.
set /p CHOICE="Enter choice (1-4): "

if "%CHOICE%"=="1" goto build_win
if "%CHOICE%"=="2" goto build_mac
if "%CHOICE%"=="3" goto build_linux
if "%CHOICE%"=="4" goto build_all
echo Invalid choice, defaulting to Windows...
goto build_win

:build_win
echo [3/4] Building Windows installer...
call npm run build:win
if errorlevel 1 (
    echo [ERROR] Windows build failed
    pause
    exit /b 1
)
echo   Done.
goto finish

:build_mac
echo [3/4] Building macOS installer...
echo   NOTE: This must be run on a macOS machine.
call npm run build:mac
if errorlevel 1 (
    echo [ERROR] macOS build failed
    pause
    exit /b 1
)
echo   Done.
goto finish

:build_linux
echo [3/4] Building Linux installer...
echo   NOTE: This must be run on a Linux machine.
call npm run build:linux
if errorlevel 1 (
    echo [ERROR] Linux build failed
    pause
    exit /b 1
)
echo   Done.
goto finish

:build_all
echo [3/4] Building for all platforms...
echo   NOTE: macOS and Linux builds require their respective OS.
call npm run build:all
if errorlevel 1 (
    echo [ERROR] Multi-platform build failed
    pause
    exit /b 1
)
echo   Done.
goto finish

:finish
echo.
echo ======================================================
echo   BUILD COMPLETE
echo ======================================================
echo.
echo Installers are in the folder:
echo   %~dp0installers\
echo.
echo Files created:
if exist "%~dp0installers\*.exe"  echo   - VeritasSEB-Setup-*-win.exe  (Windows)
if exist "%~dp0installers\*.dmg"  echo   - VeritasSEB-*-mac.dmg         (macOS)
if exist "%~dp0installers\*.AppImage" echo - VeritasSEB-*-linux.AppImage (Linux)
if exist "%~dp0installers\*.deb"  echo   - VeritasSEB-*-linux.deb       (Ubuntu/Debian)
echo.
echo Next steps:
echo   1. Copy the installer to the exam computer.
echo   2. Run the installer (admin rights not needed, just Next-Next-Finish).
echo   3. Edit config.json to set the exam URL:
echo      - Windows: %APPDATA%\Veritas SEB Kiosk\config.json
echo      - macOS:   ~/Library/Application Support/Veritas SEB Kiosk/config.json
echo      - Linux:   ~/.config/Veritas SEB Kiosk/config.json
echo   4. The desktop shortcut will launch the browser in kiosk mode.
echo.
pause
