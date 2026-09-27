#!/bin/zsh
set -euo pipefail

PROJECT_DIR="/Volumes/DATA KERJA /Project Dev/fahra-kelola/shopdash"
AGENT_DIR="$HOME/Library/LaunchAgents"
mkdir -p "$AGENT_DIR"
cp "$PROJECT_DIR/ops/com.fahra.shopdash.scheduler.plist" "$AGENT_DIR/"
cp "$PROJECT_DIR/ops/com.fahra.shopdash.worker.plist" "$AGENT_DIR/"
launchctl bootout "gui/$(id -u)/com.fahra.shopdash.scheduler" 2>/dev/null || true
launchctl bootout "gui/$(id -u)/com.fahra.shopdash.worker" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "$AGENT_DIR/com.fahra.shopdash.scheduler.plist"
launchctl bootstrap "gui/$(id -u)" "$AGENT_DIR/com.fahra.shopdash.worker.plist"
echo "Shopdash background sync agents loaded."
