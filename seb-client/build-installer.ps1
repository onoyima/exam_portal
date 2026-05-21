#!/usr/bin/env pwsh
<#
.SYNOPSIS
  Builds the Veritas SEB Kiosk installer for Windows.
.DESCRIPTION
  This script installs dependencies and builds an NSIS installer for Windows.
  It must be run from the seb-client directory.
#>

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$SrcDir = Join-Path $ScriptDir "src"

Write-Host "===============================================" -ForegroundColor Cyan
Write-Host "  Veritas SEB Kiosk - Installer Builder (PS)" -ForegroundColor Cyan
Write-Host "===============================================" -ForegroundColor Cyan
Write-Host ""

# Check Node.js
$node = Get-Command node -ErrorAction SilentlyContinue
if (-not $node) {
    Write-Host "[ERROR] Node.js is not installed." -ForegroundColor Red
    Write-Host "        Download from: https://nodejs.org/ (LTS version)" -ForegroundColor Red
    exit 1
}
Write-Host "[1/3] Node.js found: $(node --version)"

# Install dependencies
Write-Host "[2/3] Installing dependencies..."
Set-Location $SrcDir
npm install
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] npm install failed" -ForegroundColor Red
    exit 1
}
Write-Host "  Done."

# Build
Write-Host "[3/3] Building Windows installer..."
npm run build:win
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Windows build failed" -ForegroundColor Red
    exit 1
}
Write-Host "  Done."

$InstallerDir = Join-Path $ScriptDir "installers"
Write-Host ""
Write-Host "===============================================" -ForegroundColor Green
Write-Host "  BUILD COMPLETE" -ForegroundColor Green
Write-Host "===============================================" -ForegroundColor Green
Write-Host ""
Write-Host "Installer created in: $InstallerDir" -ForegroundColor White
$files = Get-ChildItem -Path $InstallerDir -Filter "*.exe"
foreach ($f in $files) {
    Write-Host "  - $($f.Name)" -ForegroundColor Yellow
}
Write-Host ""
Write-Host "Next steps:" -ForegroundColor White
Write-Host "  1. Copy the .exe installer to the exam computer." -ForegroundColor White
Write-Host "  2. Run the installer (Next-Next-Finish, no admin needed)." -ForegroundColor White
Write-Host "  3. Edit config.json at: \$env:APPDATA\Veritas SEB Kiosk\config.json" -ForegroundColor White
Write-Host "  4. Launch from the desktop shortcut." -ForegroundColor White
Write-Host ""
