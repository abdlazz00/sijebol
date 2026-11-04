# Standard Deployment Guide & Architecture Playbook

Dokumen ini adalah standar arsitektur, workflow, struktur folder, dan panduan langkah demi langkah (*step-by-step playbook*) untuk melakukan deployment aplikasi Laravel modern (Inertia.js / Vue / React / Blade) ke lingkungan VPS menggunakan Docker, Nginx Proxy Manager, dan Cloudflare.

Dokumen ini dirancang agar **pengembang manusia maupun AI agent di sesi mendatang** dapat memahami, mereplikasi, dan mengelola alur deployment dengan konsisten, aman, dan tanpa *trial-and-error*.

---

## 1. Topologi & Arsitektur Sistem

Arsitektur ini menerapkan prinsip **Zero Public Database Exposure**, **Multi-Environment Isolation (Production & UAT dalam 1 VPS)**, dan **Reverse Proxy Terpadu**.

```mermaid
flowchart TD
    subgraph Public_Internet [Internet Publik]
        User[Pengguna / Browser]
        Dev_PC[Developer PC / DataGrip]
    end

    subgraph Cloudflare_Edge [Cloudflare Edge Network]
        CF_DNS[Cloudflare DNS + WAF]
        CF_SSL[SSL Termination / Edge Cert]
    end

    subgraph VPS_Host [VPS Server Host - Ubuntu 2 vCPU 4GB RAM]
        SWAP[(Swap File 4 GB - Anti OOM)]
        UFW[Firewall UFW: 22, 80, 443, 81]

        subgraph Docker_Network [Docker Network: bridge - sijebol-network]
            NPM[Nginx Proxy Manager Container]

            subgraph Environment_Prod [Project: sijebol-prod]
                Prod_App[Container: sijebol-prod\nPHP 8.4-FPM + Nginx + Supervisor]
                Prod_Vol[(Volume: ./prod/storage)]
            end

            subgraph Environment_UAT [Project: sijebol-uat]
                UAT_App[Container: sijebol-uat\nPHP 8.4-FPM + Nginx + Supervisor]
                UAT_Vol[(Volume: ./uat/storage)]
            end

            subgraph Database_Cluster [Database Service]
                MySQL[Container: sijebol-mysql\nMySQL 8.0 - Port 3306 Localhost Only]
                DB_Prod[(DB: sijebol_prod)]
                DB_UAT[(DB: sijebol_uat)]
                DB_Vol[(Volume: ./database/data)]
            end
        end
    end

    User -->|HTTPS| CF_DNS
    CF_DNS --> CF_SSL
    CF_SSL -->|Proxied via A Record| UFW
    UFW --> NPM

    Dev_PC -->|SSH Tunnel Port 22| VPS_Host
    VPS_Host -->|127.0.0.1:3306| MySQL

    NPM -->|sungaibeduk.sijebol.online| Prod_App
    NPM -->|uat-seibeduk.sijebol.online| UAT_App

    Prod_App --> Prod_Vol
    UAT_App --> UAT_Vol

    Prod_App -->|Internal Network| DB_Prod
    UAT_App -->|Internal Network| DB_UAT

    MySQL --> DB_Prod
    MySQL --> DB_UAT
    MySQL --> DB_Vol
```

---

## 2. Resource Sizing & Kapasitas Server

Sebagai acuan standar untuk VPS skala UKM / Pemerintahan (contoh: **2 vCPU, 4 GB RAM, 80 GB SSD**):

| Service / Komponen | Alokasi RAM Rata-Rata | Catatan Konfigurasi |
|---|---|---|
| **OS Ubuntu + Docker Daemon** | ~400 – 500 MB | Base system overhead |
| **Nginx Proxy Manager (NPM)** | ~100 – 150 MB | Reverse proxy, Let's Encrypt SSL manager |
| **MySQL 8.0 (`sijebol-mysql`)** | ~600 – 750 MB | Di-tuning: `--innodb-buffer-pool-size=512M` |
| **Aplikasi Production (`sijebol-prod`)** | ~350 – 450 MB | PHP 8.4-FPM + Nginx + Worker (`queue:work`) |
| **Aplikasi UAT (`sijebol-uat`)** | ~250 – 350 MB | PHP 8.4-FPM + Nginx + Worker (`queue:work`) |
| **Swap File Linux** | **4.0 GiB (Wajib)** | **Pencegah OOM Killer saat `npm run build` (Vite) berjalan** |
| **Total RAM Terpakai (Idle)** | **~1.8 – 2.2 GB** | **Sisa ~1.8 GB aman untuk OS page cache & burst traffic** |

> [!CRITICAL]
> **Wajib Membuat Swap 4 GB**:
> Saat deploy, proses kompilasi aset frontend (`npm run build` / `vue-tsc`) membutuhkan lonjakan RAM 1.5–2 GB. Tanpa Swap, Linux OOM (Out Of Memory) Killer akan membunuh container Production atau MySQL (`Exit Code 137`), menyebabkan 503 Bad Gateway.

---

## 3. Standar Struktur Folder di Server VPS

Seluruh komponen aplikasi dipusatkan di direktori `/opt/<nama_project>` untuk kemudahan *backup*, isolasi, dan manajemen hak akses:

```text
/opt/sijebol/
├── npm/                         # Nginx Proxy Manager
│   ├── docker-compose.yml       # Port 80, 443, dan 81
│   ├── data/                    # Konfigurasi Nginx proxy hosts & SQLite internal
│   └── letsencrypt/             # Sertifikat SSL Let's Encrypt
│
├── database/                    # Database MySQL 8.0 Terpadu
│   ├── docker-compose.yml       # Port 127.0.0.1:3306:3306 (Localhost only untuk DataGrip)
│   ├── init/
│   │   └── 01-init.sql          # Inisialisasi DB prod & uat + GRANT privileges
│   └── data/                    # Persistent storage database MySQL
│
├── prod/                        # Lingkungan Production
│   ├── storage/                 # Persistent storage (KK, Akta, logs)
│   └── app/                     # Git clone dari branch `main`
│       ├── .env                 # COMPOSE_PROJECT_NAME=sijebol-prod
│       ├── Dockerfile
│       └── docker-compose.prod.yml
│
└── uat/                         # Lingkungan UAT / Staging
    ├── storage/                 # Persistent storage UAT
    └── app/                     # Git clone dari branch `staging`
        ├── .env                 # COMPOSE_PROJECT_NAME=sijebol-uat
        ├── Dockerfile
        └── docker-compose.uat.yml
```

