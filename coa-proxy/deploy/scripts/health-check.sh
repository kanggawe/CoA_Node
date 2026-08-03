#!/usr/bin/env bash

# FreeRADIUS CoA Proxy API Health Check Script

APP_DIR="/var/www/coa-proxy"
if [ ! -d "$APP_DIR" ]; then
    APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi

echo "=================================================="
echo " FreeRADIUS CoA Proxy API - Diagnostic Check"
echo "=================================================="

# 1. PHP Check
if command -v php >/dev/null 2>&1; then
    PHP_VER=$(php -r "echo PHP_VERSION;")
    echo "[OK] PHP installed (Version: $PHP_VER)"
else
    echo "[FAIL] PHP is not installed"
fi

# 2. PHP-FPM Service Check
if systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
    echo "[OK] PHP-FPM php8.3-fpm service is running"
else
    echo "[WARN] php8.3-fpm service is not active"
fi

# 3. Web Server Check (Nginx or Apache)
if systemctl is-active --quiet nginx 2>/dev/null; then
    echo "[OK] Nginx web server is running"
elif systemctl is-active --quiet apache2 2>/dev/null; then
    echo "[OK] Apache web server is running"
else
    echo "[WARN] Neither Nginx nor Apache is running"
fi

# 4. radclient Executable Check
RADCLIENT_BIN=$(which radclient 2>/dev/null || echo "/usr/bin/radclient")
if [ -x "$RADCLIENT_BIN" ]; then
    echo "[OK] radclient binary found and executable at: $RADCLIENT_BIN"
else
    echo "[FAIL] radclient binary NOT found or NOT executable at: $RADCLIENT_BIN"
fi

# 5. Configuration File Check (.env)
if [ -f "$APP_DIR/.env" ]; then
    echo "[OK] .env configuration file exists"
    
    # Check if defaults are changed
    if grep -q "COA_API_TOKEN=CHANGE_ME" "$APP_DIR/.env"; then
        echo "[WARN] COA_API_TOKEN is still set to default 'CHANGE_ME'"
    fi
    if grep -q "RADIUS_SECRET=CHANGE_ME" "$APP_DIR/.env"; then
        echo "[WARN] RADIUS_SECRET is still set to default 'CHANGE_ME'"
    fi
else
    echo "[FAIL] .env configuration file is missing!"
fi

# 6. Log & Storage Permission Check
LOG_FILE="$APP_DIR/storage/logs/coa.log"
LOG_DIR="$APP_DIR/storage/logs"
if [ -w "$LOG_DIR" ] || [ -w "$LOG_FILE" ]; then
    echo "[OK] Log directory is writable ($LOG_DIR)"
else
    echo "[FAIL] Log directory is NOT writable by current user ($LOG_DIR)"
fi

# 7. Network / UDP Port 3799 Reachability Check
DEFAULT_NAS=$(grep "^RADIUS_DEFAULT_NAS=" "$APP_DIR/.env" 2>/dev/null | cut -d'=' -f2 || echo "10.10.10.1")
if command -v nc >/dev/null 2>&1; then
    if nc -zvu -w 3 "$DEFAULT_NAS" 3799 2>/dev/null; then
        echo "[OK] NAS reachable via UDP 3799 ($DEFAULT_NAS)"
    else
        echo "[INFO] UDP 3799 check to $DEFAULT_NAS (UDP is stateless, verify routing/firewall)"
    fi
fi

echo "=================================================="
echo " Diagnostic Check Completed."
echo "=================================================="
