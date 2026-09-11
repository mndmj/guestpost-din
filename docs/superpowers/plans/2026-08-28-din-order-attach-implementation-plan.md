# Rencana Implementasi DIN Order Attach

**Tanggal:** 28 Agustus 2026  
**Acuan:** `docs/superpowers/specs/2026-08-27-din-order-attach-prd.md`  
**Status:** Tahap 1–7 terverifikasi lokal; uji lintas versi WooCommerce menunggu rilis baru  
**Versi plugin:** Saat ini `1.3.0`; dimulai dari `1.0.0` dan setiap rilis perubahan berikutnya wajib menaikkan nilai `Version` pada header plugin serta ekspektasi versi pada smoke test.  
**Batas:** Dokumen ini bukan implementasi dan tidak mengubah data WordPress.

## 1. Tujuan

Membangun plugin WooCommerce mandiri bernama **DIN Order Attach** untuk:

- Mengunggah beberapa file bukti Guest Post dari halaman order.
- Mengizinkan pengelolaan oleh Administrator dan Shop Manager melalui capability WooCommerce.
- Mengirim satu email *Customer note* setelah satu batch baru disimpan.
- Menampilkan seluruh file aktif pada email dan detail order buyer.
- Membatasi unduhan kepada buyer pemilik order atau staf yang berwenang.
- Menyimpan file di luar akses web langsung dan di luar direktori plugin.
- Tetap bekerja dengan HPOS serta tidak terhapus oleh update WooCommerce.

## 2. Kondisi yang Sudah Diverifikasi

- WordPress Studio CLI tersedia.
- WordPress 7.1, PHP 8.4, dan WooCommerce 11.0.1 aktif.
- Project belum memiliki repository Git.
- Editor order HPOS memanggil `woocommerce_process_shop_order_meta` saat order disimpan.
- Editor HPOS menyediakan `order_edit_form_tag` untuk atribut `multipart/form-data`.
- `WC_Order::add_order_note( ..., true, true )` memicu alur email *Customer note* WooCommerce.
- Template email menyediakan `woocommerce_email_after_order_table`.
- Detail order buyer menyediakan `woocommerce_order_details_after_order_table`.
- Penghapusan order HPOS dan legacy menyediakan `woocommerce_before_delete_order`.

## 3. Struktur Minimum Plugin

```text
wp-content/plugins/din-order-attach/
├── din-order-attach.php
├── includes/
│   ├── class-din-order-attach.php
│   ├── class-din-order-attach-storage.php
│   └── class-din-order-attach-download.php
└── tests/
    └── smoke.php
```

Tidak ada framework, Composer dependency, library upload, template override, stylesheet, atau JavaScript bundle pada MVP. Input multi-file memakai HTML native; konfirmasi hapus memakai dialog browser native.

## 4. Kontrak Data

Gunakan satu meta order privat:

```text
_din_order_attach_files
```

Nilainya adalah daftar record:

```text
id            UUID attachment
name          nama asli yang sudah disanitasi
path          path relatif di dalam storage privat
mime          MIME tervalidasi
size          ukuran byte
uploaded_by   WordPress user ID
uploaded_at   timestamp UTC
```

Aturan:

- Meta dibaca dan ditulis hanya melalui `WC_Order` CRUD/meta API.
- URL publik dan absolute path tidak menjadi sumber data order.
- Path hasil metadata harus selalu diubah menjadi canonical path dan diverifikasi masih berada di bawah storage root.

## 5. Penyimpanan Privat

Default storage berada pada direktori sibling di luar `ABSPATH`, diberi suffix hash site agar tidak berbenturan dengan instalasi lain. Jika deployment memerlukan lokasi berbeda, izinkan override melalui konstanta `DIN_ORDER_ATTACH_STORAGE_ROOT`; tidak perlu halaman Settings.

Saat bootstrap atau upload:

1. Resolve canonical storage root.
2. Tolak root kosong, filesystem root, path di dalam `ABSPATH`, atau path yang tidak writable.
3. Buat subdirektori order dengan ID order.
4. Simpan file memakai UUID dan ekstensi tervalidasi, bukan nama asli.
5. Gagal tertutup: jika storage tidak privat atau tidak writable, jangan menerima upload.

## 6. Strategi Pengujian

Gunakan satu smoke test PHP tanpa framework:

```powershell
studio wp eval-file wp-content/plugins/din-order-attach/tests/smoke.php
```

Test menggunakan `assert`/exception dan berhenti dengan exit non-zero pada kegagalan. Untuk pengujian yang membuat order atau user fixture, minta konfirmasi **Ya** sebelum menghapus fixture sesuai aturan project.

Verifikasi UI dilakukan pada site Studio setelah smoke test lulus. Jangan mengirim email nyata saat test otomatis; intercept `wp_mail` melalui filter sementara dan hitung jumlah pemicu.

