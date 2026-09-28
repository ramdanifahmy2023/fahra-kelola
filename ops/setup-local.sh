#!/bin/zsh
set -euo pipefail

PROJECT_DIR="${0:A:h:h}"
ENV_FILE="$PROJECT_DIR/config/.env"
SCHEMA_FILE="$PROJECT_DIR/database/schema.sql"
MYSQL_BIN="$(command -v mariadb || command -v mysql || true)"

if [[ -z "$MYSQL_BIN" ]]; then
  echo "MariaDB/MySQL client is required. Install MariaDB or add its client to PATH." >&2
  exit 1
fi

if ! command -v php >/dev/null || ! command -v npm >/dev/null; then
  echo "PHP and Node.js/npm are required." >&2
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  cp "$PROJECT_DIR/config/.env.example" "$ENV_FILE"
fi

DB_PORT_VALUE="$(sed -n 's/^DB_PORT="\([0-9][0-9]*\)"$/\1/p' "$ENV_FILE" || true)"
DB_HOST_VALUE="$(sed -n 's/^DB_HOST="\(.*\)"$/\1/p' "$ENV_FILE" || true)"

read "ADMIN_NAME?Nama admin: "
read "ADMIN_EMAIL?Email admin: "
read -s "ADMIN_PASSWORD?Password admin (minimal 8 karakter): "
echo
read -s "MYSQL_PASSWORD?Password root MariaDB/MySQL (kosongkan jika tidak ada): "
echo

MYSQL_ARGS=(-h "${DB_HOST_VALUE:-127.0.0.1}" -P "${DB_PORT_VALUE:-3306}" -u root)
if [[ -n "$MYSQL_PASSWORD" ]]; then
  MYSQL_ARGS+=("-p$MYSQL_PASSWORD")
fi

"$MYSQL_BIN" "${MYSQL_ARGS[@]}" < "$SCHEMA_FILE"
for migration in "$PROJECT_DIR"/database/migrations/*.sql; do
  "$MYSQL_BIN" "${MYSQL_ARGS[@]}" shopdash_db < "$migration"
done
(cd "$PROJECT_DIR" && npm ci && npm run build)
php "$PROJECT_DIR/bin/create-admin.php" "$ADMIN_NAME" "$ADMIN_EMAIL" "$ADMIN_PASSWORD"

echo "Selesai. Jalankan: cd \"$PROJECT_DIR\" && ./ops/install-background-sync.sh"
