#!/bin/bash
set -e

# ===================== CONFIGURAÇÃO =====================
FTP_HOST="ftp.softseven.ao"
FTP_USER="zimboweb@api-zimboweb.softseven.ao"
FTP_PASS="+L&@0fG5{Q(cT*au"
REMOTE_PATH="/"
# =========================================================

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "[1/4] Instalando dependencias production..."
cd "$DIR"
composer install --no-dev --optimize-autoloader --no-interaction

echo "[2/4] Gerando caches Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[3/4] Enviando via FTP (lftp mirror)..."

# Cria .netrc temporário para evitar problemas com caracteres especiais na senha
NETRC_FILE=$(mktemp)
cat > "$NETRC_FILE" <<NETRC_EOF
machine $FTP_HOST
login $FTP_USER
password $FTP_PASS
NETRC_EOF
chmod 600 "$NETRC_FILE"

# Usa HOME temporário para lftp ler o .netrc
HOME_DIR=$(mktemp -d)
cp "$NETRC_FILE" "$HOME_DIR/.netrc"

HOME="$HOME_DIR" lftp -c "
  set ftp:ssl-allow yes
  set ftp:ssl-force yes
  set ftp:ssl-protect-data yes
  set ssl:verify-certificate no
  open $FTP_HOST
  mirror -R --exclude-glob-from=$DIR/.ftpignore --delete --verbose $DIR/ $REMOTE_PATH
"

# Limpeza
rm -f "$NETRC_FILE"
rm -rf "$HOME_DIR"

echo "[4/4] Deploy concluido!"

echo
echo "======================================================"
echo " Rode no terminal do cPanel (manual):"
echo "   cd /caminho/real/api-zimboweb.softseven.ao"
echo "   composer install --no-dev --optimize-autoloader"
echo "   php artisan migrate --force"
echo "   php artisan config:cache && php artisan route:cache && php artisan view:cache"
echo "   chmod -R 775 storage bootstrap/cache"
echo "======================================================"