> [!IMPORTANT]
> **Isolasi Project Namespace (Pencegah Container Saling Timpa)**:
> Karena folder aplikasi Prod dan UAT sama-sama bernama `app` (`/opt/sijebol/prod/app` dan `/opt/sijebol/uat/app`), Docker Compose secara default akan menamai project keduanya sebagai `app`. 
> Jika tidak diisolasi, menjalankan deploy di UAT akan **mematikan dan menghapus kontainer Production**, dan sebaliknya!
> **Solusi mutlak**:
> 1. Set `COMPOSE_PROJECT_NAME=sijebol-prod` di file `.env` Production.
> 2. Set `COMPOSE_PROJECT_NAME=sijebol-uat` di file `.env` UAT.
> 3. Gunakan flag `-p sijebol-prod` dan `-p sijebol-uat` pada setiap perintah Docker Compose.

---

## 4. Alur Percabangan Git (Branching Workflow)

* **Branch `main`**: Digunakan untuk **Production** (`sungaibeduk.sijebol.online`).
* **Branch `staging`**: Digunakan untuk **UAT** (`uat-seibeduk.sijebol.online`).

```text
[Laptop Dev] feature/xxx ──► merge ke `staging` ──► push origin staging
                                                          │
                                                    (Deploy ke UAT)
                                                          │
                                                    (Verifikasi UAT)
                                                          │
[Laptop Dev] merge `staging` ke `main` ──────────► push origin main
                                                          │
                                                    (Deploy ke Production)
```

---

## 5. Panduan Instalasi Awal (Step-by-Step Playbook)

### Langkah 1: Persiapan DNS di Cloudflare
1. Buka DNS Management domain di Cloudflare.
2. Tambahkan **A Record** (Mode: **Proxied / Awan Oranye**):
   * `sungaibeduk` ➔ `IP_PUBLIK_VPS`
   * `uat-seibeduk` ➔ `IP_PUBLIK_VPS`
3. Pengaturan SSL/TLS Cloudflare:
   * **SSL/TLS Mode**: **Full** (wajib, jangan *Flexible* agar tidak redirect loop).
   * **Always Use HTTPS**: **ON**.
   * **Minimum TLS Version**: **TLS 1.2**.

---

### Langkah 2: Setup Awal VPS (Ubuntu) & Swap Memory
Masuk via SSH ke VPS:
```bash
# 1. Update paket sistem
apt update && apt upgrade -y
apt install -y curl git ufw htop

# 2. Pasang Swap Memory 4 GB (Wajib anti-crash OOM)
fallocate -l 4G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
sysctl vm.swappiness=20
echo 'vm.swappiness=20' >> /etc/sysctl.conf

# 3. Pasang Docker Engine resmi
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# 4. Setup Firewall UFW (PENTING: izinkan SSH dulu!)
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 81/tcp
ufw enable

# 5. Buat Docker Network bersama
docker network create sijebol-network
```

---

### Langkah 3: Menjalankan Nginx Proxy Manager (NPM)
```bash
mkdir -p /opt/sijebol/npm && cd /opt/sijebol/npm

cat << 'EOF' > docker-compose.yml
services:
  npm:
    image: 'jc21/nginx-proxy-manager:latest'
    container_name: nginx-proxy-manager
    restart: unless-stopped
    ports:
      - '80:80'
      - '81:81'
      - '443:443'
    volumes:
      - ./data:/data
      - ./letsencrypt:/etc/letsencrypt
    networks:
      - sijebol-network

networks:
  sijebol-network:
    external: true
EOF

docker compose up -d
```
* Buka browser: `http://<IP_VPS>:81`
* Login awal: `admin@example.com` / `changeme` (segera ganti email & password).

---

### Langkah 4: Menjalankan Database MySQL 8.0 (Support DataGrip via SSH Tunnel)
```bash
mkdir -p /opt/sijebol/database/init && cd /opt/sijebol/database

# Buat file inisialisasi SQL
cat << 'EOF' > init/01-init.sql
CREATE DATABASE IF NOT EXISTS sijebol_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS sijebol_uat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON sijebol_prod.* TO 'sijebol'@'%';
GRANT ALL PRIVILEGES ON sijebol_uat.* TO 'sijebol'@'%';
FLUSH PRIVILEGES;
EOF

# Buat docker-compose.yml
# Catatan: port 127.0.0.1:3306:3306 di-bind ke localhost host saja,
# aman dari internet publik tapi bisa diakses DataGrip / DBeaver lewat SSH Tunnel.
cat << 'EOF' > docker-compose.yml
services:
  mysql:
    image: 'mysql:8.0'
    container_name: sijebol-mysql
    restart: unless-stopped
    ports:
      - '127.0.0.1:3306:3306'
    environment:
      MYSQL_ROOT_PASSWORD: 'PasswordRootKuat123!'
      MYSQL_USER: 'sijebol'
      MYSQL_PASSWORD: 'PasswordSijebolKuat123!'
    command:
      - --default-authentication-plugin=mysql_native_password
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --innodb-buffer-pool-size=512M
      - --max-connections=100
    volumes:
      - ./data:/var/lib/mysql
      - ./init:/docker-entrypoint-initdb.d
    networks:
      - sijebol-network

networks:
  sijebol-network:
    external: true
EOF

docker compose up -d
```

---

### Langkah 5: Deploy Environment Production

```bash
mkdir -p /opt/sijebol/prod && cd /opt/sijebol/prod
git clone -b main https://github.com/abdlazz00/sijebol.git app
cd app

# Abaikan tracking perubahan chmod di repo server
git config core.filemode false

cp .env.production.example .env

# Tambahkan nama project unik agar tidak bentrok dengan UAT
echo "COMPOSE_PROJECT_NAME=sijebol-prod" >> .env

# Edit .env (isi DB_PASSWORD, RESEND_API_KEY, dll)
nano .env

# Jalankan build container dengan project namespace spesifik
docker compose -p sijebol-prod -f docker-compose.prod.yml up -d --build

# Generate APP_KEY dan simpan ke file .env host
KEY=$(docker exec sijebol-prod php artisan key:generate --show)
sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env
docker compose -p sijebol-prod -f docker-compose.prod.yml up -d

# Seeding & buat Super Admin pertama
docker exec -it sijebol-prod php artisan db:seed
docker exec -it sijebol-prod php artisan sijebol:create-super-admin admin@sungaibeduk.go.id "Super Admin"
```

