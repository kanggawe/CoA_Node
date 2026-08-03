#!/usr/bin/env bash

set -euo pipefail

# FreeRADIUS CoA Proxy API Installer
# Target OS: Ubuntu 24.04 LTS

echo "=================================================="
echo " FreeRADIUS CoA Proxy API - Automated Installer"
echo "=================================================="

# 1. Check Root Privileges
if [ "$EUID" -ne 0 ]; then
    echo "[ERROR] Please run this installer as root or using sudo."
    exit 1
fi

# 2. Check Ubuntu OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [ "$ID" != "ubuntu" ]; then
        echo "[WARNING] This script is optimized for Ubuntu 24.04 LTS. Detected OS: $NAME"
    fi
else
    echo "[ERROR] Cannot detect operating system."
    exit 1
fi

echo "[INFO] Updating package lists..."
apt-get update -y

# 3. Install Required Packages
echo "[INFO] Installing PHP 8.3, PHP-FPM, Nginx, and freeradius-utils..."
apt-get install -y \
    software-properties-common \
    curl \
    unzip \
    git \
    nginx \
    php8.3 \
    php8.3-cli \
    php8.3-fpm \
    php8.3-curl \
    php8.3-mbstring \
    php8.3-xml \
    freeradius-utils \
    ufw

# 4. Verify radclient Installation
RADCLIENT_PATH=$(which radclient || echo "/usr/bin/radclient")
if [ ! -x "$RADCLIENT_PATH" ]; then
    echo "[ERROR] radclient executable not found at $RADCLIENT_PATH"
    exit 1
else
    echo "[OK] radclient found at: $RADCLIENT_PATH"
fi

# 5. Create Application Directory & Deploy Files
APP_DIR="/var/www/coa-proxy"
echo "[INFO] Setting up application directory at $APP_DIR..."
mkdir -p "$APP_DIR"
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/storage/cache/rate_limit"
mkdir -p "$APP_DIR/storage/idempotency"

# Copy project files if running from installer source directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
if [ "$SCRIPT_DIR" != "$APP_DIR" ]; then
    echo "[INFO] Copying files from $SCRIPT_DIR to $APP_DIR..."
    cp -r "$SCRIPT_DIR/"* "$APP_DIR/" 2>/dev/null || true
fi

# 6. Configure Environment File (.env)
if [ ! -f "$APP_DIR/.env" ]; then
    if [ -f "$APP_DIR/.env.example" ]; then
        echo "[INFO] Generating default .env from .env.example..."
        cp "$APP_DIR/.env.example" "$APP_DIR/.env"
        
        # Generate random 32-character API token
        GEN_TOKEN=$(openssl rand -hex 16 2>/dev/null || date +%s | md5sum | head -c 32)
        sed -i "s/COA_API_TOKEN=.*/COA_API_TOKEN=$GEN_TOKEN/" "$APP_DIR/.env"
        echo "[OK] Generated secure API token in .env"
    fi
fi

# 7. Set Permissions
echo "[INFO] Setting file permissions..."
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$APP_DIR/storage"

# 8. Configure Nginx Server Block
echo "[INFO] Configuring Nginx..."
if [ -f "$APP_DIR/deploy/nginx/coa-proxy.conf" ]; then
    cp "$APP_DIR/deploy/nginx/coa-proxy.conf" /etc/nginx/sites-available/coa-proxy.conf
    ln -sf /etc/nginx/sites-available/coa-proxy.conf /etc/nginx/sites-enabled/coa-proxy.conf
    rm -f /etc/nginx/sites-enabled/default || true
    nginx -t
    systemctl reload nginx
    echo "[OK] Nginx reloaded successfully."
fi

# 9. Enable PHP-FPM
systemctl enable php8.3-fpm
systemctl restart php8.3-fpm
echo "[OK] PHP 8.3 FPM restarted."

echo ""
echo "=================================================="
echo " [SUCCESS] Installation Completed Successfully!"
echo "=================================================="
echo "Next Steps:"
echo "1. Edit .env configuration file:"
echo "   nano /var/www/coa-proxy/.env"
echo "   - Update RADIUS_SECRET"
echo "   - Update RADIUS_DEFAULT_NAS and RADIUS_ALLOWED_NAS"
echo "   - Update COA_ALLOWED_IPS (Laravel Billing Server IP)"
echo "2. Run Health Check:"
echo "   bash /var/www/coa-proxy/deploy/scripts/health-check.sh"
echo "3. Configure SSL with Certbot (recommended):"
echo "   sudo certbot --nginx -d coa.yourdomain.com"
echo "=================================================="
