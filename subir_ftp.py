#!/usr/bin/env python3
"""
subir_ftp.py — Publica este proyecto por FTP en el servidor propio.

Uso:
    python subir_ftp.py

Sube el contenido de la carpeta del proyecto (raíz de este script) a
/Tony_Restauracion/ en myappsserver.duckdns.org, creando subcarpetas
remotas que no existan y sobrescribiendo los archivos que ya estén.
No borra nada en el servidor.

La contraseña se lee de un archivo local ".ftp_password" (una sola
línea, sin saltos ni espacios extra) que NUNCA se sube al repositorio
(ver .gitignore). Si no existe, créalo tú mismo con la contraseña real
antes de ejecutar este script.
"""

from __future__ import annotations

import fnmatch
import ftplib
import sys
from pathlib import Path

# ---------------------------------------------------------------------
#  Configuración
# ---------------------------------------------------------------------

HOST = "myappsserver.duckdns.org"
PUERTO = 21
USUARIO = "fernando"

CARPETA_LOCAL = Path(__file__).resolve().parent
CARPETA_REMOTA = "/Tony_Restauracion"

ARCHIVO_PASSWORD = CARPETA_LOCAL / ".ftp_password"

# Archivos/carpetas que jamás se tocan en el servidor (ni se suben ni se
# borran), aunque existan en local: son configuración/datos propios del
# servidor o herramientas que no forman parte del despliegue.
RUTAS_PROTEGIDAS = {
    "includes/config.php",
    "includes/personas_autorizadas.php",
    "admin/generar_hash.php",
    "herramientas",
}

# Nombres/patrones que se ignoran siempre al recorrer la carpeta local.
IGNORAR_NOMBRES = {
    ".git",
    ".github",
    ".claude",
    ".gitignore",
    ".ftp_password",
    "subir_ftp.py",
    "node_modules",
}
IGNORAR_PATRONES = ("*.zip",)


def es_ruta_protegida(ruta_relativa: str) -> bool:
    """¿Esta ruta (con '/', relativa a la raíz del proyecto) no se debe tocar?"""
    partes = ruta_relativa.split("/")
    for protegida in RUTAS_PROTEGIDAS:
        partes_protegida = protegida.split("/")
        if partes[: len(partes_protegida)] == partes_protegida:
            return True
    return False


def se_ignora(nombre: str) -> bool:
    if nombre in IGNORAR_NOMBRES:
        return True
    return any(fnmatch.fnmatch(nombre, patron) for patron in IGNORAR_PATRONES)


def leer_password() -> str:
    if not ARCHIVO_PASSWORD.is_file():
        sys.exit(
            f"ERROR: no existe {ARCHIVO_PASSWORD}.\n"
            "Crea ese archivo con la contraseña FTP (una sola línea) antes de "
            "ejecutar este script."
        )
    password = ARCHIVO_PASSWORD.read_text(encoding="utf-8").strip()
    if not password:
        sys.exit(f"ERROR: {ARCHIVO_PASSWORD} está vacío.")
    return password


def recopilar_archivos_locales() -> list[Path]:
    """Todas las rutas de archivo bajo CARPETA_LOCAL, ya filtradas."""
    archivos: list[Path] = []

    def recorrer(carpeta: Path):
        for entrada in sorted(carpeta.iterdir(), key=lambda p: p.name.lower()):
            if se_ignora(entrada.name):
                continue
            if entrada.is_dir():
                recorrer(entrada)
            elif entrada.is_file():
                archivos.append(entrada)

    recorrer(CARPETA_LOCAL)
    return archivos


def asegurar_carpeta_remota(ftp: ftplib.FTP, carpeta: str, cache: set[str]) -> None:
    """Crea (si hace falta) toda la cadena de subcarpetas remotas hasta 'carpeta'."""
    if carpeta in cache:
        return

    partes = [p for p in carpeta.split("/") if p]
    actual = ""
    for parte in partes:
        actual += "/" + parte
        if actual in cache:
            continue
        try:
            ftp.mkd(actual)
        except ftplib.error_perm as e:
            # 550 = ya existe (o no se puede crear); si ya existe no es un error.
            if not str(e).startswith("550"):
                raise
        cache.add(actual)


def main() -> None:
    password = leer_password()
    archivos = recopilar_archivos_locales()

    print(f"Conectando a {HOST}:{PUERTO} como '{USUARIO}' (modo pasivo)...")
    ftp = ftplib.FTP()
    ftp.connect(HOST, PUERTO, timeout=30)
    ftp.login(USUARIO, password)
    ftp.set_pasv(True)
    print("Conectado.\n")

    carpetas_creadas: set[str] = {""}
    subidos = 0
    omitidos_protegidos = 0
    errores: list[tuple[str, str]] = []

    try:
        for ruta_local in archivos:
            ruta_relativa = ruta_local.relative_to(CARPETA_LOCAL).as_posix()

            if es_ruta_protegida(ruta_relativa):
                omitidos_protegidos += 1
                print(f"  [protegido, no se sube] {ruta_relativa}")
                continue

            ruta_remota = f"{CARPETA_REMOTA}/{ruta_relativa}"
            carpeta_remota = ruta_remota.rsplit("/", 1)[0]

            try:
                asegurar_carpeta_remota(ftp, carpeta_remota, carpetas_creadas)
                with ruta_local.open("rb") as f:
                    ftp.storbinary(f"STOR {ruta_remota}", f)
                subidos += 1
                print(f"  [subido] {ruta_relativa}")
            except Exception as e:  # noqa: BLE001 — se quiere seguir con el resto
                errores.append((ruta_relativa, str(e)))
                print(f"  [ERROR] {ruta_relativa}: {e}")
    finally:
        try:
            ftp.quit()
        except Exception:
            ftp.close()

    print("\n" + "=" * 60)
    print("Resumen")
    print("=" * 60)
    print(f"Archivos subidos:            {subidos}")
    print(f"Protegidos (no tocados):     {omitidos_protegidos}")
    print(f"Errores:                     {len(errores)}")
    if errores:
        print("\nDetalle de errores:")
        for ruta, mensaje in errores:
            print(f"  - {ruta}: {mensaje}")
        sys.exit(1)


if __name__ == "__main__":
    main()