---

### Langkah 6: Routing & SSL di Nginx Proxy Manager (NPM)

1. Buka dashboard NPM (`http://<IP_VPS>:81`) ➔ **Hosts** ➔ **Proxy Hosts** ➔ **Add Proxy Host**.
2. **Tab Details**:
   * **Domain Names**: `sungaibeduk.sijebol.online`
   * **Scheme**: `http`
   * **Forward Hostname / IP**: `sijebol-prod`
   * **Forward Port**: `80`
   * Aktifkan: `Block Common Exploits` & `Websockets Support`.
3. **Tab SSL**:
   * **SSL Certificate**: `Request a new SSL Certificate`.
   * Aktifkan: `Force SSL` & `HTTP/2 Support`.
   * Isi email & centang persetujuan Let's Encrypt.
4. **Tab Ikon Gerigi (⚙️ / Advanced)**:
   > [!IMPORTANT]
   > Wajib ditambahkan untuk mencegah error **502 Bad Gateway** akibat header keamanan CSP & Inertia yang melebihi batas buffer default Nginx:
   ```nginx
   proxy_buffer_size 128k;
   proxy_buffers 4 256k;
   proxy_busy_buffers_size 256k;
   ```
5. Klik **Save**.

---

### Langkah 7: Deploy Environment UAT

Ulangi alur di atas untuk folder `/opt/sijebol/uat`:
```bash
mkdir -p /opt/sijebol/uat && cd /opt/sijebol/uat
git clone -b staging https://github.com/abdlazz00/sijebol.git app
cd app

git config core.filemode false
cp .env.uat.example .env

# Tambahkan nama project unik untuk UAT
echo "COMPOSE_PROJECT_NAME=sijebol-uat" >> .env
nano .env

# Jalankan build dengan project namespace sijebol-uat
docker compose -p sijebol-uat -f docker-compose.uat.yml up -d --build

KEY=$(docker exec sijebol-uat php artisan key:generate --show)
sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env
docker compose -p sijebol-uat -f docker-compose.uat.yml up -d

docker exec -it sijebol-uat php artisan db:seed
docker exec -it sijebol-uat php artisan sijebol:create-super-admin admin@uat-sungaibeduk.go.id "Super Admin UAT"
```
Di NPM arahkan `uat-seibeduk.sijebol.online` ➔ `sijebol-uat:80` dengan tab gerigi ⚙️ buffer yang sama.

---

## 6. Otomasi Deployment Harian (Daily Deploy Commands)

Beri izin eksekusi dan hubungkan shortcut global sekali saja di VPS:
```bash
chmod +x /opt/sijebol/prod/app/scripts/deploy-prod.sh
chmod +x /opt/sijebol/uat/app/scripts/deploy-uat.sh

ln -sf /opt/sijebol/prod/app/scripts/deploy-prod.sh /usr/local/bin/deploy-prod
ln -sf /opt/sijebol/uat/app/scripts/deploy-uat.sh /usr/local/bin/deploy-uat
```

Setiap kali ada fitur baru atau perubahan kode:
* **Update UAT**: cukup ketik `deploy-uat` dari folder mana saja.
* **Update Prod**: cukup ketik `deploy-prod` dari folder mana saja.

### Struktur Script Standar `deploy-prod.sh`:
```bash
#!/bin/bash
set -e

echo "🚀 [SIJEBOL] Memulai deployment Production (sungaibeduk.sijebol.online)..."
cd /opt/sijebol/prod/app

echo "📥 Menarik pembaruan dari branch main..."
git fetch origin main
git reset --hard origin/main

echo "🔨 Membangun dan merestart kontainer Production..."
docker compose -p sijebol-prod -f docker-compose.prod.yml up -d --build

echo "⚡ Menjalankan migrasi database..."
docker compose -p sijebol-prod -f docker-compose.prod.yml exec -T app php artisan migrate --force

echo "🔄 Merestart antrean queue..."
docker compose -p sijebol-prod -f docker-compose.prod.yml exec -T app php artisan queue:restart

echo "⚡ Mengoptimalkan cache Laravel..."
docker compose -p sijebol-prod -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -p sijebol-prod -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -p sijebol-prod -f docker-compose.prod.yml exec -T app php artisan route:cache
docker compose -p sijebol-prod -f docker-compose.prod.yml exec -T app php artisan view:cache

echo "✅ [SIJEBOL] Deployment Production selesai dengan sukses!"
```

---

## 7. Koneksi Database ke DataGrip / DBeaver (SSH Tunnel)

Untuk melihat dan mengelola database `sijebol_prod` dan `sijebol_uat` langsung dari laptop developer menggunakan **DataGrip** atau **DBeaver**:

1. Buka **DataGrip** ➔ New Data Source ➔ **MySQL**.
2. **Tab SSH/SSL**:
   - Centang **Use SSH tunnel**.
   - Host: `IP_PUBLIK_VPS` (port `22`).
   - Username: `root` (atau user SSH).
   - Auth: Password / SSH Private Key.
   - Klik **Test Connection** (Pastikan sukses terhubung).
3. **Tab General**:
   - Host: `127.0.0.1` (port `3306`).
   - User: `sijebol` (atau `root`).
   - Password: Password database dari `docker-compose.yml`.
   - Database: Kosongkan.
4. Klik **Test Connection** ➔ Centang skema `sijebol_prod` dan `sijebol_uat` di daftar schema.

---

## 8. Troubleshooting Playbook (Penyelesaian Masalah Cepat)

### Kasus 1: Web Production Hilang / 503 Bad Gateway setelah Deploy UAT
* **Gejala**: `sijebol-prod` hilang dari output `docker ps -a` setelah mendeploy UAT.
* **Penyebab**: Kedua folder bernama `app` (`/opt/sijebol/prod/app` dan `/opt/sijebol/uat/app`), sehingga Docker Compose menganggap UAT dan Prod adalah project `app` yang sama. Deploy UAT menimpa dan memusnahkan kontainer Prod.
* **Solusi**:
  1. Hapus bentrok nama lama: `docker rm -f sijebol-uat sijebol-prod` (jika stuck).
  2. Tambahkan `COMPOSE_PROJECT_NAME=sijebol-prod` di `.env` prod.
  3. Tambahkan `COMPOSE_PROJECT_NAME=sijebol-uat` di `.env` uat.
  4. Jalankan masing-masing dengan `-p`:
     ```bash
     docker compose -p sijebol-prod -f docker-compose.prod.yml up -d
     docker compose -p sijebol-uat -f docker-compose.uat.yml up -d
     ```

