# Maura Laundry

Sistem Manajemen Laundry — PHP Native + MySQLi + Bootstrap 5.

## Stack
- PHP 8.5+ (Native, no framework)
- MySQLi with prepared statements
- Bootstrap 5.3.8 (CDN)
- Bootstrap Icons 1.13.2 (CDN)
- Chart.js 4.5.1 (CDN)
- MySQL / MariaDB

## Instalasi

### 1. Import Database
```sql
mysql -u root -p < database/schema.sql
```

### 2. Konfigurasi Database
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'db_maura_laundry');
```

### 3. Konfigurasi Aplikasi
Edit `config/config.php`:
```php
define('APP_URL', 'http://yourdomain.com/maura-laundry');
define('MAIL_FROM', 'noreply@mauralaundry.com');
```

### 4. Upload ke cPanel
- Upload semua file ke `public_html/maura-laundry/`
- Pastikan `.htaccess` ikut terupload
- Set permission folder: `755`, file: `644`

### 5. Login Default
| Username | Password | Peran |
|---|---|---|
| `superadmin` | `password` | Super Admin |

> **Ganti password segera setelah login pertama!**

---

## Struktur File
```
maura-laundry/
├── .htaccess                  # Apache config, security headers
├── index.php                  # Entry point → redirect
├── config/
│   ├── database.php           # DB constants
│   └── config.php             # App settings, session, timezone
├── includes/
│   ├── auth.php               # Login, logout, RBAC, email verify
│   ├── functions.php          # Helpers: CSRF, flash, paginate, h(), idr()
│   ├── header.php             # HTML head + navbar
│   ├── sidebar.php            # Sidebar navigasi (permission-aware)
│   └── footer.php             # Scripts, closing tags
├── assets/
│   ├── css/style.css          # Layout, sidebar, auth, badges
│   └── js/app.js              # Sidebar toggle, confirm dialogs
├── auth/
│   ├── login.php
│   ├── logout.php
│   ├── forgot-password.php
│   └── verify-email.php
├── pages/
│   ├── dashboard.php          # Statistik order, pendapatan, status terbaru
│   ├── customers/             # index, create, edit, delete
│   ├── services/              # index, create, edit, delete
│   ├── orders/                # index, create, view, update-status
│   ├── payments/              # index, create (catat pembayaran)
│   ├── reports/               # Laporan order & pendapatan
│   └── users/                 # index, create, edit, toggle
└── database/
    └── schema.sql             # DDL + seed data
```

## Peran Default (RBAC)
| Peran | Akses |
|---|---|
| Super Admin | Semua fitur |
| Admin | Semua kecuali hapus user & edit role |
| Kasir | Order, pembayaran, laporan |
| Viewer | Read-only semua |

## Fitur

### PWA, Portal Pelanggan & Dark Mode
- Aplikasi dapat dipasang di ponsel melalui `manifest.json` dan service worker, dengan halaman fallback saat offline
- Portal mobile khusus Pelanggan untuk memantau status order, pembayaran, dan profil
- Dark mode persisten mengikuti preferensi pengguna

### Dashboard
- Statistik real-time: order hari ini, order pending, selesai belum diambil, pendapatan hari ini & bulan ini, total pelanggan
- Tabel 8 order terbaru dengan status badge dan link langsung ke detail

### Master Data
- **Pelanggan** — CRUD; nama, telepon, alamat, email
- **Layanan** — CRUD; nama, tipe (cuci, setrika, dll.), harga satuan, satuan (kg/pcs), estimasi hari selesai, status aktif/nonaktif

### Order
- Buat order: pilih pelanggan + tambah multi-item layanan secara dinamis (JavaScript tanpa reload)
- Kalkulasi subtotal & total otomatis di browser; estimasi selesai dihitung dari `duration_days` layanan terlama
- Nomor order di-generate otomatis
- Update status: `diterima → proses → selesai → diambil`
- Halaman detail order: rincian item, total, histori status
- Daftar order dengan filter & pencarian

### Pickup / Delivery
- Order mendukung tanpa antar-jemput, pickup, delivery, atau keduanya; alamat, kontak, jadwal, biaya, dan status operasional disimpan per order
- Biaya pickup/delivery dihitung server-side ke total order dan tersedia pada daftar, detail, portal pelanggan, serta laporan

### Deposit Pelanggan
- Saldo berbasis ledger immutable (top-up, debit, refund/pengembalian dana) dengan histori petugas dan referensi
- Kasir dapat membayar tepat sebesar sisa order dari saldo deposit; pengecekan saldo, debit ledger, dan payment dilakukan atomik dalam transaksi
- Pelanggan hanya melihat saldo dan histori miliknya dari akun yang terhubung

### Langganan (Order Berulang)
- Definisi langganan: pelanggan, paket item layanan + jumlah, frekuensi (mingguan / 2 mingguan / bulanan), jatuh tempo berikutnya, tipe antar-jemput, alamat, status aktif
- Tombol **Buat Order** mengubah langganan menjadi order nyata dalam satu transaksi, lalu memajukan jatuh tempo ke periode berikutnya; order menyimpan `subscription_id`
- Bulanan: tanggal dibatasi ke akhir bulan bila perlu (mis. 31 Jan → 28 Feb)
- Tidak ada cron: pembuatan order adalah aksi manual staf
- Permission: `subscriptions.view`, `subscriptions.manage`; halaman `pages/subscriptions/`, logika di `includes/subscriptions.php`

### Pembayaran
- Catat pembayaran per order; metode: tunai / transfer / saldo deposit
- Daftar pembayaran dengan filter status & metode

### Laporan
- **Harian** — filter rentang tanggal; kolom tunai, transfer, total per hari; grand total
- **Bulanan** — pilih bulan; ringkasan total pendapatan, total order, hari aktif; tabel transaksi per hari
- **Per Layanan** — filter rentang tanggal; jumlah order, total qty, pendapatan, persentase kontribusi per layanan

### Manajemen User
- CRUD user; nama, username, email, peran
- Toggle aktif/nonaktif; reset password oleh Super Admin

## Keamanan
- Semua query pakai MySQLi prepared statements
- Password di-hash dengan `password_hash()` bcrypt cost=12
- CSRF token di setiap form POST
- Output di-escape dengan `h()` → `htmlspecialchars(ENT_QUOTES, UTF-8)`
- Session `httponly` + `samesite=Strict`
- `.htaccess` blokir akses langsung ke `config/`, `includes/`, `database/`
- Validasi permission di setiap halaman (`require_permission()`)
- Email verifikasi akun via `mail()`
- Forgot & reset password dengan token berumur 1 jam
