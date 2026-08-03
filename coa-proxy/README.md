# FreeRADIUS CoA Proxy API

A standalone, lightweight, high-performance, security-hardened **FreeRADIUS CoA (Change of Authorization) Proxy API** built for ISP Billing Systems (e.g., Laravel 13). 

It acts as a secure middleware gateway that receives HTTPS REST API requests from your Billing system and executes RADIUS `Disconnect-Request` or `CoA-Request` directly to MikroTik NAS devices using `radclient`.

---

## 1. Architecture

```
+--------------------------+
|  Laravel 13 Billing System|
+--------------------------+
             |
             | HTTPS REST API (Bearer Token)
             v
+-------------------------------------------------------------+
|                     CoA Proxy API                           |
|                                                             |
| - PHP 8.3+ (No Heavy Framework / Zero-Dependency Runtime)  |
| - Nginx / Apache + PHP-FPM                                  |
| - Bearer Token Auth (hash_equals)                           |
| - IP Allowlist & NAS Whitelist                              |
| - Rate Limiter & Idempotency Cache                          |
| - Structured ISO-8601 Audit Logging                         |
+-------------------------------------------------------------+
             |
             | RADIUS UDP (Port 3799)
             | via radclient proc_open()
             v
+--------------------------+           +----------------------+
|       MikroTik NAS       | <-------- |      FreeRADIUS      |
|                          |   RADIUS  |  PostgreSQL/MySQL    |
| - PPPoE Active Sessions  |           +----------------------+
| - RADIUS Client          |
| - CoA / Disconnect       |
+--------------------------+
```

> [!NOTE]
> The CoA Proxy sends RADIUS packets directly to MikroTik NAS devices via `radclient`. HTTP requests do not need to route through FreeRADIUS first.

---

## 2. Requirements

- **OS**: Ubuntu 24.04 LTS (or Debian 12+)
- **PHP**: PHP 8.3+ with `cli`, `fpm`, `curl`, `mbstring`, `xml`
- **Web Server**: Nginx or Apache 2.4
- **RADIUS Tools**: `freeradius-utils` (provides `/usr/bin/radclient`)
- **Docker**: **Not required** (Native standalone PHP application)

---

## 3. Directory Structure

```
coa-proxy/
├── public/
│   └── index.php               # Single entry point
├── src/
│   ├── Env.php                 # Safe .env parser
│   ├── Router.php              # REST router
│   ├── Auth.php                # Authentication & rate limiting
│   ├── RadiusClient.php        # proc_open radclient execution
│   ├── CoAService.php          # CoA business logic & idempotency
│   ├── Validator.php           # Input payload validator
│   ├── Logger.php              # Structured logger & audit trail
│   └── Response.php           # Standardized JSON response helper
├── config/
│   ├── config.php              # Core app configuration
│   └── radius.php              # RADIUS & NAS allowlist configuration
├── storage/
│   ├── logs/
│   │   ├── coa.log             # Application log
│   │   └── audit.log           # JSON audit log
│   ├── cache/                  # Rate limiting cache
│   └── idempotency/            # Idempotency cache (TTL 60s)
├── deploy/
│   ├── nginx/
│   │   └── coa-proxy.conf      # Production Nginx config
│   ├── apache/
│   │   └── coa-proxy.conf      # Production Apache config
│   ├── systemd/
│   │   └── coa-proxy.service   # Storage maintenance service
│   └── scripts/
│       ├── install.sh          # Automated installer
│       ├── uninstall.sh        # Uninstaller
│       └── health-check.sh     # System diagnostic script
├── laravel-integration/
│   └── CoaProxyService.php     # Laravel 13 HTTP Client service
├── tests/
│   ├── AuthTest.php
│   ├── ValidationTest.php
│   ├── RadiusClientTest.php
│   ├── CoAServiceTest.php
│   ├── ApiTest.php
│   └── run_tests.php           # CLI test runner
├── .env.example
├── .gitignore
├── composer.json
├── LICENSE
└── README.md
```

---

## 4. Installation Guide (Ubuntu 24.04 LTS)

### Quick Automated Installation