### Kasus 2: Kontainer Crash Exit Code 137 (OOM Killer) saat Build
* **Gejala**: Proses build Docker berhenti tiba-tiba dengan exit code `137` atau kontainer prod mati saat UAT sedang di-build.
* **Penyebab**: Server kehabisan RAM fisik (4 GB) akibat lonjakan memori Vite (`vue-tsc`), dan server tidak memiliki Swap Memory.
* **Solusi**: Pasang Swap 4 GB segera:
  ```bash
  fallocate -l 4G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
  sysctl vm.swappiness=20
  ```

### Kasus 3: Judul Tab Browser Menampilkan "- Laravel" (Masuk - Laravel)
* **Gejala**: Di browser, judul halaman tetap menampilkan kata *Laravel*, bukan nama aplikasi *SIJEBOL*.
* **Penyebab**:
  1. `.dockerignore` mengabaikan file `.env`, sehingga saat `npm run build` dijalankan di dalam container, Vite tidak membaca `VITE_APP_NAME` dan mengambil fallback `'Laravel'`.
  2. Fallback di `resources/js/app.ts` dan `resources/views/app.blade.php` masih diset ke `'Laravel'`.
* **Solusi**:
  1. Di `Dockerfile` stage frontend build, tambahkan: `ENV VITE_APP_NAME="SIJEBOL"`.
  2. Di `resources/js/app.ts`, ganti fallback: `const appName = import.meta.env.VITE_APP_NAME || 'SIJEBOL';`.
  3. Di `resources/views/app.blade.php`, ganti: `<title inertia>{{ config('app.name', 'SIJEBOL') }}</title>`.

### Kasus 4: Error "Your local changes would be overwritten by merge" pada Git Pull
* **Gejala**: `deploy-prod` atau `git pull` gagal karena file `.gitignore` di `storage/` atau file script dianggap termodifikasi (*modified*).
* **Penyebab**: Perubahan izin eksekusi (`chmod`) saat Laravel berjalan dianggap Git sebagai perubahan file (`core.filemode=true`).
* **Solusi**:
  ```bash
  git config core.filemode false
  git reset --hard origin/<branch>
  ```
  Dan pastikan script deploy menggunakan `git reset --hard origin/<branch>` alih-alih `git pull`.

### Kasus 5: Error "-bash: /usr/local/bin/deploy-prod: Permission denied"
* **Gejala**: Menjalankan shortcut global gagal karena izin eksekusi hilang setelah `git reset`.
* **Solusi**: Beri izin eksekusi ulang di VPS:
  ```bash
  chmod +x /opt/sijebol/prod/app/scripts/deploy-prod.sh
  chmod +x /opt/sijebol/uat/app/scripts/deploy-uat.sh
  ```
  *(Di repo lokal, jalankan `git update-index --chmod=+x scripts/deploy-*.sh` lalu commit & push agar Git mengingat atribut executable secara permanen).*

### Kasus 6: Error "502 Bad Gateway / upstream sent too big header"
* **Gejala**: Akses ke halaman web mengembalikan 502 Bad Gateway dari Nginx Proxy Manager.
* **Penyebab**: Header keamanan CSP, cookie sesi, dan header Inertia melebihi batas buffer default FastCGI (4k) dan NPM.
* **Solusi**:
  1. Di `docker/nginx.conf` pastikan FastCGI buffer diperbesar:
     ```nginx
     fastcgi_buffer_size 64k;
     fastcgi_buffers 8 64k;
     fastcgi_busy_buffers_size 128k;
     ```
  2. Di NPM (Tab gerigi ⚙️ Advanced) tambahkan custom configuration:
     ```nginx
     proxy_buffer_size 128k;
     proxy_buffers 4 256k;
     proxy_busy_buffers_size 256k;
     ```

### Kasus 7: Port 22 SSH Connection Timed Out setelah UFW Aktif
* **Gejala**: Tidak bisa login SSH ke VPS setelah mengaktifkan firewall.
* **Solusi**: Masuk ke **Web Console / VNC** di dashboard penyedia VPS (Rumahweb), lalu jalankan:
  ```bash
  ufw disable
  ufw allow 22/tcp
  systemctl restart ssh
  ufw enable
  ```

## 9. Catatan Alur Mandiri Siswa (Fase 7a)

### Batas unggah dokumen

Aplikasi menyatakan berkas dokumen maksimal 5 MB sah, jadi server harus menerima minimal 6 MB:

- PHP: `upload_max_filesize` dan `post_max_size` minimal `6M`.
- Nginx (termasuk Nginx Proxy Manager): `client_max_body_size` minimal `6m`.

Foto dari kamera HP dikecilkan otomatis di peramban sebelum diunggah, tetapi PDF tidak.

### Memulai dari data kosong (sekali jalan)

Menghapus semua data siswa, pendaftaran, jadwal, dan berkas dokumen. Akun pengguna, sekolah, dan log aktivitas tidak tersentuh.

```bash
php artisan sijebol:bersihkan-data-siswa          # meminta konfirmasi
php artisan sijebol:bersihkan-data-siswa --force  # tanpa konfirmasi (hanya di lokal)
```

Jalankan hanya sebelum rilis, dan cadangkan database lebih dulu.

**Di UAT dan produksi (F-11):** `--force` tidak berlaku. Perintah menampilkan nama database dan jumlah baris yang akan dihapus, lalu meminta operator **mengetik nama lingkungan** (`APP_ENV`, mis. `production`). Untuk skrip tanpa terminal interaktif, beri nama lingkungan lewat opsi:

```bash
docker exec sijebol-prod php artisan sijebol:bersihkan-data-siswa --lingkungan=production
```

Nama yang salah atau kosong membatalkan perintah tanpa menghapus apa pun.

### Link pendaftaran sekolah

Setiap sekolah mendapat token otomatis. Operator menyalin link dari menu **Link pendaftaran**; admin dapat mereset bila link bocor. Pendaftaran akun memakai email, jadi pengiriman email (SMTP produksi) harus sudah berfungsi sebelum siswa mendaftar.

### Wajib setelah migrasi: jalankan ulang seeder role

Role di database yang sudah ada tidak berubah sendiri. Tanpa langkah ini, role `siswa` belum ada (pendaftaran akun gagal) dan operator sekolah masih memegang hak ubah/hapus siswa yang seharusnya dicabut.

