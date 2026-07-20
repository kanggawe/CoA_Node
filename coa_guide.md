# Panduan Lengkap Setup CoA (Change of Authorization) dari Awal

Panduan ini menjelaskan alur konfigurasi CoA dari hulu ke hilir untuk memutus (*kick*) atau merubah *session* user PPPoE / Hotspot secara *real-time* dari aplikasi web (seperti Laravel) ke **MikroTik NAS** melalui **FreeRADIUS Server** menggunakan **Node.js API Gateway**.

---

## 🛠️ Arsitektur Aliran Data (Workflow)

```
+------------------------+      POST /api/coa/disconnect      +--------------------------+
|  Laravel / Web App     | ---------------------------------> | Node.js Gateway (P.3000) |
+------------------------+                                    +--------------------------+
                                                                            |
                                                                            | Exec radclient
                                                                            v
+------------------------+           UDP Port 3799            +--------------------------+
|  MikroTik RouterOS     | <--------------------------------- |    Linux radclient command|
+------------------------+                                    +--------------------------+
            |
            v
   [ Kick User Session ]
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

Node.js bertindak sebagai jembatan/middleware aman agar aplikasi eksternal (seperti Laravel) dapat memicu pemutusan user tanpa harus membuka akses SSH/Terminal Linux ke publik.

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

### Langkah 3: Konfigurasi Keamanan API Gateway
Edit bagian header `coa_api.js` sesuai kebutuhan server Anda:
```javascript
// Konfigurasi Keamanan
const ALLOWED_IP = "IP_SERVER_LARAVEL_ANDA"; // Ganti dengan IP Laravel Anda demi keamanan
const SECRET_TOKEN = "Ganti_Dengan_Token_Keamanan_Anda_2026";
```

### Langkah 4: Jalankan Service dengan PM2
Gunakan PM2 agar service Node.js terus berjalan di latar belakang dan berjalan otomatis saat server melakukan reboot:
```bash
sudo npm install -g pm2
pm2 start coa_api.js --name "coa-gateway"
pm2 startup
# Jalankan perintah yang dihasilkan oleh "pm2 startup" di terminal Anda
pm2 save
```

---

## 4. Pengujian & Troubleshooting

### Pengujian 1: Tes Langsung dari Terminal Linux Server
Coba putuskan koneksi user secara manual dari terminal Linux menggunakan `radclient` sebelum menembak API Node.js:
```bash
echo "User-Name=nama_username_mikrotik" | radclient -x -r 1 -t 3 IP_ROUTER_MIKROTIK:3799 disconnect 'SecretRadiusAnda'
```
* **Hasil Sukses**: Ada respon `Disconnect-ACK`.
* **Hasil Gagal**: `Disconnect-NAK` (user tidak aktif) atau *Timeout* (koneksi diblokir firewall/port 3799 tertutup).

### Pengujian 2: Tes via REST API Node.js (Postman / cURL)
Tembak endpoint Node.js menggunakan HTTP POST:
* **URL**: `http://IP_SERVER_LINUX:3000/api/coa/disconnect`
* **Headers**:
  * `Authorization`: `Bearer Ganti_Dengan_Token_Keamanan_Anda_2026`
  * `Content-Type`: `application/json`
* **JSON Body**:
  ```json
  {
    "username": "nama_username_mikrotik",
    "nas_ip": "IP_ROUTER_MIKROTIK",
    "secret": "SecretRadiusAnda"
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
