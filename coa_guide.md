# Panduan Lengkap Setup CoA (Change of Authorization) dari Awal

Panduan ini menjelaskan alur konfigurasi CoA dari hulu ke hilir untuk memutus (*kick*), merubah bandwidth (*rate limit*), atau mengisolasi (*address list*) *session* user PPPoE / Hotspot secara *real-time* dari aplikasi web (seperti Laravel) ke **MikroTik NAS** melalui **FreeRADIUS Server** menggunakan **Node.js API Gateway** (mendukung reverse proxy **Nginx** dan **Apache**).

---

## 🛠️ Arsitektur Aliran Data (Workflow)

```
+------------------------+      POST /api/coa/disconnect      +--------------------------+
|  Laravel / Web App     | ---------------------------------> | Node.js Gateway (P.3000) |
+------------------------+      POST /api/coa/rate-limit      +--------------------------+
                                POST /api/coa/isolate                     |
                                                                          | Exec radclient
                                                                          v
+------------------------+           UDP Port 3799            +--------------------------+
|  MikroTik RouterOS     | <--------------------------------- |    Linux radclient command|
+------------------------+                                    +--------------------------+
            |
            v
   [ Apply CoA Changes ]
```

---

## 1. Konfigurasi di Sisi MikroTik (NAS)

MikroTik harus dikonfigurasi agar bersedia menerima dan memproses permintaan perubahan otorisasi (CoA) dari server RADIUS.

1. Buka Winbox / Terminal MikroTik.
2. Aktifkan fitur **Radius Incoming** (CoA):
   * Melalui GUI: Masuk ke menu **Radius** -> klik tombol **Incoming**.
   * Centang **Accept**.
   * Tentukan port (default: `3799`).
3. Tambahkan konfigurasi Server RADIUS jika belum ada:
   ```routeros
   /radius incoming
   set accept=yes port=3799
   
   /radius
   add address=IP_SERVER_LINUX_FREERADIUS secret=SecretRadiusAnda service=ppp,hotspot
   ```
   *Pastikan IP Server Linux diizinkan di router MikroTik dan Port UDP `3799` tidak diblokir oleh Firewall MikroTik.*

---

## 2. Konfigurasi di Sisi FreeRADIUS Server (Linux)

Pastikan MikroTik terdaftar sebagai client di FreeRADIUS agar paket CoA yang dikirim dari FreeRADIUS dikenali oleh MikroTik.

1. Buka file `/etc/freeradius/3.0/clients.conf` (lokasi dapat bervariasi sesuai versi OS).
2. Tambahkan definisi client untuk MikroTik:
   ```nas
   client mikrotik-nas {
       ipaddr      = IP_ROUTER_MIKROTIK
       secret      = SecretRadiusAnda
       nas_type    = other
   }
   ```
3. Restart service FreeRADIUS:
   ```bash
   sudo systemctl restart freeradius
   ```

---

## 3. Setup Node.js API Gateway (`coa_api.js`)

Node.js bertindak sebagai jembatan/middleware aman agar aplikasi eksternal (seperti Laravel) dapat memicu perintah CoA ke MikroTik tanpa harus membuka akses SSH/Terminal Linux ke publik.

### Langkah 1: Install Paket Pendukung di Linux (Ubuntu/Debian)
```bash
# Update OS
sudo apt update

# Install FreeRADIUS Server dan Utility (radclient)
sudo apt install -y freeradius freeradius-utils

# Install Node.js & npm (jika belum ada)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs
```

### Langkah 2: Deploy Project Code
Buat folder project di `/var/www/coa-gateway`:
```bash
sudo mkdir -p /var/www/coa-gateway
sudo chown -R $USER:$USER /var/www/coa-gateway
cd /var/www/coa-gateway
npm init -y
npm install express
```

Simpan file `coa_api.js` di dalam folder tersebut.

### Langkah 3: Konfigurasi Keamanan & Reverse Proxy
Edit bagian header `coa_api.js` sesuai kebutuhan server Anda:
```javascript
// Konfigurasi Keamanan
const ALLOWED_IP = "IP_SERVER_LARAVEL_ANDA"; // Ganti dengan IP Laravel Anda demi keamanan
const SECRET_TOKEN = "Ganti_Dengan_Token_Keamanan_Anda_2026";
```

#### Opsi A: Konfigurasi Nginx sebagai Reverse Proxy
Agar IP asli Laravel dapat lolos dari filter `ALLOWED_IP` meskipun di belakang Nginx, tambahkan aturan berikut:
```nginx
location /api/coa/ {
    proxy_pass http://127.0.0.1:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
    
    # Meneruskan IP asli Client ke Node.js
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

#### Opsi B: Konfigurasi Apache2 sebagai Reverse Proxy
Aktifkan modul Apache proxy:
```bash
sudo a2enmod proxy proxy_http headers
sudo systemctl restart apache2
```
Tambahkan di file VirtualHost Anda:
```apache
<VirtualHost *:80>
    ProxyPreserveHost On
    ProxyPass /api/coa http://127.0.0.1:3000/api/coa
    ProxyPassReverse /api/coa http://127.0.0.1:3000/api/coa

    RequestHeader set X-Forwarded-Proto expr=%{REQUEST_SCHEME}
</VirtualHost>
```

### Langkah 4: Jalankan Service dengan PM2
Gunakan PM2 agar service Node.js terus berjalan di latar belakang:
```bash
sudo npm install -g pm2
pm2 start coa_api.js --name "coa-gateway"
pm2 startup
# Jalankan perintah yang dihasilkan oleh "pm2 startup" di terminal Anda
pm2 save
```

---

## 4. Pengujian & Troubleshooting via REST API

### 1. Endpoint: Disconnect / Kick User
* **URL**: `http://IP_SERVER:3000/api/coa/disconnect`
* **JSON Body**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret"
  }
  ```

### 2. Endpoint: Change Speed / Rate Limit
* **URL**: `http://IP_SERVER:3000/api/coa/rate-limit`
* **JSON Body**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret",
    "rate_limit": "5M/10M"
  }
  ```

### 3. Endpoint: Isolate User / Address List
* **URL**: `http://IP_SERVER:3000/api/coa/isolate`
* **JSON Body**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret",
    "address_list": "isolasi_tagihan"
  }
  ```

---

## 🛑 Troubleshooting Port & Firewall

Jika terjadi error timeout saat memproses CoA, pastikan port-port berikut terbuka:
1. **Port 3000 (TCP)**: Terbuka pada Server Linux (arah masuk dari Server Laravel).
2. **Port 3799 (UDP)**: Terbuka pada MikroTik (arah masuk dari Server Linux).

Perintah membuka port firewall di Linux (UFW):
```bash
sudo ufw allow 3000/tcp
sudo ufw allow out 3799/udp
```