```bash
php artisan migrate --force
php artisan db:seed --class=AccessSeeder --force
```

> **Perilaku seeder berubah (2 Nov 2026, temuan F-01 review).** Sebelumnya seeder menyetel ulang izin tiga role bawaan sehingga perubahan lewat menu Role ikut tertimpa. Kini seeder **aditif** dan aman dijalankan ulang kapan saja. Detailnya di bagian 16. Catatan di bagian rilis lama di bawah yang menyebut "seeder menyetel ulang/mencabut izin" menggambarkan perilaku lama.

## 10. Catatan Sesi Rekaman Berkuota (Fase 7b)

### Urutan wajib saat memasang fase 7b

Migrasi fase 7b mengubah `schedules` menjadi sesi (jam, kuota) dan `registration_students` menjadi pendaftaran sesi per siswa, serta **menghapus** tabel `registrations`. Migrasi ini satu arah dan menolak berjalan bila `schedules` atau `registration_students` masih berisi data lama.

```bash
# 0. Cadangkan database lebih dulu.
php artisan sijebol:bersihkan-data-siswa      # data siswa, pendaftaran, jadwal lama dihapus
php artisan migrate --force
php artisan db:seed --class=AccessSeeder --force   # izin pendaftaran.lihat/buat untuk role siswa; pendaftaran.ajukan dicabut
```

Bila `migrate` berhenti dengan pesan "Data lama masih ada", jalankan perintah pembersihan di atas lalu ulangi migrasi.

### Konfigurasi

`config/sijebol.php` memuat `registration_close_days` (nilai awal `2`): sejak 2026-11-01 hanya isian awal batas pendaftaran di form sesi (lihat bagian 13); sebelumnya = hari terakhir mendaftar (tanggal sesi dikurangi nilai ini, inklusif). Siswa dapat membatalkan atau mengajukan ulang sampai sehari sebelum sesi.

### Belum tersedia sampai fase 7c

Notifikasi ke siswa (disetujui, perlu perbaikan, ditolak, sesi diubah atau dibatalkan), tiket dan QR, serta batas waktu perbaikan belum ada. Selama itu siswa hanya melihat status di halaman **Sesi rekaman**. Jangan dirilis ke siswa sungguhan sebelum fase 7c.

## 11. Catatan Fase 7c (tiket, notifikasi siswa, scheduler)

### Pemasangan

```bash
php artisan migrate --force      # hanya menambah kolom (ticket_code, tenggat, penanda pengingat) dan melonggarkan by_user_id; aman pada data yang ada
npm ci && npm run build          # paket baru: qrcode (QR digambar di peramban)
php artisan queue:restart
```

### Wajib hidup di server

- **Cron `schedule:run` setiap menit** dan **worker antrean**. Dua perintah berjalan tiap jam: `sijebol:kedaluwarsakan` (melepas kursi yang lewat batas perbaikan atau sesinya sudah berakhir) dan `sijebol:kirim-pengingat` (pengingat batas perbaikan dan sesi H-1 sejak pukul 08.00 WIB). Tanpa cron, kursi tidak pernah dilepas otomatis dan pengingat tidak terkirim. Detail di `docs/operasional-queue.md`.
- **Email**: semua keputusan, perubahan sesi, dan pengingat dikirim ke email siswa lewat antrean. Akun staf (operator sekolah, admin kecamatan) yang dibuat atau dikirim ulang aktivasinya lewat menu Pengguna juga menerima link aktivasi lewat email; `sijebol:create-super-admin` tidak, link-nya dicetak di terminal. SMTP produksi atau domain terverifikasi harus siap sebelum rilis (lihat catatan Resend free tier di spec bagian 11).
- **HTTPS** untuk produksi (kamera pemindai di fase 7d membutuhkannya; halaman tiket sebaiknya juga lewat HTTPS).

### Konfigurasi (`config/sijebol.php`)

| Kunci | Nilai awal | Arti |
|---|---|---|
| `registration_close_days` | 2 | Isian awal batas pendaftaran di form sesi: sekian hari sebelum sesi, pukul 23.59 |
| `fix_window_hours` | 48 | Batas perbaikan: sekian jam sejak diminta, tidak melewati akhir hari sebelum sesi |
| `reminder_hour` | 8 | Pengingat sesi H-1 dikirim sejak jam ini (WIB) |

### Gerbang rilis

Dengan fase 7c, syarat rilis 7b terpenuhi (siswa diberi tahu atas keputusan, kursi dilepas otomatis). Pastikan penelusuran manual pada rencana 7c (Task 8) sudah dijalankan di DB dev, termasuk kode QR terbaca oleh pemindai QR di HP.

## 12. Catatan Fase 7d (pindai QR, kehadiran otomatis, beranda, laporan)

### Pemasangan

```bash
php artisan migrate --force                        # hanya menambah kolom registration_students.scanned_at; aman pada data yang ada
php artisan db:seed --class=AccessSeeder --force   # WAJIB: membuat izin rekaman.pindai dan memberikannya ke super-admin dan admin-kecamatan
npm ci && npm run build                            # paket baru: qr-scanner (pembaca QR dari kamera)
php artisan queue:restart
```

Tanpa seeder, tombol **Pindai tiket** tidak muncul untuk siapa pun dan rute pindai menjawab 403. Operator sekolah dan siswa tidak diberi izin ini. Pemegang `rekaman.tandai` saja tidak dapat memindai: izin memindai dan izin menandai/membatalkan penandaan terpisah (atur di menu **Role**).

### Wajib hidup di server

- **HTTPS**: kamera peramban hanya bekerja lewat HTTPS (atau `localhost`). Halaman Pindai menjelaskan hal ini bila kamera tidak tersedia dan menautkan ke penandaan manual.
- **Cron `schedule:run` setiap menit**: perintah baru `sijebol:tandai-tidak-hadir` berjalan tiap jam. Tanpa cron, siswa yang tidak datang tetap berstatus Terdaftar dan kursinya tidak dilepas.

### Perilaku yang perlu diketahui petugas

