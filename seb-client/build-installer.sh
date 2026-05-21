#!/usr/bin/env bash
# Veritas SEB Kiosk - Cross-Platform Installer Builder (macOS/Linux)
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/src"

echo "==============================================="
echo "  Veritas SEB Kiosk - Installer Builder"
echo "  For macOS and Linux"
echo "==============================================="
echo ""

# Check Node.js
if ! command -v node &>/dev/null; then
    echo "[ERROR] Node.js is not installed."
    echo "        Install from: https://nodejs.org/ (LTS version)"
    exit 1
fi
echo "[1/3] Node.js found: $(node --version)"

# Install dependencies
echo "[2/3] Installing dependencies..."
npm install
echo "  Done."

# Build
echo "[3/3] Building installer..."

OS="$(uname -s)"
case "$OS" in
    Darwin)
        echo "  Building for macOS..."
        npm run build:mac
        ;;
    Linux)
        echo "  Building for Linux..."
        npm run build:linux
        ;;
    *)
        echo "[ERROR] Unsupported OS: $OS"
        exit 1
        ;;
esac

echo ""
echo "==============================================="
echo "  BUILD COMPLETE"
echo "==============================================="
echo ""
echo "Installers are in: $SCRIPT_DIR/installers/"
echo ""
case "$OS" in
    Darwin)
        echo "  - VeritasSEB-*-mac.dmg"
        ;;
    Linux)
        echo "  - VeritasSEB-*-linux.AppImage"
        echo "  - VeritasSEB-*-linux.deb"
        ;;
esac
echo ""
echo "Next steps:"
echo "  1. Copy the installer to the exam computer."
echo "  2. Run the installer."
echo "  3. Edit config.json to set the exam URL:"
case "$OS" in
    Darwin)
        echo "     ~/Library/Application Support/Veritas SEB Kiosk/config.json"
        ;;
    Linux)
        echo "     ~/.config/Veritas SEB Kiosk/config.json"
        ;;
esac
echo "  4. Launch the app from the Applications menu or desktop shortcut."
echo ""
