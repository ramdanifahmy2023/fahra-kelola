#!/bin/sh
set -eu
if [ "${1:-}" != "--install" ]; then
  printf '%s\n' 'Usage: ./ops/install-boost-worker.sh --install' 'Installs only the Boost worker for this checkout. Apply its migration first.'
  exit 1
fi
boost_project=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
boost_php=$(command -v php)
boost_label=com.fahra.shopdash.boost
boost_domain="gui/$(id -u)"
if launchctl print "$boost_domain/$boost_label" >/dev/null 2>&1; then
  printf '%s\n' 'A Boost worker is already installed. Inspect its checkout before replacing it.' >&2
  exit 1
fi
cd "$boost_project"
"$boost_php" bin/boost-worker.php --dry-run >/dev/null
boost_plist="$HOME/Library/LaunchAgents/$boost_label.plist"
if [ -e "$boost_plist" ]; then
  printf '%s\n' 'An existing Boost service definition needs review; it will not be overwritten.' >&2
  exit 1
fi
mkdir -p "$HOME/Library/LaunchAgents"
python3 - "$boost_plist" "$boost_project" "$boost_php" <<'PY'
import plistlib, sys
target, project, php = sys.argv[1:]
with open(target, 'xb') as stream:
    plistlib.dump({
        'Label': 'com.fahra.shopdash.boost',
        'ProgramArguments': [php, project + '/bin/boost-worker.php', '--once', '--limit=5'],
        'WorkingDirectory': project,
        'RunAtLoad': True,
        'StartInterval': 60,
        'StandardOutPath': '/tmp/shopdash-boost.log',
        'StandardErrorPath': '/tmp/shopdash-boost.error.log',
    }, stream)
PY
launchctl bootstrap "$boost_domain" "$boost_plist"
printf '%s\n' 'Boost worker installed. Sending still requires BOOST_SEND_ENABLED=1 and an enabled shop profile.'