## 7. Tahapan Implementasi

### Tahap 1 — Bootstrap dan guard dependency

**File:**

- Buat `wp-content/plugins/din-order-attach/din-order-attach.php`.
- Buat `wp-content/plugins/din-order-attach/includes/class-din-order-attach.php`.
- Buat `wp-content/plugins/din-order-attach/tests/smoke.php`.

**Test dahulu:**

- Plugin memiliki header valid.
- Plugin dapat diaktifkan ketika WooCommerce aktif.
- Plugin tidak fatal ketika WooCommerce tidak tersedia; admin mendapat notice.
- Class utama hanya diinisialisasi sekali.
- Kompatibilitas HPOS dideklarasikan sebelum WooCommerce initialization.

**Implementasi minimum:**

- Bootstrap memuat class yang sudah tersedia pada setiap tahap; Tahap 1 hanya memuat class utama.
- Guard `class_exists( 'WooCommerce' )` sebelum hook fitur.
- Daftarkan kompatibilitas HPOS melalui API WooCommerce.
- Tidak membuat tabel database, opsi, cron, atau activation migration.

**Verifikasi:**

```powershell
studio wp plugin activate din-order-attach
studio wp plugin status din-order-attach
studio wp eval-file wp-content/plugins/din-order-attach/tests/smoke.php
```

### Tahap 2 — Storage, validasi, Atomic batch, dan order metadata

**File:**

- Buat `wp-content/plugins/din-order-attach/includes/class-din-order-attach-storage.php`.
- Perluas `tests/smoke.php`.

**Test dahulu:**

- Storage root berada di luar `ABSPATH` dan writable.
- Format yang diterima hanya PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, dan ZIP.
- File di atas 10 MB ditolak.
- Ekstensi/MIME yang tidak cocok ditolak.
- Nama fisik dihasilkan sistem dan tidak mengandung nama input.
- Batch valid disimpan seluruhnya.
- Jika satu item batch invalid atau gagal dipindahkan, seluruh file baru di-roll back.
- Record meta round-trip melalui `WC_Order` mempertahankan seluruh field.
- Canonical path di luar storage root ditolak.

**Implementasi minimum:**

- Normalisasi struktur multi-file dari `$_FILES`.
- Validasi upload error, ukuran, ekstensi, dan MIME memakai API WordPress/PHP yang tersedia.
- Pre-validasi seluruh batch sebelum commit.
- Pindahkan file ke storage privat dengan nama UUID.
- Jika operasi berikutnya gagal, hapus hanya file yang dibuat oleh batch itu.
- Simpan record ke `_din_order_attach_files` melalui `$order->update_meta_data()` dan `$order->save_meta_data()`.

**Verifikasi:**

```powershell
studio wp eval-file wp-content/plugins/din-order-attach/tests/smoke.php
```

### Tahap 3 — Panel admin dan batch upload saat Update order

**File:**

- Perluas `includes/class-din-order-attach.php`.
- Perluas `tests/smoke.php`.

**Hook:**

- `add_meta_boxes` untuk screen legacy dan `wc_get_page_screen_id( 'shop-order' )`.
- `post_edit_form_tag` untuk legacy.
- `order_edit_form_tag` untuk HPOS.
- `woocommerce_process_shop_order_meta` dengan priority setelah penyimpanan bawaan WooCommerce.

**Test dahulu:**

- User tanpa capability pengelolaan order ditolak.
- Nonce invalid tidak memproses upload.
- Order update tanpa file tidak mengubah meta dan tidak membuat note.
- Multi-file input diteruskan sebagai satu batch.
- Batch valid menambah seluruh record satu kali.
- Order tanpa akun buyer boleh menyimpan file, tetapi tidak membuat notifikasi pelanggan dan menghasilkan admin warning.

**Implementasi minimum:**

- Render meta box **DIN Order Attach** dengan input `multiple` dan `accept` sesuai allowlist.
- Render tabel file aktif: nama, jenis, ukuran, pengunggah, tanggal, unduh, hapus.
- Tambahkan nonce khusus plugin.
- Tambahkan `enctype="multipart/form-data"` pada form order.
- Proses hanya jika input file baru benar-benar tersedia.
- Panggil storage service; tampilkan pesan keberhasilan atau alasan batch gagal.

**Verifikasi UI:**

- Login sebagai Administrator dan Shop Manager.
- Upload dua gambar lalu klik **Update order**.
- Pastikan satu baris per file muncul setelah redirect.
- Ulangi sebagai role tanpa izin dan pastikan kontrol tidak tersedia.

### Tahap 4 — Customer note dan email satu kali

**Status implementasi:** Selesai pada versi `1.1.0`.

**File:**

- Perluas `includes/class-din-order-attach.php`.
- Perluas `tests/smoke.php`.

