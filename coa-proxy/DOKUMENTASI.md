# Dokumentasi Lengkap FreeRADIUS CoA Proxy API

Sistem middleware REST API berbasis PHP 8.3+ untuk menjembatani Sistem Billing ISP (Laravel 13) dengan router MikroTik NAS menggunakan paket RADIUS CoA (Change of Authorization) dan Disconnect-Request via binary `radclient`.

---

## DAFTAR ISI
1. [Latar Belakang & Konsep Arsitektur](#1-latar-belakang--konsep-arsitektur)
2. [Fitur Keamanan Utama](#2-fitur-keamanan-utama)
3. [Spesifikasi System & Dependency](#3-spesifikasi-system--dependency)
4. [Panduan Instalasi di Ubuntu 24.04 LTS](#4-panduan-instalasi-di-ubuntu-2404-lts)
5. [Konfigurasi Environment (.env)](#5-konfigurasi-environment-env)
6. [Panduan Web Server (Nginx & Apache)](#6-panduan-web-server-nginx--apache)
7. [Pengaturan Firewall (UFW) & HTTPS SSL](#7-pengaturan-firewall-ufw--https-ssl)
8. [Dokumentasi API & Contoh cURL](#8-dokumentasi-api--contoh-curl)
9. [Panduan Integrasi Laravel 13 Billing](#9-panduan-integrasi-laravel-13-billing)
10. [Konfigurasi Router MikroTik NAS](#10-konfigurasi-router-mikrotik-nas)
11. [Troubleshooting & Diagnosa](#11-troubleshooting--diagnosa)

---

## 1. LATAR BELAKANG & KONSEP ARSITEKTUR

### Mengapa Membutuhkan CoA Proxy?
Pada arsitektur jaringan ISP berbasis PPPoE:
1. **FreeRADIUS** bertindak sebagai server otentikasi (Auth) dan pencatatan sesi pengguna (*Accounting* / `radacct`).
2. Ketika pelanggan **membayar tagihan**, **ganti paket bandwidth**, atau **terisolasi karena menunggak**, sistem Billing (Laravel 13) perlu mengubah status koneksi pelanggan yang sedang aktif secara *real-time*.
3. Mengirim perintah RADIUS paket langsung dari aplikasi web Laravel sering kali tidak aman dan lambat (membutuhkan permission shell khusus pada web server billing).

**CoA Proxy API** hadir sebagai **middleware/gateway terisolasi** yang menerima REST API via HTTPS dari Laravel Billing, memvalidasi request, lalu mengeksekusi paket RADIUS `CoA-Request` atau `Disconnect-Request` langsung ke router MikroTik NAS via socket UDP port 3799 menggunakan binary `radclient`.

### Diagram Alur Data

```
+--------------------------+
|  Laravel 13 Billing System|
+--------------------------+
             |
             | 1. Request REST API (HTTPS + Bearer Token)
             v
+-------------------------------------------------------------+
|                     CoA Proxy API                           |
|  (PHP 8.3+ Standalone / Zero-Dependency Runtime / Nginx)    |
|                                                             |
|  - Validasi Token (hash_equals)                             |
|  - IP Allowlist Client & NAS IP Whitelist                   |
|  - Rate Limiter (60 req/min/IP) & Idempotency Key           |
|  - Subprocess Execution (proc_open) -> radclient            |
+-------------------------------------------------------------+
             |
             | 2. Packet UDP RADIUS Port 3799 (Disconnect / CoA)
             v
+--------------------------+           +----------------------+
|       MikroTik NAS       | <-------- |      FreeRADIUS      |
|  (PPPoE Server Active)   |   RADIUS  |  PostgreSQL / MySQL  |
+--------------------------+           +----------------------+
```

> **Catatan Penting:** CoA Proxy **TIDAK** perlu mengirim HTTP Request ke FreeRADIUS terlebih dahulu. CoA Proxy langsung mengirimkan paket UDP RADIUS ke MikroTik NAS target.

---

## 2. FITUR KEAMANAN UTAMA

1. **Proteksi Command Injection 100%**
   Proses pembentukan perintah `radclient` **tidak menggunakan** `shell_exec()`, `system()`, atau penggabungan string shell command. Panggilan menggunakan PHP `proc_open()` dengan *array parameters*:
   `[$radclientPath, '-x', "$nasIp:$port", $action, $secret]`
   Atribut RADIUS dialirkan secara aman melalui STDIN stream (`pipes[0]`).

2. **Autentikasi Bearer Token Aman**
   String token dibandingkan menggunakan fungsi `hash_equals()` untuk mencegah celah *timing attack*.

3. **Restriksi IP Berlapis (Client & NAS IP Whitelist)**
   - `COA_ALLOWED_IPS`: Hanya server Billing Laravel yang diizinkan memanggil API.
   - `RADIUS_ALLOWED_NAS`: Mencegah API consumer mengirimkan paket RADIUS ke IP arbitrer yang tidak terdaftar.

4. **Rate Limiting & Batas Ukuran Request**
   - Batas request default 60 request/menit/IP (`429 Too Many Requests`).
   - Ukuran payload JSON maksimal 64 KB (`413 Payload Too Large`).

5. **Whitelist Atribut RADIUS (Prevent Attribute Injection)**
   Atribut RADIUS pada endpoint generic CoA dibatasi hanya untuk atribut terdaftar:
   `User-Name`, `Acct-Session-Id`, `Mikrotik-Rate-Limit`, `Mikrotik-Address-List`, `Session-Timeout`, `Idle-Timeout`.

6. **Redaksi Log Otomatis**
   Informasi sensitif seperti `COA_API_TOKEN`, `RADIUS_SECRET`, dan `Authorization` header tidak akan pernah tercatat ke dalam log file.

7. **Dukungan Idempotency Key**
   Menyediakan header `Idempotency-Key` (TTL 60 detik) untuk mencegah pengiriman ulang paket RADIUS ganda saat terjadi jaringan mati (*network retry*).

---

## 3. SPESIFIKASI SYSTEM & DEPENDENCY

- **OS Target**: Ubuntu 24.04 LTS
- **PHP Version**: PHP 8.3+ (Ext: `cli`, `fpm`, `curl`, `mbstring`, `xml`)
- **Web Server**: Nginx 1.24+ atau Apache 2.4+
- **RADIUS Client Utility**: `freeradius-utils` (`/usr/bin/radclient`)
- **Tanpa Docker & Tanpa Heavy Framework** (Pure Standalone PHP)

---

## 4. PANDUAN INSTALASI DI UBUNTU 24.04 LTS

### Langkah 1: Download / Clone Project
Clone atau tempatkan source code pada direktori `/var/www/coa-proxy`:

```bash
sudo mkdir -p /var/www/coa-proxy
cd /var/www/coa-proxy
```

### Langkah 2: Jalankan Script Installer Otomatis
Jalankan script installer yang sudah disediakan:

```bash
sudo bash deploy/scripts/install.sh
```

Script ini secara otomatis akan:
- Memasang `php8.3`, `php8.3-fpm`, `nginx`, `freeradius-utils`, dan `ufw`.
- Memeriksa ketersediaan executable `/usr/bin/radclient`.
- Membuat direktori `storage/logs`, `storage/cache`, dan `storage/idempotency`.
- Mengatur hak akses kepemilikan user `www-data:www-data`.
- Meng-generate file `.env` dengan *random 32-character API token*.
- Memasang dan mengaktifkan virtualhost Nginx.

---

## 5. KONFIGURASI ENVIRONMENT (.ENV)

Edit file `.env` di `/var/www/coa-proxy/.env`:

```ini
APP_ENV=production
APP_DEBUG=false

# Token Autentikasi untuk Laravel Billing
COA_API_TOKEN=c3f91807d9f740a1b920a4511d7398ab

# IP Server Billing Laravel (Pisahkan dengan koma)
COA_ALLOWED_IPS=127.0.0.1,10.10.10.20

# Konfigurasi MikroTik NAS & RADIUS
RADIUS_DEFAULT_NAS=10.10.10.1
RADIUS_ALLOWED_NAS=10.10.10.1,10.10.10.2,10.10.10.3
RADIUS_COA_PORT=3799
RADIUS_SECRET=KunciSharedSecretRadiusMikrotik Anda

# Path Binary radclient & Timeout
RADCLIENT_PATH=/usr/bin/radclient
RADIUS_TIMEOUT=5

# Logging
LOG_LEVEL=info
LOG_FILE=/var/www/coa-proxy/storage/logs/coa.log
```

---

## 6. PANDUAN WEB SERVER (NGINX & APACHE)

### Konfigurasi Nginx (`/etc/nginx/sites-available/coa-proxy.conf`)

File konfigurasi otomatis terpasang pada `deploy/nginx/coa-proxy.conf`:

```nginx
limit_req_zone $binary_remote_addr zone=coa_api_limit:10m rate=60r/m;

server {
    listen 80;
    server_name coa.example.com;

    root /var/www/coa-proxy/public;
    index index.php;

    client_max_body_size 64k;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "no-referrer" always;

    limit_req zone=coa_api_limit burst=10 nodelay;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }

    location ~ ^/(config|src|storage|deploy|tests|vendor)/ {
        deny all;
        return 404;
    }
}
```

---

## 7. PENGATURAN FIREWALL (UFW) & HTTPS SSL

### 1. UFW Firewall Setup

```bash
# Izinkan akses HTTP & HTTPS Web Server
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Izinkan Traffic UDP Port 3799 dari CoA Proxy ke MikroTik NAS
sudo ufw allow proto udp from 10.10.10.1 to any port 3799

sudo ufw enable
```

### 2. HTTPS SSL (Let's Encrypt / Certbot)

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d coa.example.com
```

---

## 8. DOKUMENTASI API & CONTOH cURL

Semua request wajib menyertakan header:
`Authorization: Bearer YOUR_COA_API_TOKEN`

### Endpoint 1: Health Check (`GET /api/health`)

**Request:**
```bash
curl -X GET http://localhost/api/health
```

**Response Success (200 OK):**
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

### Endpoint 2: Disconnect Session (`POST /api/coa/disconnect`)

Memutuskan koneksi PPPoE aktif pelanggan.

**Request Body:**
```json
{
  "username": "user001",
  "acct_session_id": "17654321",
  "nas_ip": "10.10.10.1"
}
```

**cURL Example:**
```bash
curl -X POST http://localhost/api/coa/disconnect \
  -H "Authorization: Bearer c3f91807d9f740a1b920a4511d7398ab" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: disconnect-user001-inv1001" \
  -d '{
    "username": "user001",
    "acct_session_id": "17654321",
    "nas_ip": "10.10.10.1"
  }'
```

**Response Success (200 OK):**
```json
{
  "success": true,
  "message": "CoA Disconnect-Request sent successfully",
  "data": {
    "username": "user001",
    "acct_session_id": "17654321",
    "nas_ip": "10.10.10.1",
    "action": "disconnect",
    "duration_ms": 45
  }
}
```

---

### Endpoint 3: Change Profile / Rate-Limit (`POST /api/coa/change-profile`)

Mengubah kecepatan paket internet pelanggan yang sedang aktif tanpa memutuskan koneksi.

**Request Body:**
```json
{
  "username": "user001",
  "acct_session_id": "17654321",
  "nas_ip": "10.10.10.1",
  "rate_limit": "20M/20M"
}
```

**cURL Example:**
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

---

### Endpoint 4: Generic CoA Request (`POST /api/coa/coa`)

Mengirim request CoA dengan atribut RADIUS khusus dari whitelist.

**Request Body:**
```json
{
  "username": "user001",
  "acct_session_id": "17654321",
  "nas_ip": "10.10.10.1",
  "attributes": {
    "Mikrotik-Rate-Limit": "50M/50M",
    "Session-Timeout": "86400"
  }
}
```

---

## 9. PANDUAN INTEGRASI LARAVEL 13 BILLING

### Step 1: Daftarkan Service di Laravel `config/services.php`

```php
'coa_proxy' => [
    'url' => env('COA_PROXY_URL', 'https://coa.example.com'),
    'token' => env('COA_PROXY_TOKEN', ''),
    'timeout' => env('COA_PROXY_TIMEOUT', 10),
],
```

### Step 2: Tambahkan Environment Variable di Laravel `.env`

```ini
COA_PROXY_URL=https://coa.example.com
COA_PROXY_TOKEN=c3f91807d9f740a1b920a4511d7398ab
```

### Step 3: Gunakan Service Pada Webhook Billing ISP

```php
namespace App\Http\Controllers;

use App\Services\CoaProxyService;
use App\Models\Invoice;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function handlePaymentGatewayWebhook(Request $request, CoaProxyService $coa)
    {
        $invoice = Invoice::where('payment_ref', $request->ref_number)->firstOrFail();
        $invoice->update(['status' => 'PAID']);

        $customer = $invoice->customer;

        // Use Case 1: Pelanggan Bayar -> Ubah Rate Limit ke Kecepatan Normal
        $response = $coa->changeProfile(
            username: $customer->pppoe_username,
            rateLimit: $customer->package->rate_limit, // contoh: "20M/20M"
            nasIp: $customer->nas_ip
        );

        // Jika Gagal CoA Change-Profile -> Disconnect Paksa agar Re-Auth
        if (!$response['success']) {
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

## 10. KONFIGURASI ROUTER MIKROTIK NAS

Agar MikroTik NAS mau menerima paket CoA / Disconnect-Request dari CoA Proxy:

1. Buka MikroTik WinBox / Terminal.
2. Masuk ke menu `/radius incoming`.
3. Aktifkan **CoA Incoming**:
   ```routeros
   /radius incoming set accept=yes port=3799
   ```
4. Pastikan IP CoA Proxy terdaftar pada daftar `/radius` server di MikroTik dengan `secret` yang sama dengan `RADIUS_SECRET` di `.env` Proxy.

### Pengujian CLI Manual di Server Proxy
```bash
echo 'User-Name = "user001"' | \
radclient -x 10.10.10.1:3799 disconnect "KunciSharedSecretRadiusMikrotik"
```

---

## 11. TROUBLESHOOTING & DIAGNOSA

### 1. Menjalankan Diagnosa Otomatis
```bash
bash deploy/scripts/health-check.sh
```

### 2. Memeriksa Port UDP 3799 ke MikroTik NAS
```bash
nc -zvu 10.10.10.1 3799
```

### 3. Memantau File Log Real-time
```bash
tail -f /var/www/coa-proxy/storage/logs/coa.log
tail -f /var/www/coa-proxy/storage/logs/audit.log
```

### 4. Memeriksa Log Nginx Error
```bash
sudo tail -f /var/log/nginx/coa-proxy.error.log
```
