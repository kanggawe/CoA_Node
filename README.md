# CoA API Gateway for FreeRADIUS & MikroTik (Node.js Edition)

[![Node.js Version](https://img.shields.io/badge/node-%3E%3D%2018.x-blue.svg)](https://nodejs.org)
[![Platform](https://img.shields.io/badge/platform-linux-lightgrey.svg)](https://ubuntu.com)
[![Express](https://img.shields.io/badge/express-v4.x-green.svg)](https://expressjs.com)

**CoA API Gateway** adalah sebuah microservice berbasis Node.js yang bertindak sebagai jembatan/middleware aman untuk melakukan operasi **Change of Authorization (CoA)** dan **Packet of Disconnect (PoD)** pada router **MikroTik NAS** secara instan dari aplikasi web (seperti Laravel, Django, dll) melalui utilitas **FreeRADIUS (`radclient`)**.

---

## 🚀 Fitur Utama
* 🔌 **Disconnect (Kick User)**: Memutuskan sesi pengguna PPPoE/Hotspot secara *real-time*.
* ⚡ **Dynamic Rate Limiting**: Mengubah bandwidth upload/download secara *live* tanpa memutus koneksi pengguna.
* 🛡️ **User Isolation (Address List)**: Memasukkan IP pengguna secara otomatis ke grup firewall address list untuk keperluan isolasi tagihan.
* 🔒 **Security Headers**: Dilengkapi filter IP Client (`ALLOWED_IP`) dan otorisasi keamanan menggunakan Bearer Token.
* 🌐 **Proxy Ready**: Mendukung deteksi IP asli di belakang reverse proxy **Nginx** dan **Apache2** (`trust proxy` enabled).

---

## 🛠️ Persyaratan Sistem
* **OS**: Linux (Ubuntu 20.04/22.04/24.04 LTS direkomendasikan).
* **Software**:
  * Node.js v18.x atau lebih baru.
  * FreeRADIUS Utilities (`freeradius-utils` / `radclient`).

---

## 📦 Panduan Memulai Cepat (Quick Start)

### 1. Instalasi Dependencies
```bash
sudo apt update
sudo apt install -y freeradius-utils nodejs npm
```

### 2. Kloning & Install Modul Proyek
```bash
# Salin file proyek ke direktori kerja Anda (misal /var/www/coa-gateway)
cd /var/www/coa-gateway
npm install
```

### 3. Konfigurasi Keamanan (`coa_api.js`)
Edit parameter keamanan di bagian atas file `coa_api.js`:
```javascript
const ALLOWED_IP = "IP_SERVER_LARAVEL_ANDA"; // Masukkan IP Laravel Anda
const SECRET_TOKEN = "TOKEN_BEARER_ANDA_2026"; // Ganti dengan token yang aman
```

### 4. Jalankan dengan PM2
```bash
sudo npm install -g pm2
pm2 start coa_api.js --name "coa-gateway"
pm2 save
```

---

## 🔌 Dokumentasi API (Endpoints)

Semua request memerlukan Header Autentikasi:
```http
Authorization: Bearer <SECRET_TOKEN>
Content-Type: application/json
```

### 1. Kick / Disconnect User
* **Endpoint**: `POST /api/coa/disconnect`
* **JSON Body**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret"
  }
  ```

### 2. Ganti Kecepatan (Rate Limit)
* **Endpoint**: `POST /api/coa/rate-limit`
* **JSON Body**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret",
    "rate_limit": "5M/10M"
  }
  ```

### 3. Isolasi User (Firewall Address List)
* **Endpoint**: `POST /api/coa/isolate`
* **JSON Body**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret",
    "address_list": "isolasi_tagihan"
  }
  ```

### 4. Custom Attributes (Mendukung ppp, login, dhcp, hotspot, dll.)
* **Endpoint**: `POST /api/coa/custom`
* **JSON Body**:
  ```json
  {
    "username": "budi_pppoe",
    "nas_ip": "192.168.88.1",
    "secret": "mikrotik_coa_secret",
    "attributes": {
      "Session-Timeout": "7200",
      "Mikrotik-Rate-Limit": "10M/10M"
    }
  }
  ```

---

## 📖 Dokumentasi Lengkap
Untuk panduan detail tentang konfigurasi sisi **MikroTik**, **FreeRADIUS config (`clients.conf`)**, dan panduan setup **Nginx/Apache reverse proxy**, silakan merujuk pada:
* 📄 **[coa_guide.md](file:///d:/BIG/CoA/coa_guide.md)** — Panduan konfigurasi infrastruktur lengkap.
* 📄 **[dokumentasi_setup_coa.md](file:///d:/BIG/CoA/dokumentasi_setup_coa.md)** — Referensi teknis API & Troubleshooting.

---

## 📝 Lisensi
Proyek ini dilisensikan di bawah Lisensi MIT.