**Hook:**

- Buat note melalui `$order->add_order_note( $message, true, true )` setelah batch dan meta berhasil disimpan.
- Render daftar attachment melalui `woocommerce_email_after_order_table` hanya ketika email ID adalah `customer_note`.

**Test dahulu:**

- Satu batch berisi beberapa file menghasilkan satu action customer note.
- Email customer note menampilkan seluruh file aktif, bukan hanya file batch terakhir.
- Order update tanpa file baru menghasilkan nol email attachment.
- Manual **Note to customer** juga menyertakan seluruh file aktif.
- Format HTML dan plain-text sama-sama memiliki nama serta link file.
- Order tanpa buyer account tidak memicu email.

**Implementasi minimum:**

- Note otomatis berisi pesan singkat bahwa file Guest Post telah diperbarui.
- Gunakan email bawaan WooCommerce; jangan mendaftarkan class email baru.
- Jangan menempelkan binary file pada email.
- Gunakan URL download terproteksi untuk setiap attachment aktif.

**Verifikasi:**

- Intercept `wp_mail` di smoke test dan pastikan satu pemanggilan untuk satu batch.
- Kirim manual **Note to customer** di UI dan periksa email melalui konfigurasi lokal yang tersedia.

**Hasil verifikasi lokal:** Smoke test memastikan satu pemanggilan customer note per batch, tidak ada note tanpa file atau akun buyer, seluruh file aktif tampil pada email `customer_note` dalam HTML dan plain text, dan tipe email lain tidak berubah. Pengiriman email nyata tidak dijalankan agar tidak menghubungi alamat eksternal saat pengujian otomatis.

### Tahap 5 — Dashboard buyer dan endpoint download

**Status implementasi:** Selesai pada versi `1.2.0`.

**File:**

- Buat `wp-content/plugins/din-order-attach/includes/class-din-order-attach-download.php`.
- Perluas `includes/class-din-order-attach.php`.
- Perluas `tests/smoke.php`.

**Hook:**

- `woocommerce_order_details_after_order_table` untuk daftar file buyer.
- `admin_post_din_order_attach_download` untuk download terautentikasi.
- `admin_post_nopriv_din_order_attach_download` hanya untuk redirect ke login, tidak pernah mengirim file.

**Test dahulu:**

- Buyer pemilik order diizinkan.
- Buyer lain ditolak.
- Guest diarahkan ke login dan tidak menerima isi file.
- Administrator/Shop Manager dengan capability order diizinkan.
- Attachment ID tidak dikenal atau sudah dihapus menghasilkan respons generik.
- Metadata path traversal ditolak.

**Implementasi minimum:**

- Render nama, ukuran, tanggal, dan action **Lihat/Unduh**.
- Bangun URL dari order ID dan attachment UUID; jangan sertakan path.
- Setelah login, resolve order dengan `wc_get_order()` lalu cek ownership/capability.
- Untuk JPG, PNG, dan PDF gunakan disposition `inline`; format lain `attachment`.
- Kirim `Content-Type`, `Content-Length`, `Content-Disposition`, `X-Content-Type-Options: nosniff`, dan header no-cache.
- Bersihkan output buffer lalu stream file; jangan memuat seluruh file ke memory.

**Verifikasi UI:**

- Klik link sebagai buyer pemilik order.
- Coba URL yang sama sebagai buyer berbeda.
- Logout lalu buka link dari email dan pastikan login diperlukan sebelum kembali ke download.

**Hasil verifikasi lokal:** Smoke test memastikan URL tidak memuat path fisik, guest diarahkan ke login, owner dan staf WooCommerce diizinkan, buyer lain/ID tidak dikenal/path traversal ditolak generik, daftar My Account memuat seluruh file aktif, serta file memakai header inline atau attachment yang aman. Verifikasi browser dengan akun buyer dan order berattachment belum dijalankan karena memerlukan fixture/data order nyata.

### Tahap 6 — Penghapusan file dan lifecycle order

**Status implementasi:** Selesai pada versi `1.3.0`.

**File:**

- Perluas `includes/class-din-order-attach.php`.
- Perluas `includes/class-din-order-attach-storage.php`.
- Perluas `tests/smoke.php`.

**Hook:**

- `admin_post_din_order_attach_delete` untuk delete terproteksi.
- `woocommerce_before_delete_order` untuk cleanup saat order dihapus permanen.

**Test dahulu:**

- Delete tanpa nonce/capability ditolak.
- Delete file menghapus record dan file fisik terkait saja.
- Jika penghapusan fisik gagal, metadata tidak dibuang diam-diam.
- Link lama tidak dapat mengunduh file terhapus.
- Memindahkan order ke Trash tidak menghapus file.
- Penghapusan permanen menghapus seluruh file order.
- Deaktivasi/reaktivasi plugin mempertahankan file serta metadata.

