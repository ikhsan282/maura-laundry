# Maura Laundry

Sistem Manajemen Laundry — PHP Native + MySQLi + Bootstrap 5.

## Stack
- PHP 7.4+ (Native, no framework)
- MySQLi with prepared statements
- Bootstrap 5.3 + Bootstrap Icons (CDN)
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
- ✅ Login / Logout dengan session
- ✅ CSRF protection di semua form
- ✅ Email verifikasi via `mail()`
- ✅ Forgot & reset password (token 1 jam)
- ✅ RBAC: roles + permissions + role_permissions
- ✅ Master data: layanan (jenis & harga), pelanggan
- ✅ Buat order baru (multi-item layanan)
- ✅ Update status order (diterima → proses → selesai → diambil)
- ✅ Catat pembayaran (tunai / transfer)
- ✅ Laporan order & rekap pendapatan
- ✅ Dashboard statistik real-time
- ✅ Pagination di semua list
- ✅ Responsive (Bootstrap 5)

## Keamanan
- Semua query pakai MySQLi prepared statements
- Password di-hash dengan `password_hash()` bcrypt cost=12
- CSRF token di setiap form POST
- Output di-escape dengan `h()` → `htmlspecialchars(ENT_QUOTES, UTF-8)`
- Session `httponly` + `samesite=Strict`
- `.htaccess` blokir akses langsung ke `config/`, `includes/`, `database/`
- Validasi permission di setiap halaman (`require_perm()`)
