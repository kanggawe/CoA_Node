// === coa_api.js | Taruh di Server 1 (FreeRADIUS - NODE.JS VERSION) ===
const express = require("express");
const { exec } = require("child_process");
const app = express();

// Mengaktifkan trust proxy agar Express dapat membaca IP asli client
// dari reverse proxy (Nginx / Apache / Cloudflare)
app.set("trust proxy", true);

// Middleware untuk membaca JSON body
app.use(express.json());

// Konfigurasi Keamanan
const ALLOWED_IP = "0.0.0.0"; // Ganti dengan IP Laravel kamu jika ingin diperketat
const SECRET_TOKEN = "Mitraxcon_CoA_8f2b3e9a1c6d7e4f_2026";

// Helper function untuk validasi dasar request
function validateBasicRequest(req, res) {
  // 1. Deteksi IP Client (Menggunakan req.ip karena trust proxy sudah aktif)
  let clientIp = req.ip;
  if (clientIp.startsWith("::ffff:")) {
    clientIp = clientIp.replace("::ffff:", "");
  }

  // 2. Validasi Akses IP
  if (ALLOWED_IP !== "0.0.0.0" && clientIp !== ALLOWED_IP) {
    res.status(403).json({
      status: "error",
      message: `Akses ditolak. IP ${clientIp} tidak diizinkan.`,
    });
    return false;
  }

  // 3. Validasi Bearer Token
  const authHeader = req.headers["authorization"];
  if (!authHeader || authHeader !== `Bearer ${SECRET_TOKEN}`) {
    res.status(401).json({
      status: "error",
      message: "Token Otentikasi (Bearer Token) Salah atau Tidak Ditemukan!",
    });
    return false;
  }

  return true;
}

// Helper untuk sanitasi input
function sanitize(input) {
  if (!input) return "";
  return String(input).replace(/[`"'$()&;|<>]/g, "");
}

// Helper untuk validasi IP NAS
function isValidIp(ip) {
  const ipRegex =
    /^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/;
  return ipRegex.test(ip);
}

// ----------------------------------------------------
// 1. ENDPOINT: Disconnect / Kick User (Tipe: disconnect)
// ----------------------------------------------------
app.post("/api/coa/disconnect", (req, res) => {
  if (!validateBasicRequest(req, res)) return;

  const { username, nas_ip, secret } = req.body;
  if (!username || !nas_ip || !secret) {
    return res.status(400).json({
      status: "error",
      message: "Gagal, butuh variabel: username, nas_ip, secret.",
    });
  }

  if (!isValidIp(nas_ip)) {
    return res.status(400).json({ status: "error", message: "Format NAS IP tidak valid." });
  }

  const cleanUsername = sanitize(username);
  const cleanSecret = sanitize(secret);
  const nasTarget = `${nas_ip}:3799`;

  const cmd = `echo "User-Name=${cleanUsername}" | radclient -x -r 1 -t 3 ${nasTarget} disconnect '${cleanSecret}' 2>&1`;

  exec(cmd, (error, stdout, stderr) => {
    const outputText = stdout || stderr || "";
    const logOutput = outputText.split("\n").filter((line) => line.trim() !== "");

    if (outputText.includes("Disconnect-ACK")) {
      return res.status(200).json({
        status: "success",
        message: "Berhasil: User berhasil di-kick dari MikroTik.",
        log: logOutput,
      });
    } else {
      return res.status(400).json({
        status: "error",
        message: "Gagal memutus user (Disconnect-NAK atau Timeout).",
        log: logOutput,
      });
    }
  });
});

// ----------------------------------------------------
// 2. ENDPOINT: Change Speed / Rate Limit (Tipe: coa)
// ----------------------------------------------------
app.post("/api/coa/rate-limit", (req, res) => {
  if (!validateBasicRequest(req, res)) return;

  const { username, nas_ip, secret, rate_limit } = req.body;
  if (!username || !nas_ip || !secret || !rate_limit) {
    return res.status(400).json({
      status: "error",
      message: "Gagal, butuh variabel: username, nas_ip, secret, rate_limit (contoh: 5M/5M).",
    });
  }

  if (!isValidIp(nas_ip)) {
    return res.status(400).json({ status: "error", message: "Format NAS IP tidak valid." });
  }

  const cleanUsername = sanitize(username);
  const cleanSecret = sanitize(secret);
  const cleanRate = sanitize(rate_limit);
  const nasTarget = `${nas_ip}:3799`;

  // Menggunakan tipe 'coa' dan menyertakan Mikrotik-Rate-Limit
  const cmd = `echo "User-Name=${cleanUsername}, Mikrotik-Rate-Limit=${cleanRate}" | radclient -x -r 1 -t 3 ${nasTarget} coa '${cleanSecret}' 2>&1`;

  exec(cmd, (error, stdout, stderr) => {
    const outputText = stdout || stderr || "";
    const logOutput = outputText.split("\n").filter((line) => line.trim() !== "");

    if (outputText.includes("CoA-ACK")) {
      return res.status(200).json({
        status: "success",
        message: `Berhasil: Kecepatan user diubah menjadi ${cleanRate}.`,
        log: logOutput,
      });
    } else {
      return res.status(400).json({
        status: "error",
        message: "Gagal mengubah kecepatan user (CoA-NAK atau Timeout).",
        log: logOutput,
      });
    }
  });
});

// ----------------------------------------------------
// 3. ENDPOINT: Isolate User / Address List (Tipe: coa)
// ----------------------------------------------------
app.post("/api/coa/isolate", (req, res) => {
  if (!validateBasicRequest(req, res)) return;

  const { username, nas_ip, secret, address_list } = req.body;
  if (!username || !nas_ip || !secret || !address_list) {
    return res.status(400).json({
      status: "error",
      message: "Gagal, butuh variabel: username, nas_ip, secret, address_list (contoh: isolasi).",
    });
  }

  if (!isValidIp(nas_ip)) {
    return res.status(400).json({ status: "error", message: "Format NAS IP tidak valid." });
  }

  const cleanUsername = sanitize(username);
  const cleanSecret = sanitize(secret);
  const cleanList = sanitize(address_list);
  const nasTarget = `${nas_ip}:3799`;

  // Menggunakan tipe 'coa' dan menyertakan Mikrotik-Address-List
  const cmd = `echo "User-Name=${cleanUsername}, Mikrotik-Address-List=${cleanList}" | radclient -x -r 1 -t 3 ${nasTarget} coa '${cleanSecret}' 2>&1`;

  exec(cmd, (error, stdout, stderr) => {
    const outputText = stdout || stderr || "";
    const logOutput = outputText.split("\n").filter((line) => line.trim() !== "");

    if (outputText.includes("CoA-ACK")) {
      return res.status(200).json({
        status: "success",
        message: `Berhasil: IP User masuk ke address list '${cleanList}'.`,
        log: logOutput,
      });
    } else {
      return res.status(400).json({
        status: "error",
        message: "Gagal memasukkan user ke address list (CoA-NAK atau Timeout).",
        log: logOutput,
      });
    }
  });
});

// Jalankan server di port 3000
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`CoA API Gateway running on port ${PORT}`);
});