Clone or download the repository to `/var/www/coa-proxy` and execute the installer:

```bash
cd /var/www/coa-proxy
sudo bash deploy/scripts/install.sh
```

The installer will:
1. Update system repositories and install PHP 8.3, PHP-FPM, Nginx, and `freeradius-utils`.
2. Verify that `/usr/bin/radclient` is executable.
3. Configure storage directories with correct `www-data` permissions.
4. Generate a `.env` file with a secure random 32-character API token.
5. Deploy and test the Nginx configuration.

---

## 5. Configuration (`.env`)

Create or update `/var/www/coa-proxy/.env`:

```ini
APP_ENV=production
APP_DEBUG=false

# Bearer Token for Laravel Billing authentication
COA_API_TOKEN=c3f91807d9f740a1b920a4511d7398ab

# Allowed Client IP Addresses (Billing Server IPs, comma-separated)
COA_ALLOWED_IPS=127.0.0.1,10.10.10.20

# RADIUS NAS Settings
RADIUS_DEFAULT_NAS=10.10.10.1
RADIUS_ALLOWED_NAS=10.10.10.1,10.10.10.2,10.10.10.3
RADIUS_COA_PORT=3799
RADIUS_SECRET=YourSuperSecretNasRadiusKey

# Executable Path & Timeout
RADCLIENT_PATH=/usr/bin/radclient
RADIUS_TIMEOUT=5

# Logging
LOG_LEVEL=info
LOG_FILE=/var/www/coa-proxy/storage/logs/coa.log
```

---

## 6. Web Server Configuration

### Nginx

Nginx virtualhost is located at `deploy/nginx/coa-proxy.conf`. To enable manually:

```bash
sudo cp deploy/nginx/coa-proxy.conf /etc/nginx/sites-available/coa-proxy.conf
sudo ln -s /etc/nginx/sites-available/coa-proxy.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Apache 2.4

Apache virtualhost is located at `deploy/apache/coa-proxy.conf`. To enable manually:

```bash
sudo cp deploy/apache/coa-proxy.conf /etc/apache2/sites-available/coa-proxy.conf
sudo a2enmod rewrite headers proxy_fcgi
sudo a2ensite coa-proxy.conf
sudo systemctl reload apache2
```

---

## 7. HTTPS SSL Setup (Let's Encrypt / Certbot)

Enforce HTTPS encryption for API security:

### Nginx
```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d coa.example.com
```

### Apache
```bash
sudo apt-get install -y certbot python3-certbot-apache
sudo certbot --apache -d coa.example.com
```

---

## 8. Firewall Configuration (UFW)

Protect your server and limit UDP 3799 traffic:

```bash
# Allow SSH & Web Ports
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Limit RADIUS CoA UDP 3799 to specific NAS IP range
sudo ufw allow proto udp from 10.10.10.1 to any port 3799

sudo ufw enable
```

---

## 9. API Reference & cURL Examples

All protected API endpoints require:
`Authorization: Bearer YOUR_COA_API_TOKEN`

Optionally pass `Idempotency-Key: <unique-uuid>` to prevent duplicate execution within 60 seconds.

### Health Check (`GET /api/health`)

```bash
curl -X GET http://localhost/api/health
```

**Response:**
```json
{
  "success": true,
  "service": "FreeRADIUS CoA Proxy",
  "status": "online",
  "version": "1.0.0",
  "checks": {
    "radclient": true,
    "configuration": true,
    "log": true
  }
}
```

---

### Disconnect Session (`POST /api/coa/disconnect`)

```bash
curl -X POST http://localhost/api/coa/disconnect \
  -H "Authorization: Bearer c3f91807d9f740a1b920a4511d7398ab" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: disconnect-user001-inv101" \
  -d '{
    "username": "user001",
    "acct_session_id": "17654321",
    "nas_ip": "10.10.10.1"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "CoA Disconnect-Request sent successfully",
  "data": {
    "username": "user001",
    "acct_session_id": "17654321",
    "nas_ip": "10.10.10.1",
    "action": "disconnect",
    "duration_ms": 42
  }
}
```

---

### Change Profile / Rate Limit (`POST /api/coa/change-profile`)

```bash
curl -X POST http://localhost/api/coa/change-profile \
  -H "Authorization: Bearer c3f91807d9f740a1b920a4511d7398ab" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "user001",
    "acct_session_id": "17654321",
    "nas_ip": "10.10.10.1",
    "rate_limit": "20M/20M"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "CoA Change-Profile sent successfully",
  "data": {
    "username": "user001",
    "acct_session_id": "17654321",
    "nas_ip": "10.10.10.1",
    "rate_limit": "20M/20M",
    "action": "change-profile",
    "duration_ms": 38
  }
}
```

---

### Generic CoA Request (`POST /api/coa/coa`)

```bash
curl -X POST http://localhost/api/coa/coa \
  -H "Authorization: Bearer c3f91807d9f740a1b920a4511d7398ab" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "user001",
    "acct_session_id": "17654321",
    "nas_ip": "10.10.10.1",
    "attributes": {
      "Mikrotik-Rate-Limit": "50M/50M",
      "Session-Timeout": "86400"
    }
  }'
