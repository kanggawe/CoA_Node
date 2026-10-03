# 🚀 CoA Node

**CoA Node** adalah sistem yang terdiri dari dua komponen utama:

* 🖥️ **CoA Node** — menjalankan proses dan layanan utama pada sisi node.
* 🌐 **CoA Proxy** — menjadi gateway/proxy untuk mengatur dan meneruskan komunikasi menuju CoA Node.

Keduanya dirancang untuk bekerja bersama sehingga deployment dapat dibuat lebih fleksibel, terkontrol, dan mudah dikembangkan.

---

## 🏗️ Architecture

```text
                         CLIENT / APPLICATION
                                  │
                                  │
                                  ▼
                    ┌─────────────────────────┐
                    │       COA PROXY         │
                    │                         │
                    │  Gateway / Proxy Layer  │
                    │  Routing & Filtering    │
                    │  Access Control         │
                    └────────────┬────────────┘
                                 │
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │        COA NODE          │
                    │                         │
                    │   Core Node Service     │
                    │   Processing Layer      │
                    │   Node Operations       │
                    └────────────┬────────────┘
                                 │
                 ┌───────────────┼───────────────┐
                 ▼               ▼               ▼
              Service          Network         API
```

---

# 🖥️ CoA Node

`coa_node` merupakan komponen **core node** yang menjadi tempat proses utama dijalankan.

### ⭐ Keunggulan CoA Node

#### 1. ⚡ Processing Lebih Dekat dengan Service

CoA Node dirancang sebagai tempat eksekusi proses utama sehingga request yang sudah diteruskan oleh proxy dapat langsung diproses pada node.

Keuntungannya:

* Mengurangi ketergantungan pada satu server pusat.
* Proses dapat dijalankan pada node yang berbeda.
* Cocok untuk environment dengan beberapa server/node.
* Memungkinkan sistem dikembangkan menjadi arsitektur distributed.

---

#### 2. 📦 Mudah Dikembangkan dan Di-scale

CoA Node dapat dijadikan unit deployment tersendiri.

Contoh:

```text
                 COA PROXY
                     │
          ┌──────────┼──────────┐
          │          │          │
          ▼          ▼          ▼
       NODE-01    NODE-02    NODE-03
```

Dengan model seperti ini, penambahan node baru tidak harus mengubah keseluruhan sistem.

Cukup:

```text
Deploy Node Baru
       ↓
Register / Connect
       ↓
Mulai Menerima Request
```

### 🎯 Cocok Untuk

* Distributed service
* Multiple server
* Edge node
* Network infrastructure
* Service processing
* Infrastruktur dengan banyak lokasi

---

# 🌐 CoA Proxy

`coa-proxy` berfungsi sebagai **lapisan perantara/gateway** antara client dan CoA Node.

```text
Client
   │
   ▼
┌─────────────┐
│  COA PROXY  │
└──────┬──────┘
       │
       ▼
   COA NODE
```

### ⭐ Keunggulan CoA Proxy

#### 1. 🛡️ Satu Pintu Akses

Client tidak harus berkomunikasi langsung dengan setiap CoA Node.

Semua request dapat melewati:

```text
Client
  ↓
CoA Proxy
  ↓
CoA Node
```

Hal ini memberikan satu layer tambahan untuk:

* Access control
* Authentication
* Request filtering
* Routing
* Logging
* Monitoring

---

#### 2. 🔀 Mempermudah Routing dan Manajemen Banyak Node

CoA Proxy dapat menjadi titik pengatur komunikasi apabila jumlah node semakin banyak.

Contohnya:

```text
                    ┌─────────────┐
                    │  COA PROXY  │
                    └──────┬──────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
           NODE-01      NODE-02      NODE-03
           Jakarta      Bandung      Indramayu
```

Proxy dapat dikembangkan untuk menentukan node tujuan berdasarkan kebutuhan tertentu.

Misalnya:

```text
Request
   │
   ├──► Node Jakarta
   │
   ├──► Node Bandung
   │
   └──► Node Indramayu
```

Dengan demikian, penambahan node tidak membuat client harus mengetahui seluruh alamat node yang tersedia.

---

# ⚔️ CoA Node vs CoA Proxy

