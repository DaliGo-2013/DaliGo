#!/usr/bin/env bash
#
# Auto-despliegue idempotente para HostGator.
#
# Pensado para correrse desde un cron job cada 2-5 minutos.
# Hace fetch a origin/main y, SOLO si hay commits nuevos, ejecuta deploy.sh.
# En estado estable (sin cambios) sale en milisegundos sin escribir nada en
# el log, asi que se puede correr frecuentemente sin generar ruido.
#
# Registro: storage/logs/auto-deploy.log (rotacion manual si crece demasiado).
# Bloqueo:  storage/framework/auto-deploy.lock (evita ejecuciones solapadas).

set -euo pipefail
cd "$(dirname "$0")"

LOG_FILE="storage/logs/auto-deploy.log"
LOCK_FILE="storage/framework/auto-deploy.lock"

mkdir -p "$(dirname "$LOG_FILE")" "$(dirname "$LOCK_FILE")"

# Si otro auto-deploy todavia esta corriendo, salir sin hacer nada.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    exit 0
fi

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$LOG_FILE"
}

git fetch origin main --quiet

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)

if [ "$LOCAL" = "$REMOTE" ]; then
    # Sin cambios. Salida silenciosa para no llenar el log con no-ops.
    exit 0
fi

log "Cambios detectados: ${LOCAL:0:7} -> ${REMOTE:0:7}. Ejecutando deploy.sh"

if bash deploy.sh >> "$LOG_FILE" 2>&1; then
    log "Despliegue completado correctamente (HEAD=${REMOTE:0:7})"
else
    EXIT_CODE=$?
    log "ERROR: deploy.sh fallo con codigo $EXIT_CODE"
    exit "$EXIT_CODE"
fi
