# POS & Kitchen Display System

Aplikasi Point of Sale (POS) dan Kitchen Display System (KDS) untuk restoran/kafe — kasir membuat order, dapur menerima order secara real-time via WebSocket, admin memantau laporan dan mengelola menu.

<!-- SCREENSHOT: Admin dashboard showing StatsOverview widgets -->

---

## 🛠 Tech Stack

| Komponen | Versi | Keterangan |
|---|---|---|
| PHP | 8.3 | Runtime |
| Laravel | 13 | Framework |
| Filament | 5 | Admin panel |
| Livewire | 4 | Reactive UI (POS + KDS) |
| Reverb | 1 | WebSocket server |
| Spatie Permission | 7 | RBAC (3 role) |
| Spatie Settings | 3 | RestaurantSettings |
| Flux UI Free | 2 | Komponen UI |
| Tailwind CSS | 4 | Styling |
| Alpine.js | 3 | Client-side interactivity |
| DomPDF | 3 | Invoice + thermal receipt |
| Laravel Excel | 3.1 | Export laporan |
| Pest | 4 | Testing framework |
| MySQL | 8 | Database |

---

## ✨ Fitur Utama

- 🏪 **Admin Panel** (`/admin`) — Filament v5 dengan navigation group: Manajemen Menu, Transaksi, Pengaturan, Laporan
  - 📂 CRUD Kategori (drag-and-drop reorder, ColorPicker, ToggleColumn aktif)
  - 🍽 CRUD Produk (image upload, RichEditor, soft delete + restore, bulk mark available/unavailable)
  - 🪑 CRUD Meja (status badge berwarna)
  - 🛒 Order monitor (READ-ONLY, filter status/kasir/payment/date range, ViewAction modal, link Invoice)
  - 👥 User management (CheckboxList role assignment, password optional saat edit)
  - 📊 Dashboard custom (4 stat cards + line chart 30 hari + top 5 produk + 10 order terakhir)
  - ⚙️ Settings page (`spatie/laravel-settings` integration)
  - 📈 Laporan dengan filter, stats, chart, tabel, export Excel multi-sheet

- 💳 **POS** (`/pos`) — Livewire 4 SFC, dark slate-900 background, layout 60/40
  - 🔍 Search produk real-time (debounced 250ms)
  - 🗂 Tab kategori horizontal scrollable
  - 🛍 Grid produk 2-3-4 kolom dengan badge "Featured" + "Habis"
  - 🧾 Cart reaktif: stepper qty, notes per item (Alpine collapse), discount modal (% atau Rp)
  - 💰 Modal checkout 4 metode (Tunai/QRIS/Transfer/Card), kembalian otomatis, validasi cash≥total
  - ⚡ Auto-broadcast `OrderPlaced` ke channel `kitchen` setelah commit
  - ⌨️ Keyboard shortcut: F1 = search, F2 = checkout, Esc = close modal
  - 📱 Mobile cart badge

- 🍳 **KDS** (`/kitchen`) — full-screen, body bg-black, font ≥16px untuk visibilitas dapur
  - 🕐 Jam digital live (Alpine.js)
  - 🟢 Indikator koneksi WebSocket di header
  - 📋 Grid 1/2/3 kolom kartu order, border 4px berwarna sesuai status (amber=PENDING, sky=PREPARING, emerald=READY)
  - ⏱ Timer per kartu (elapsed time live)
  - 🔔 Notifikasi suara via Web Audio API saat order baru
  - 🎬 Slide-in saat masuk, fade-out saat dismiss
  - ♻️ READY auto-dismiss setelah 30 detik

- 🧾 **Invoice & Receipt**
  - PDF A4 invoice (`/orders/{id}/invoice`)
  - PDF thermal 80mm receipt (`/orders/{id}/receipt`)
  - Footer dinamis dari RestaurantSettings

- 📡 **Realtime Broadcasting** via Laravel Reverb
  - Event: `OrderPlaced`, `OrderCancelled`, `OrderItemStatusUpdated`
  - Channel publik: `kitchen`
  - Semua event implement `ShouldBroadcast` + `ShouldQueue`

---

## 🗂 ERD

