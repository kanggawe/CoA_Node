# Dokumentasi Setup & Pemecahan Masalah CoA Gateway (20 Juli 2026)

Dokumentasi ini merangkum proses diskusi, instalasi, dan pemecahan masalah (troubleshooting) dalam mengimplementasikan API Gateway CoA (Change of Authorization) menggunakan Node.js dan FreeRADIUS pada server Linux Ubuntu.

---

## 📋 Ringkasan Percakapan & Solusi Masalah

### 1. Masalah: Pengujian Lokal di Windows vs Linux
* **Diskusi**: Kode pada [coa_api.js](file:///d:/BIG/CoA/coa_api.js) menggunakan program `radclient` bawaan Linux (`echo ... | radclient ...`).
* **Solusi**: Ditegaskan bahwa pengujian endpoint dan eksekusi PM2 tidak bisa dilakukan di Windows secara langsung karena ketergantungan pada tool network `radclient` milik Linux. Project ini harus dideploy di server Linux (Ubuntu/Debian).

### 2. Masalah: Error Typo File (`radlient` vs `radclient`)
* **Kasus**: Percobaan masuk ke file eksekusi menggunakan `cd /usr/bin/radlient` mengalami error.
* **Solusi**:
  * Menjelaskan bahwa nama program yang benar adalah `radclient` (memakai huruf **c**).
  * Menjelaskan bahwa `radclient` merupakan file binary/eksekusi, sehingga perintah `cd` (Change Directory) tidak dapat digunakan. Gunakan perintah `which radclient` untuk cek lokasi atau `radclient -h` untuk melihat dokumentasi instruksinya.

### 3. Masalah: Error Hak Akses `EACCES` saat install PM2
* **Kasus**: Menjalankan `npm install -g pm2` menghasilkan error permission denied.
* **Solusi**: Karena instalasi bersifat global (`-g`), NPM memerlukan hak akses administrator/root di Linux. Solusinya adalah menambahkan perintah `sudo`:
  ```bash
  sudo npm install -g pm2
  ```

---

## 🚀 Langkah Instalasi Akhir yang Berhasil Dijalankan

Berikut adalah rekapitulasi perintah yang berhasil dieksekusi di server `mitraxcon@mitraxcon`:

1. **Memasang Dependencies**:
   ```bash
   sudo apt update
   sudo apt install -y freeradius freeradius-utils
   ```
2. **Inisialisasi Project di `/var/www/coa-gateway`**:
   ```bash
   cd /var/www/coa-gateway
   npm install express
   ```
3. **Menjalankan Server API dengan PM2**:
   ```bash
   pm2 start coa_api.js --name "coa-gateway"
   ```
4. **Mengatur Autostart PM2 saat booting server**:
   ```bash
   sudo env PATH=$PATH:/usr/bin /usr/lib/node_modules/pm2/bin/pm2 startup systemd -u mitraxcon --hp /home/mitraxcon
   pm2 save
   ```

---

## 🔒 Konfigurasi Keamanan Tambahan (Langkah Berikutnya)

Sebelum sistem ini digunakan secara penuh di produksi, pastikan konfigurasi keamanan berikut disesuaikan pada file [coa_api.js](file:///d:/BIG/CoA/coa_api.js):

1. **Batasi IP Pengirim (`ALLOWED_IP`)**:
   Ganti nilai `"0.0.0.0"` dengan alamat IP server aplikasi/Laravel Anda agar port API `3000` tidak bisa ditembak oleh pihak luar yang tidak dikenal.
2. **Ganti Secret Token (`SECRET_TOKEN`)**:
   Ganti token bawaan dengan string acak yang kuat untuk autentikasi Bearer Token.
3. **Buka Port Firewall**:
   * **TCP Port 3000**: Harus terbuka di Ubuntu (untuk menerima request dari Laravel).
   * **UDP Port 3799**: Harus terbuka di MikroTik (untuk menerima sinyal CoA/Disconnect dari server Ubuntu).
