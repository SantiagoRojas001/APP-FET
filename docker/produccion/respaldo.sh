#!/bin/sh
# Copia de seguridad diaria de la base de datos y de los documentos privados
# (storage/app/private). Conserva los últimos RETENCION_DIAS días.
set -eu

DESTINO=/respaldos
RETENCION_DIAS="${RETENCION_DIAS:-14}"

respaldar() {
    fecha=$(date +%Y%m%d-%H%M)
    pg_dump -h db -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc -f "$DESTINO/base-$fecha.dump"
    tar -czf "$DESTINO/documentos-$fecha.tar.gz" -C /almacenamiento private
    find "$DESTINO" -type f -mtime +"$RETENCION_DIAS" -delete
    echo "Respaldo $fecha listo."
}

while true; do
    respaldar || echo "El respaldo falló; se reintenta en la próxima ejecución." >&2
    sleep 86400
done