Schema database dapat dilihat di [`docs/erd.dbml`](docs/erd.dbml). Tempel ke [dbdiagram.io](https://dbdiagram.io/d) untuk visualisasi interaktif.

<!-- SCREENSHOT: ERD diagram exported from dbdiagram.io -->

Tabel utama:
- `users` (UUID PK) — kasir/dapur/admin
- `categories` + `products` (soft delete pada products)
- `restaurant_tables` — rename dari `tables` untuk hindari reserved word
- `orders` + `order_items` — snapshot `product_name` + `product_price` agar tidak berubah saat menu diedit
- `settings` — key-value via `spatie/laravel-settings`
- `roles` + `model_has_roles` — Spatie Permission, `model_morph_key` = `model_uuid` agar konsisten dengan UUID PK

---

## 📦 Cara Install

### Prasyarat
- PHP 8.3
- Composer 2
- Node 20+ dan npm
- MySQL 8 (lokal atau via Docker)

### Setup

```bash
# Clone & install dependencies
git clone <repo-url> restaurant-pos-app
cd restaurant-pos-app
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Sesuaikan kredensial DB di .env, lalu:
php artisan migrate --seed
php artisan storage:link

# Build assets
npm run build
```

`.env` minimum yang harus diisi:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kitchen_pos
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=kitchen-pos
REVERB_APP_KEY=kitchen-pos-key
REVERB_APP_SECRET=kitchen-pos-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## 🔑 Default Credentials

Setelah `php artisan db:seed`, login dengan akun berikut (semuanya password `password`):

| Email | Password | Role | Auto-redirect |
|---|---|---|---|
| `admin@pos.test` | `password` | `super_admin` | `/admin` |
| `kasir@pos.test` | `password` | `cashier` | `/pos` |
| `dapur@pos.test` | `password` | `kitchen` | `/kitchen` |

---

## 🚀 Menjalankan Service

Aplikasi membutuhkan **3 process** untuk berjalan penuh:

```bash
# Terminal 1: Laravel HTTP server
php artisan serve

# Terminal 2: Reverb WebSocket server (untuk KDS realtime)
php artisan reverb:start

# Terminal 3: Queue worker (untuk broadcast event)
php artisan queue:work
```

Atau jalankan ketiganya sekaligus dengan:

```bash
composer run dev
```

Frontend dev (Vite hot reload):

```bash
npm run dev
```

---

## 📸 Screenshots

<!-- SCREENSHOT: Admin dashboard showing StatsOverview widgets -->
<!-- SCREENSHOT: POS interface with items in cart -->
<!-- SCREENSHOT: KDS showing active order cards -->
<!-- SCREENSHOT: Invoice PDF output -->

---

## 🗃 Struktur Folder Penting

```
app/
├── Actions/Pos/             ← CheckoutOrder + GenerateOrderNumber (atomic order persistence)
├── Events/                  ← OrderPlaced, OrderCancelled, OrderItemStatusUpdated (broadcast)
├── Exports/                 ← OrdersExport (multi-sheet) + OrdersSummaryExport / OrdersDetailExport
├── Filament/
│   ├── Pages/               ← Dashboard, ManageRestaurantSettings, Reports
│   ├── Resources/           ← Categories, Products, RestaurantTables, Orders, Users
│   └── Widgets/             ← StatsOverview, OrdersChart, TopProductsTable, RecentOrdersTable
├── Helpers/                 ← CurrencyHelper.php (rupiah() global function)
├── Http/
│   ├── Controllers/         ← InvoiceController (PDF + thermal receipt)
│   ├── Middleware/          ← RedirectByRole
│   └── Responses/           ← Custom Fortify LoginResponse (role-based redirect)
├── Models/                  ← Category, Product, RestaurantTable, Order, OrderItem, User
├── Policies/                ← OrderPolicy (cashier-owner + admin-bypass)
└── Settings/                ← RestaurantSettings (spatie/laravel-settings)

resources/
├── views/
│   ├── layouts/             ← pos.blade.php, kitchen.blade.php (full-screen, dark)
│   ├── pages/
│   │   ├── ⚡pos.blade.php       ← POS Livewire SFC
│   │   └── ⚡kitchen.blade.php   ← KDS Livewire SFC
│   ├── pdf/                 ← invoice.blade.php (A4) + receipt.blade.php (80mm)
│   └── filament/pages/      ← reports.blade.php
└── js/
    ├── app.js               ← Vite entry
    └── echo.js              ← Laravel Echo + Reverb

database/
├── migrations/              ← UUID schemas matching docs/erd.dbml
├── seeders/                 ← Role / User / Category / Product / RestaurantTable
└── settings/                ← Spatie settings migrations (RestaurantSettings defaults)

docs/
└── erd.dbml                 ← dbdiagram.io schema (single source of truth)

routes/
├── web.php                  ← Public + role-protected routes (/pos, /kitchen, /orders/{id}/invoice)
├── channels.php             ← Public 'kitchen' broadcast channel
└── settings.php             ← Account settings pages (Fortify)

tests/Feature/               ← 200+ Pest tests, including E2EFlowTest covering full lifecycle
```

---

## 🧪 Testing

```bash
php artisan test --compact
```

Konfigurasi: `phpunit.xml` mengarah ke MySQL test DB `kitchen_pos_test`. Buat database test sebelum run pertama:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS kitchen_pos_test"
```

Suite mencakup unit + integration test untuk model, resources Filament, broadcast events, POS cart, checkout flow (DB transaction + retry on unique violation), KDS realtime listeners, invoice authorization, reports computation, dan end-to-end lifecycle.

---

## 🪪 License

MIT
