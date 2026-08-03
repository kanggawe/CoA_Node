#!/usr/bin/env bash

set -euo pipefail

echo "=================================================="
echo " FreeRADIUS CoA Proxy API - Uninstaller"
echo "=================================================="

if [ "$EUID" -ne 0 ]; then
    echo "[ERROR] Please run as root or sudo."
    exit 1
fi

read -p "Are you sure you want to remove FreeRADIUS CoA Proxy API from /var/www/coa-proxy? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "[INFO] Removing Nginx site configuration..."
    rm -f /etc/nginx/sites-enabled/coa-proxy.conf
    rm -f /etc/nginx/sites-available/coa-proxy.conf
    systemctl reload nginx || true

    echo "[INFO] Removing Apache site configuration..."
    rm -f /etc/apache2/sites-enabled/coa-proxy.conf || true
    rm -f /etc/apache2/sites-available/coa-proxy.conf || true

    echo "[INFO] Deleting application directory..."
    rm -rf /var/www/coa-proxy

    echo "[OK] FreeRADIUS CoA Proxy removed successfully."
else
    echo "[INFO] Uninstall cancelled."
fi
