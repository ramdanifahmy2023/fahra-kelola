#!/bin/zsh
set -euo pipefail

PROJECT_DIR="${0:A:h:h}"
AGENT_DIR="$HOME/Library/LaunchAgents"
PHP_BIN="$(command -v php)"
mkdir -p "$AGENT_DIR"
for agent in scheduler worker web; do
  sed \
    -e "s|__PROJECT_DIR__|$PROJECT_DIR|g" \
    -e "s|__PHP_BIN__|$PHP_BIN|g" \
    "$PROJECT_DIR/ops/com.fahra.shopdash.$agent.plist" > "$AGENT_DIR/com.fahra.shopdash.$agent.plist"
done
launchctl bootout "gui/$(id -u)/com.fahra.shopdash.scheduler" 2>/dev/null || true
launchctl bootout "gui/$(id -u)/com.fahra.shopdash.worker" 2>/dev/null || true
launchctl bootout "gui/$(id -u)/com.fahra.shopdash.web" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "$AGENT_DIR/com.fahra.shopdash.scheduler.plist"
launchctl bootstrap "gui/$(id -u)" "$AGENT_DIR/com.fahra.shopdash.worker.plist"
launchctl bootstrap "gui/$(id -u)" "$AGENT_DIR/com.fahra.shopdash.web.plist"
echo "Shopdash web and background sync agents loaded."
