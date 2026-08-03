// === coa_api.js | Taruh di Server 1 (FreeRADIUS - NODE.JS VERSION) ===
const express = require("express");
const { exec } = require("child_process");
const app = express();

// Middleware untuk membaca JSON body
app.use(express.json());

// Konfigurasi Keamanan
const ALLOWED_IP = "0.0.0.0"; // Ganti dengan IP Laravel kamu jika ingin diperketat
const SECRET_TOKEN = "Mitraxcon_CoA_8f2b3e9a1c6d7e4f_2026";

app.post("/api/coa/disconnect", (req, res) => {
  // 1. Deteksi IP Client (Aman untuk Nginx Reverse Proxy / Cloudflare)
  let clientIp = req.headers["x-forwarded-for"] || req.socket.remoteAddress;
  if (clientIp.includes(",")) {
    clientIp = clientIp.split(",")[0].trim();
  }
  // Membersihkan format IPv6 mapping jika ada (misal ::ffff:127.0.0.1)
  if (clientIp.startsWith("::ffff:")) {
    clientIp = clientIp.replace("::ffff:", "");
  }

  // 2. Validasi Akses IP
  if (ALLOWED_IP !== "0.0.0.0" && clientIp !== ALLOWED_IP) {
    return res.status(403).json({
      status: "error",
      message: `Akses ditolak. IP ${clientIp} tidak diizinkan.`,
    });
  }

  // 3. Validasi Bearer Token
  const authHeader = req.headers["authorization"];
  if (!authHeader || authHeader !== `Bearer ${SECRET_TOKEN}`) {
    return res.status(401).json({
      status: "error",
      message: "Token Otentikasi (Bearer Token) Salah atau Tidak Ditemukan!",
    });
  }

  // 4. Validasi Input Body
  const { username, nas_ip, secret } = req.body;
  if (!username || !nas_ip || !secret) {
    return res.status(400).json({
      status: "error",
      message: "Gagal, butuh variabel: username, nas_ip, secret.",
    });
  }

  // 5. Validasi Format IP NAS (Regex Keamanan)
  const ipRegex =
    /^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/;
  if (!ipRegex.test(nas_ip)) {
    return res.status(400).json({
      status: "error",
      message: "Format NAS IP tidak valid.",
    });
  }

  // 6. Sanitasi Input untuk Shell Command (Mencegah Command Injection)
  // Di Node.js kita bersihkan karakter berbahaya karena tidak ada fungsi "escapeshellarg" bawaan
  const cleanUsername = username.replace(/[`"'$()&;|<>]/g, "");
  const cleanSecret = secret.replace(/[`"'$()&;|<>]/g, "");
  const nasTarget = `${nas_ip}:3799`;

  // 7. Menyusun Perintah radclient
  const cmd = `echo "User-Name=${cleanUsername}" | radclient -x -r 1 -t 3 ${nasTarget} disconnect '${cleanSecret}' 2>&1`;

  // 8. Eksekusi Perintah ke Sistem Linux
  exec(cmd, (error, stdout, stderr) => {
    const outputText = stdout || stderr || "";
    const logOutput = outputText
      .split("\n")
      .filter((line) => line.trim() !== "");

    if (outputText.includes("Disconnect-ACK")) {
      return res.status(200).json({
        status: "success",
        message: "Berhasil: User berhasil di kick dari MikroTik.",
        log: logOutput,
      });
    } else if (outputText.includes("Disconnect-NAK")) {
      return res.status(400).json({
        status: "error",
        message:
          "Gagal: Ditolak oleh MikroTik (kemungkinan user sudah offline/tidak ada).",
        log: logOutput,
      });
    } else {
      return res.status(500).json({
        status: "error",
        message:
          "Error Jaringan atau Koneksi terputus ke MikroTik (Port tutup/Secret salah).",
        log: logOutput,
      });
    }
  });
});

// Jalankan server di port 3000 (atau sesuaikan)
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`CoA API Gateway running on port ${PORT}`);
});