- Pemindaian dibuka pada **tanggal sesi saja**, mulai 60 menit sebelum jam mulai sampai 60 menit setelah jam selesai (`scan_tolerance_minutes`). Di luar itu tiket ditolak dengan pesan jam dibuka; penandaan manual tetap tersedia pada hari sesi.
- Siswa **Terdaftar yang tidak terpindai** otomatis menjadi **Tidak hadir** mulai **hari setelah sesi** (bukan begitu jam selesai lewat), sehingga seluruh hari-H masih dapat dipakai untuk penandaan manual. Kursi dilepas dan siswa dapat mendaftar sesi lain keesokan harinya. Tidak ada notifikasi untuk status ini.
- **Batalkan penandaan** (Penandaan rekaman) mengembalikan siswa ke Terdaftar dan mengosongkan waktu pindai, sehingga tiket dapat dipindai lagi.
- Penandaan manual tidak mengisi waktu pindai; tiket yang ditandai manual dijawab "Siswa sudah ditandai sudah rekaman."
- Setiap pindaian sukses dan setiap penolakan atas tiket yang dikenal tercatat di **Log aktivitas** (`Tiket dipindai`, `Pindai tiket ditolak`). Kode yang tidak dikenal tidak dicatat. Pindaian tidak mengirim notifikasi ke operator sekolah.

### Konfigurasi (`config/sijebol.php`)

| Kunci | Nilai awal | Arti |
|---|---|---|
| `scan_tolerance_minutes` | 60 | Pemindaian dibuka sekian menit sebelum jam mulai dan ditutup sekian menit setelah jam selesai |

### Beranda dan Laporan

- Sesi yang **dibatalkan** tidak lagi tampil di Jadwal terdekat, tabel jadwal, dan kalender beranda. Tabel jadwal menampilkan kursi terisi/kuota, jumlah menunggu verifikasi, dan jumlah sudah rekaman; sesi **Hari Ini** punya tautan **Pindai** bagi pemegang izin.
- Tren bulanan **Sudah Rekam** memakai bulan **tanggal sesi** (sebelumnya bulan terakhir data diubah). Angka historis dapat bergeser setelah rilis ini.
- Aksi cepat **Input Hasil Perekaman** diganti **Pindai Tiket**.
- Halaman **Laporan** mendapat tabel **Kehadiran per sesi** (menurut tanggal sesi) dan ekspor Excel kedua (`rekap-kehadiran-sesi.xlsx`). Rekap pendaftaran per sekolah tetap menurut tanggal pendaftaran. Kehadiran = (Sudah rekaman + Belum rekaman) dibagi (Sudah rekaman + Belum rekaman + Tidak hadir).

### Gerbang rilis

Penelusuran manual pada rencana 7d (Task 8) harus lulus di DB dev sebelum rilis: pindai QR dari HP lain lewat HTTPS, pindai ulang, tiket sesi lain, batalkan penandaan lalu pindai lagi, jalankan `sijebol:tandai-tidak-hadir` setelah menggeser tanggal sesi, periksa label KTP-el langsung, beranda, dan kedua ekspor.

## 13. Catatan Batas Waktu Pendaftaran per Sesi

Admin menetapkan **batas pendaftaran** (tanggal dan jam) pada tiap sesi rekaman; aturan global "tutup N hari sebelum sesi" tidak lagi menentukan. Sesi yang batasnya lewat tetap tampil bagi siswa dengan ribbon "Pendaftaran ditutup" dan pendaftarannya ditolak server.

```bash
php artisan migrate --force    # menambah kolom schedules.registration_closes_at; TANPA seeder
```

- Sesi yang sudah ada diisi otomatis: tanggal sesi dikurangi 2 hari, pukul 23.59.59 (sama dengan aturan lama), jadi perilakunya tidak berubah sampai admin mengubah batasnya lewat **Ubah** sesi.
- `registration_close_days` di `config/sijebol.php` kini hanya isian awal form Buat sesi (batas = tanggal sesi dikurangi nilai ini, pukul 23.59). Admin bebas mengubahnya per sesi, termasuk ke hari yang sama untuk sesi mendadak.
- Siswa yang sudah terdaftar tetap dapat membatalkan atau mengajukan ulang sampai sehari sebelum sesi (aturan terpisah dari batas pendaftaran).

## 14. Catatan Daftar Rekaman & KTP-el

Halaman baru **Laporan > Laporan KTP-el pelajar** (daftar per siswa: sudah rekaman dengan KTP-el langsung terbit, sudah rekaman tetapi menunggu genap 17 tahun, dan belum rekaman) beserta unduhan Excel.

```bash
php artisan db:seed --class=AccessSeeder --force   # WAJIB: membuat izin laporan.rekaman dan laporan.unduh-nik; tanpa migrasi
```

- Tanpa seeder, item menu tidak muncul dan rutenya menjawab 403. Secara bawaan hanya `super-admin` dan `admin-kecamatan` yang memegang kedua izin; role lain diatur lewat menu **Role**.
- `laporan.rekaman` membuka halaman dan Excel (NIK termasking). `laporan.unduh-nik` menambah NIK penuh di Excel. Pengguna terikat sekolah yang diberi izin ini hanya melihat siswa sekolahnya.
- "Terbit" berarti **memenuhi syarat umur** (lebih dari 17 tahun pada tanggal sesi rekaman). Sistem tidak mencatat KTP-el yang sudah dicetak Disdukcapil.
- Setiap unduhan tercatat di Log aktivitas (`laporan.diekspor`, `jenis: ktp-el-pelajar`, filter, `nik_penuh`, jumlah baris).

## 15. Data dummy (lokal dan UAT)

Untuk menguji daftar, filter, laporan, dan Excel dengan data banyak, serta sebagai bahan pengenalan ke sekolah dan simulasi client di UAT:

```bash
php artisan sijebol:isi-data-dummy             # 1.500 siswa + sesi, pendaftaran (semua status), dokumen, akun
php artisan sijebol:isi-data-dummy --siswa=300 # jumlah lain
php artisan sijebol:isi-data-dummy --bersihkan # hapus semua yang bertanda dummy
```

- Memakai **sekolah yang sudah ada** di database tempat perintah dijalankan (sekolah tidak diubah) dan butuh `AccessSeeder` sudah dijalankan. Tidak perlu sekolah tertentu; tanpa sekolah perintah berhenti.
- Penanda dummy: NIK berawalan `9999`, NISN `99`, email `@dummy.sijebol.test`. Menjalankan ulang membersihkan dummy lama dulu; data asli tidak disentuh. `--bersihkan` melewatkan sesi dummy yang sudah dipakai pendaftaran asli.
- Akun: `operator.{NPSN}@dummy.sijebol.test` (satu per sekolah) dan `siswa.{NISN}@dummy.sijebol.test` (sekitar 30% siswa). Akun `admin.dummy@…` hanya pembuat sesi dan **tidak bisa dipakai masuk**.
- Log aktivitas dimatikan selama pengisian. Akun dummy hanya menerima notifikasi di aplikasi; **email tidak pernah dikirim** ke `@dummy.sijebol.test` (pengingat otomatis dan notifikasi verifikasi tidak ikut bounce di Resend).

