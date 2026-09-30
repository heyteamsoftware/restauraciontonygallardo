#!/bin/bash
# Copia diaria de la base de datos (se instala una vez en el cron del servidor).
# Credenciales en ~/.my.cnf-tony (permisos 600), nunca en este archivo.
set -euo pipefail
DESTINO="$HOME/backups/tony_restauracion"
mkdir -p "$DESTINO"
mysqldump --defaults-extra-file="$HOME/.my.cnf-tony" --single-transaction tony_restauracion \
  | gzip > "$DESTINO/tony_$(date +%Y-%m-%d_%H%M).sql.gz"
find "$DESTINO" -name 'tony_*.sql.gz' -mtime +14 -delete