| Komponen       | CoA Node                   | CoA Proxy                     |
| -------------- | -------------------------- | ----------------------------- |
| Fungsi utama   | Menjalankan proses         | Gateway komunikasi            |
| Posisi         | Backend / processing layer | Front / gateway layer         |
| Fokus          | Execution                  | Routing & access              |
| Skalabilitas   | Horizontal node            | Centralized gateway           |
| Security layer | Processing security        | Access security               |
| Multi-node     | ✅                          | ✅                             |
| Routing        | Internal                   | Gateway                       |
| Logging        | Process log                | Request/access log            |
| Deployment     | Dapat diperbanyak          | Dapat dibuat terpusat/cluster |

---

# 🔥 Kenapa Menggunakan Keduanya?

Kekuatan utama CoA adalah pemisahan antara **gateway** dan **processing node**.

Tanpa proxy:

```text
Client ─────────► Node
Client ─────────► Node
Client ─────────► Node
```

Dengan CoA Proxy:

```text
                    ┌──────────────┐
Client ────────────►│   COA PROXY  │
                    └───────┬──────┘
                            │
                 ┌──────────┼──────────┐
                 ▼          ▼          ▼
              NODE-01    NODE-02    NODE-03
```

### Hasilnya:

* 🔐 Akses node dapat lebih terkontrol.
* 🔀 Routing berada pada satu layer.
* 📈 Node dapat ditambahkan tanpa mengubah client.
* 🧩 Komponen dapat dikembangkan secara independen.
* 🌍 Cocok untuk deployment multi-server dan multi-location.
* 🛠️ Maintenance node menjadi lebih mudah.
* 📊 Monitoring dan logging dapat dipusatkan pada proxy.

---

# 📂 Project Structure

```text
CoA_Node/
│
├── coa_node/
│   └── Core Node Service
│
├── coa-proxy/
│   └── Proxy / Gateway Service
│
├── .gitignore
│
└── README.md
```

---

# 🚀 Deployment Concept

Untuk deployment sederhana:

```text
┌──────────────────────┐
│       CLIENT         │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│      COA PROXY       │
│      Server #1       │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│       COA NODE       │
│      Server #2       │
└──────────────────────┘
```

Untuk deployment skala besar:

```text
                         ┌──────────────┐
                         │   COA PROXY  │
                         └───────┬──────┘
                                 │
             ┌───────────────────┼───────────────────┐
             │                   │                   │
             ▼                   ▼                   ▼
       ┌───────────┐       ┌───────────┐       ┌───────────┐
       │ COA NODE  │       │ COA NODE  │       │ COA NODE  │
       │  NODE-01  │       │  NODE-02  │       │  NODE-03  │
       └───────────┘       └───────────┘       └───────────┘
             │                   │                   │
             ▼                   ▼                   ▼
          Service             Service             Service
```

---

# 🛠️ Use Case

CoA Node dapat digunakan sebagai fondasi untuk sistem yang membutuhkan:

* 🌐 Distributed infrastructure
* 🖥️ Multi-server processing
* 📡 Network service
* 🔌 API/service gateway
* 🏢 Multi-location deployment
* ⚡ Scalable backend service
* 🔐 Controlled service access

---

# 🗺️ Roadmap

* [ ] Node registration
* [ ] Node authentication
* [ ] Proxy authentication
* [ ] Health check
* [ ] Node status monitoring
* [ ] Automatic node discovery
* [ ] Load balancing
* [ ] Failover
* [ ] Centralized logging
* [ ] Metrics & monitoring
* [ ] Docker deployment
* [ ] Multi-node management

---

# 🤝 Contributing

Contribution sangat dipersilakan.

```bash
git clone https://github.com/kanggawe/CoA_Node.git

cd CoA_Node

git checkout -b feature/nama-fitur
```

Setelah perubahan selesai:

```bash
git add .
git commit -m "feat: add new feature"
git push origin feature/nama-fitur
```

Kemudian buat Pull Request.

---

# 👤 Maintainer

**kanggawe**

GitHub: [@kanggawe](https://github.com/kanggawe)

---

## ⭐ Support

Jika project ini bermanfaat, silakan berikan ⭐ pada repository.

**CoA Node — Connect. Control. Scale.**