### Lokal
Kata sandi akun dummy `password`. Perintah hanya jalan bila `APP_ENV=local`.

### UAT
`fakerphp/faker` sudah di `require` (image dibangun `--no-dev`), jadi perintah tersedia setelah deploy. Perintah menolak jalan di UAT kecuali saklar dinyalakan; **jangan nyalakan di produksi**.

```bash
# 1. Di uat/.env, hanya selama data dummy dipakai:
SIJEBOL_ALLOW_DUMMY_DATA=true
# 2. Muat ulang konfigurasi, lalu isi dengan kata sandi yang kuat (akun dummy memakai kata sandi yang sama):
docker exec -it sijebol-uat php artisan config:clear
docker exec -it sijebol-uat php artisan sijebol:isi-data-dummy --siswa=600 --sandi='<kata sandi kuat>'
```

- Di luar lokal `--sandi` wajib diganti (minimal 8 karakter, bukan `password`) dan perintah meminta konfirmasi (lewati dengan `--force`). UAT terbuka ke internet dan alamat operator bisa ditebak dari NPSN, jadi kata sandi bawaan tidak boleh dipakai.
- Bagikan kata sandi hanya kepada sekolah/client yang ikut simulasi. Setelah simulasi: `--bersihkan`, lalu hapus `SIJEBOL_ALLOW_DUMMY_DATA` dari `uat/.env`.
- Dummy ikut tampil di daftar sekolah asli di UAT, dan jadwal otomatis (kedaluwarsa, tidak hadir) ikut memproses pendaftaran dummy seperti data biasa.

## 16. Perilaku AccessSeeder (aditif)

`php artisan db:seed --class=AccessSeeder --force` aman dijalankan ulang di server yang role-nya sudah diubah lewat menu **Role & Permission**.

- **Role** dibuat bila belum ada. Role yang sudah ada tidak ditimpa: izin, deskripsi, dan pengaturan "terikat sekolah" tetap.
- **Izin baru** (belum ada di database) dibuat, lalu diberikan hanya ke role bawaan yang memang mendapatkannya. Izin yang sudah ada tidak diberikan lagi, sehingga izin yang sengaja dicabut dari suatu role tidak kembali.
- **Izin yang sudah dihapus dari kode** dibersihkan dari database dan dari semua role.
- **`super-admin`** selalu disinkron penuh (role dilindungi, memegang semua izin).
- Role bawaan yang terhapus dibuat kembali lengkap dengan izin bawaannya.

Konsekuensinya: **mengurangi** izin bawaan sebuah role di kode tidak otomatis berlaku di server yang sudah berjalan. Pencabutan semacam itu harus dilakukan lewat menu Role atau lewat migrasi data, dan dicatat di bagian rilis yang bersangkutan.

## 17. Deploy produksi: cadangan dan migrasi terkendali

Mulai 2 Nov 2026 (temuan F-02), migrasi di **produksi tidak lagi otomatis** saat kontainer mulai (`AUTO_MIGRATE=false` di `docker-compose.prod.yml`). Sebelumnya setiap start/restart kontainer menjalankan `migrate --force`; migrasi yang gagal membuat kontainer crash-loop dan tidak ada cadangan. **UAT tetap otomatis** (`AUTO_MIGRATE=true`).

```bash
bash scripts/deploy-prod.sh          # interaktif (menanyakan konfirmasi)
bash scripts/deploy-prod.sh --yes    # tanpa konfirmasi, untuk otomasi
```

Urutan skrip:

1. `git pull origin main`, lalu **build image baru** (aplikasi lama tetap melayani).
2. Menampilkan **migrasi yang akan dijalankan** dan meminta konfirmasi. Dibatalkan = tidak ada yang berubah.
3. `artisan down` (mode pemeliharaan; status tersimpan di `storage`, jadi kontainer baru juga langsung dalam mode itu).
4. **Cadangan database** (`mysqldump --single-transaction`) ke `/opt/sijebol/backups/<db>-<stempel>.sql.gz` (izin folder 700, berkas 600). **Cadangan gagal = deploy berhenti** dan aplikasi dibuka kembali. Cadangan lebih dari **14 hari** dihapus, hanya setelah cadangan baru berhasil.
5. Mengganti kontainer, menunggu siap, lalu `migrate --force`, `queue:restart`, cache, dan `artisan up`.

Bila migrasi gagal, aplikasi **tetap dalam mode pemeliharaan** dan skrip mencetak lokasi cadangan serta perintah pemulihan. Setelah diperbaiki: `docker exec sijebol-prod php artisan up`.

Memulihkan cadangan:

```bash
export MYSQL_PWD='<DB_PASSWORD dari .env>'
gunzip -c /opt/sijebol/backups/sijebol_prod-YYYYMMDD-HHMMSS.sql.gz \
  | docker exec -i -e MYSQL_PWD sijebol-mysql mysql -usijebol sijebol_prod
unset MYSQL_PWD
```

Catatan:
- Cadangan hanya ada di VPS yang sama. **Salinan di luar VPS tetap harus diatur terpisah** (tidak ditangani skrip ini).
- Pada instalasi pertama, pesan "status migrasi tidak terbaca" saat langkah 2 wajar (tabel `migrations` belum ada).
- Rilis produksi pertama: migrasi `convert_registrations_to_session_registrations` menolak berjalan bila `registration_students` atau `schedules` sudah berisi data. Database produksi saat ini kosong, sehingga lolos. Bila kelak ada data uji, jalankan `php artisan sijebol:bersihkan-data-siswa` lebih dulu.
- Skrip bantu (`scripts/lib/deploy.sh`) diuji dengan `bash scripts/test-deploy-lib.sh` (tanpa Docker sungguhan).

## 18. Pengaman pendaftaran siswa: Cloudflare Turnstile dan batas per email

Mulai 2 Nov 2026 (temuan F-03) halaman pendaftaran siswa publik (`/register/{token}`) dilindungi dua lapis:

