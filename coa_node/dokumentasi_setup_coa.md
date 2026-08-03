# Dokumentasi Lengkap & API Reference CoA Gateway

Dokumentasi ini merangkum proses diskusi, instalasi, dan spesifikasi endpoint API untuk mengoperasikan CoA (Change of Authorization) secara remote menggunakan Node.js dan FreeRADIUS pada server Linux Ubuntu.

---

## 📋 Ringkasan Fitur
API ini mendukung 3 tindakan utama yang dikirimkan langsung ke MikroTik secara dinamis:
1. **Disconnect (Kick)**: Memutuskan sesi aktif pengguna.
2. **Rate Limit (Ganti Kecepatan)**: Merubah bandwidth secara live tanpa memutus koneksi.
3. **Isolate (Address List)**: Memasukkan IP pengguna ke firewall address-list untuk isolasi (redirection).

---

## 🔐 Keamanan & Reverse Proxy (Nginx / Apache)
API ini sudah dilengkapi dengan pengaturan `app.set('trust proxy', true)`. Agar IP Laravel Anda dapat dideteksi dengan benar untuk validasi keamanan (`ALLOWED_IP`), silakan konfigurasikan web server Anda sebagai reverse proxy dengan aturan berikut:

### A. Konfigurasi Nginx
Tambahkan konfigurasi berikut pada blok server Nginx Anda (misal di `/etc/nginx/sites-available/default`):
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

### B. Konfigurasi Apache2
Aktifkan modul proxy terlebih dahulu:
```bash
sudo a2enmod proxy proxy_http headers
sudo systemctl restart apache2
```
Kemudian tambahkan konfigurasi berikut pada file VirtualHost Apache Anda:
```apache
<VirtualHost *:80>
    # ... konfigurasi domain Anda ...

    ProxyPreserveHost On
    ProxyPass /api/coa http://127.0.0.1:3000/api/coa
    ProxyPassReverse /api/coa http://127.0.0.1:3000/api/coa

    # Meneruskan header IP asli ke Node.js
    RequestHeader set X-Forwarded-Proto expr=%{REQUEST_SCHEME}
</VirtualHost>
```

---

## 🚀 API Reference (Endpoints)

### 1. Disconnect / Kick User
Memaksa pengguna keluar (*logout*) dari jaringannya saat ini.
* **Endpoint**: `POST /api/coa/disconnect`
* **Payload (JSON)**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret"
  }
  ```
* **Response Sukses (200 OK)**:
  ```json
  {
    "status": "success",
    "message": "Berhasil: User berhasil di-kick dari MikroTik.",
    "log": [ ... ]
  }
  ```

---

### 2. Change Speed / Rate Limit
Merubah profil kecepatan download/upload pengguna secara *real-time*.
* **Endpoint**: `POST /api/coa/rate-limit`
* **Payload (JSON)**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret",
    "rate_limit": "5M/10M"
  }
  ```
  *(Format `rate_limit`: `Upload/Download` seperti `512k/1M`, `5M/5M`, `10M/20M`)*
* **Response Sukses (200 OK)**:
  ```json
  {
    "status": "success",
    "message": "Berhasil: Kecepatan user diubah menjadi 5M/10M.",
    "log": [ ... ]
  }
  ```

---

### 3. Isolate User (Firewall Address List)
Memasukkan IP pengguna ke dalam kelompok *Address List* tertentu di firewall MikroTik. Sangat berguna untuk pengalihan laman pembayaran (isolasi bagi penunggak tagihan).
* **Endpoint**: `POST /api/coa/isolate`
* **Payload (JSON)**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret",
    "address_list": "isolasi_tagihan"
  }
  ```
* **Response Sukses (200 OK)**:
  ```json
  {
    "status": "success",
    "message": "Berhasil: IP User masuk ke address list 'isolasi_tagihan'.",
    "log": [ ... ]
  }
  ```

---

### 4. Custom RADIUS Attributes (Mendukung Semua Service)
Endpoint ini digunakan untuk mengirim atribut RADIUS secara fleksibel. Berguna untuk mengontrol service apa pun yang dicentang pada konfigurasi RADIUS di MikroTik Anda (seperti **ppp / PPPoE**, **login**, **hotspot**, **dhcp**, **wireless**, **ipsec**, dll).
* **Endpoint**: `POST /api/coa/custom`
* **Payload (JSON)**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret",
    "attributes": {
      "Session-Timeout": "7200",
      "Acct-Interim-Interval": "60",
      "Mikrotik-Rate-Limit": "10M/10M"
    }
  }
  ```
* **Response Sukses (200 OK)**:
  ```json
  {
    "status": "success",
    "message": "Berhasil: Atribut custom CoA berhasil dikirim ke MikroTik.",
    "log": [ ... ]
  }
  ```

---

## 🛠️ Langkah Penerapan Perubahan di Server

Apabila Anda melakukan perubahan kode di server, pastikan untuk memuat ulang (reload) proses PM2 agar kode baru diterapkan:

```bash
# Masuk ke folder project
cd /var/www/coa-gateway

# Upload atau update coa_api.js di server, lalu restart proses PM2
pm2 restart coa-gateway
```
*(Daftar proses PM2 dapat dipantau menggunakan perintah `pm2 status` atau `pm2 logs`)*