```

---

## 10. Laravel 13 Integration Guide

### 1. Register Configuration in `config/services.php`:

```php
'coa_proxy' => [
    'url' => env('COA_PROXY_URL', 'https://coa.example.com'),
    'token' => env('COA_PROXY_TOKEN', ''),
    'timeout' => env('COA_PROXY_TIMEOUT', 10),
],
```

### 2. Add Environment Variables to Laravel `.env`:

```ini
COA_PROXY_URL=https://coa.example.com
COA_PROXY_TOKEN=c3f91807d9f740a1b920a4511d7398ab
```

### 3. Usage Example in Payment Webhook Controller:

```php
use App\Services\CoaProxyService;

class PaymentWebhookController extends Controller
{
    public function handlePayment(Request $request, CoaProxyService $coa)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);
        $invoice->update(['status' => 'PAID']);

        $customer = $invoice->customer;

        // Disconnect old session or update rate-limit dynamically
        $result = $coa->changeProfile(
            username: $customer->pppoe_username,
            rateLimit: $customer->package->rate_limit, // e.g. "20M/20M"
            nasIp: $customer->nas_ip
        );

        if (!$result['success']) {
            // Fallback: Disconnect session to force re-authentication
            $coa->disconnect(
                username: $customer->pppoe_username,
                nasIp: $customer->nas_ip
            );
        }

        return response()->json(['status' => 'success']);
    }
}
```

---

## 11. Troubleshooting Guide

### 1. `radclient` not found
Verify binary path:
```bash
which radclient
```
If installed outside `/usr/bin/radclient`, update `RADCLIENT_PATH` in `.env`.

### 2. UDP 3799 Reachability
Test UDP port 3799 reachability to MikroTik NAS:
```bash
nc -zvu 10.10.10.1 3799
```

### 3. Check Proxy Logs
```bash
tail -f /var/www/coa-proxy/storage/logs/coa.log
tail -f /var/www/coa-proxy/storage/logs/audit.log
```

### 4. Check Nginx / PHP-FPM Logs
```bash
sudo tail -f /var/log/nginx/coa-proxy.error.log
sudo systemctl status php8.3-fpm
```

---

## 12. Production Security Checklist

- [x] HTTPS enabled via Let's Encrypt / TLS 1.3
- [x] Strong 32+ character API token in `.env`
- [x] Bearer token verified using `hash_equals()`
- [x] IP allowlist enabled (`COA_ALLOWED_IPS`)
- [x] NAS IP whitelist enabled (`RADIUS_ALLOWED_NAS`)
- [x] Command injection prevented (`proc_open` array argument execution)
- [x] Rate limiting active (60 req/min/IP)
- [x] Request payload size limited to 64 KB
- [x] RADIUS attributes restricted to whitelist
- [x] Secrets masked in logs (`Authorization`, `RADIUS_SECRET`, `COA_API_TOKEN`)
- [x] Non-public directories (`config/`, `src/`, `.env`) blocked from web access
- [x] UFW firewall active restricting UDP 3799
- [x] `display_errors` disabled & `APP_DEBUG=false` in production