**Implementasi minimum:**

- Tautan hapus memiliki nonce dan dialog konfirmasi native.
- Delete tidak mengirim email buyer.
- Jangan membuat `uninstall.php`; uninstall tidak menghapus data pada MVP.
- Log kegagalan cleanup melalui logger WooCommerce tanpa membuka path privat kepada user.

**Hasil verifikasi lokal:** Smoke test memastikan hanya staf dengan capability dan nonce valid yang dapat menghapus, penghapusan satu file tidak memengaruhi file lain, kegagalan fisik mempertahankan metadata, link lama ditolak, Trash/deaktivasi tidak menjalankan cleanup, dan penghapusan permanen menghapus seluruh file order. Tes juga memastikan kegagalan cleanup dicatat tanpa path privat dan plugin tidak memiliki `uninstall.php`.

### Tahap 7 — Verifikasi integrasi dan kompatibilitas

**Status verifikasi:** Selesai untuk alur lokal pada versi `1.3.0`; uji lintas versi belum dapat dijalankan karena WooCommerce lokal `11.0.1` sudah versi publik terbaru.

**Jalankan pemeriksaan penuh:**

```powershell
studio wp eval-file wp-content/plugins/din-order-attach/tests/smoke.php
studio wp plugin status woocommerce
studio wp plugin status din-order-attach
studio wp eval 'echo class_exists("Automattic\\WooCommerce\\Utilities\\OrderUtil") ? "Woo OrderUtil OK" : "missing";'
```

**Verifikasi browser:**

1. Administrator mengunggah beberapa file valid.
2. Satu email customer note dipicu dan berisi seluruh file aktif.
3. Buyer pemilik order melihat file di **My Account > Order details**.
4. Buyer lain dan guest tidak dapat mengunduh.
5. File invalid membatalkan seluruh batch.
6. Update order tanpa file baru tidak mengirim email.
7. Penghapusan meminta konfirmasi dan membuat link lama gagal.
8. HPOS aktif: ulangi upload, email, dashboard, download, dan delete.
9. Deaktivasi/reaktivasi plugin: data tetap tersedia.

**Update-safety check:**

- Catat versi dan backup sebelum pengujian update.
- Jangan menjalankan update WooCommerce tanpa permintaan dan persetujuan eksplisit user.
- Jika user menyetujui test update, verifikasi plugin, meta order, dan file tetap tersedia setelah update.

**Hasil verifikasi lokal:** WooCommerce `11.0.1`, DIN Order Attach `1.3.0`, dan HPOS aktif. Administrator mengunggah dua PDF dalam satu batch; order menghasilkan satu customer note dan satu email lokal yang memuat kedua link tanpa binary attachment. Update order tanpa file tidak menambah note/email, sedangkan batch PDF+PHP ditolak atomik dengan nama file dan alasan pada admin notice. Buyer pemilik melihat serta membuka kedua file, buyer lain ditolak generik, dan guest diarahkan ke login dengan return URL. Delete bernonce menghapus hanya file target dan membuat link lama gagal. Deaktivasi/reaktivasi mempertahankan dua record dan file, lalu penghapusan permanen order membersihkan file yang tersisa. Seluruh order, user, option, mail log, dan file fixture telah dihapus; mode Coming soon dikembalikan ke `yes`. Pada 28 Agustus 2026, dry-run WP-CLI dan katalog WordPress.org menunjukkan WooCommerce `11.0.1` sudah terbaru sehingga tidak ada paket update lintas versi yang dapat diuji. Ulangi pengujian ini setelah rilis WooCommerce berikutnya tersedia.

## 8. Definition of Done

- Seluruh 14 acceptance criteria PRD terpetakan ke test atau verifikasi browser.
- Smoke test selesai dengan exit code 0.
- Tidak ada PHP fatal/error baru pada alur yang diuji.
- Tidak ada perubahan di `wp-admin`, `wp-includes`, plugin WooCommerce, atau theme.
- Tidak ada file attachment tersimpan di direktori plugin.
- Tidak ada URL direct-storage yang dapat dipakai untuk mengambil file.
- Tidak ada email attachment terkirim pada update order tanpa file baru.
- File bertahan setelah deactivate/reactivate.
- Implementasi telah diperiksa dengan HPOS aktif.

## 9. Keputusan yang Sengaja Ditunda

- Cloud/object storage: tambahkan hanya jika volume atau kebutuhan CDN menuntut.
- Versioning file: tambahkan hanya jika replacement history dibutuhkan.
- Settings UI: tambahkan hanya jika tipe, batas ukuran, atau storage perlu dikelola non-developer.
- Guest checkout: tambahkan hanya dengan model otorisasi terpisah yang disetujui.
- Cleanup saat uninstall: tambahkan hanya sebagai action terpisah dengan konfirmasi eksplisit.