- **Batas per alamat email**: maksimal 3 email pendaftaran per alamat per 15 menit. Di atas batas, permintaan diabaikan diam-diam dan respons ke pengguna tetap sama. Tidak perlu pengaturan.
- **Cloudflare Turnstile** (captcha gratis, tak terlihat kecuali Cloudflare butuh interaksi). **Aktif hanya bila `TURNSTILE_SECRET_KEY` terisi.** Kosong = nonaktif, jadi pendaftaran terbuka tanpa captcha.

### Mengaktifkan di UAT dan produksi (wajib)

1. Dashboard Cloudflare → **Turnstile** → **Add widget**. Isi domain `sungaibeduk.sijebol.online` (produksi) dan domain UAT; satu widget boleh mencakup keduanya. Mode: **Managed**.
2. Salin **Site Key** dan **Secret Key** ke `.env` masing-masing lingkungan:

   ```
   TURNSTILE_SITE_KEY=...
   TURNSTILE_SECRET_KEY=...
   ```

3. Terapkan: `docker exec <kontainer> php artisan config:cache` (kunci dibaca saat runtime, tidak perlu build ulang image).
4. Cek: buka link pendaftaran sekolah; tombol "Buat akun" aktif setelah teks "Memeriksa keamanan…" hilang, dan pendaftaran berhasil.

Kunci uji Cloudflare (selalu lolos) untuk lokal: site `1x00000000000000000000AA`, secret `1x0000000000000000000000000000000AA`.

### Perilaku yang perlu diketahui

- Bila Cloudflare tidak terjangkau dari server, pendaftaran **ditolak** dengan pesan "Verifikasi keamanan gagal…" (sengaja gagal tertutup supaya kuota email tidak terkuras). Pendaftaran pulih sendiri saat Cloudflare terjangkau.
- CSP (`SecurityHeaders`) otomatis mengizinkan `https://challenges.cloudflare.com` hanya pada rute `register.*` dan hanya bila site key terisi.
- Kuota email gratis Resend (100/hari) tetap tidak cukup untuk pendaftaran massal; lihat F-29 di berkas review.

## 19. Batas wewenang admin (F-04, F-05, F-06): pengecekan data lama

Mulai 4 Nov 2026 pemeriksaan pelaku ditambahkan di tiga tempat. Tidak ada migrasi, tetapi sebelum rilis ke server yang sudah berisi data, cek dua hal berikut (MySQL).

**1. Role terikat sekolah yang memegang izin kecamatan-wide** (F-04). Aplikasi kini menolak aksinya, tetapi izinnya sebaiknya dicabut lewat menu Role:

```sql
SELECT r.name AS role, p.name AS izin
FROM roles r
JOIN role_has_permissions rp ON rp.role_id = r.id
JOIN permissions p ON p.id = rp.permission_id
WHERE r.is_school_scoped = 1
  AND p.name IN ('sekolah.buat', 'sekolah.ubah', 'sekolah.hapus', 'log.lihat', 'laporan.lihat');
```

**2. Akun siswa tanpa data siswa** (F-06; terjadi bila akun dengan role siswa pernah dibuat lewat menu Pengguna):

```sql
SELECT u.id, u.email, u.school_id
FROM users u
JOIN model_has_roles mr ON mr.model_id = u.id AND mr.model_type = 'App\\Models\\User'
JOIN roles r ON r.id = mr.role_id AND r.name = 'siswa'
LEFT JOIN students s ON s.user_id = u.id
WHERE s.id IS NULL;
```

Perilaku baru: akun siswa tidak dapat diubah dari menu Pengguna (hanya nonaktif/aktif dan kirim ulang aktivasi) dan role siswa tidak ditawarkan di form; role dengan izin di luar izin penyunting hanya dapat diubah izinnya, bukan nama, deskripsi, atau jenis terikat sekolahnya.

## 20. Sesi berakhir saat password diganti atau di-reset (F-07)

Mulai 4 Nov 2026, **ganti password** (menu Profil) mengeluarkan semua perangkat lain (perangkat yang dipakai tetap masuk), dan **reset password** lewat email mengakhiri semua sesi akun itu. Cookie "Ingat saya" di perangkat lain ikut tidak berlaku.

Pengakhiran sesi bekerja pada tabel `sessions`, jadi server **wajib** memakai `SESSION_DRIVER=database` (bawaan `.env.example`). Dengan driver lain, ganti dan reset password tetap berhasil tetapi sesi lama tidak ikut berakhir; tombol "Keluarkan perangkat lain" di Profil menolak dengan galat 501 pada driver selain database.

## 21. Batas login per IP dan pengecekan IP pengunjung (F-08)

Mulai 4 Nov 2026, selain batas 5 kegagalan per email+IP, login dikunci per IP setelah **50 kegagalan dalam 10 menit** (semua email digabung; login sukses tidak dihitung dan tidak mereset hitungan). Angkanya dapat diubah di `.env`:

```
SECURITY_LOGIN_IP_FAILURES=50
SECURITY_LOGIN_IP_WINDOW=600   # detik
```

### Wajib dicek sebelum rilis: IP yang sampai ke aplikasi

Semua batas per IP (login, `throttle:5,1` pendaftaran siswa, batas umum untuk tamu) hanya berguna bila aplikasi melihat **IP pengunjung asli**. Alur: pengunjung → Cloudflare → Nginx Proxy Manager → kontainer. `trustProxies('*')` di Laravel hanya mempercayai peer langsung (NPM) lalu membaca `X-Forwarded-For` dari kanan, dan kontainer tidak mempublikasikan port, jadi pemalsuan header dari luar tidak berlaku. Tetapi bila NPM tidak menerjemahkan IP Cloudflare menjadi IP pengunjung, semua orang terlihat berasal dari beberapa IP edge dan limiter akan mengunci banyak orang sekaligus.

Cek di server (setelah ada beberapa orang login):

```sql
SELECT ip_address, COUNT(*) AS sesi FROM sessions GROUP BY ip_address ORDER BY sesi DESC LIMIT 10;
```

- IP beragam dan berasal dari jaringan pengguna (ISP/seluler) = benar.
- Rentang Cloudflare (mis. `104.x`, `172.64.x`–`172.71.x`, `162.158.x`) atau IP Docker (`172.x` dari jaringan `sijebol-network`) mendominasi = proxy perlu dibenahi dulu (NPM: aktifkan penerjemahan IP asli Cloudflare / `real_ip_header`), atau naikkan angka di atas sementara.
