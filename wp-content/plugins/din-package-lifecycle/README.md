# DIN Package Lifecycle 1.0.3

Plugin pendamping DIN Order Attach untuk masa aktif guest post. Memakai hook WooCommerce, tabel `{$wpdb->prefix}din_packages`, dan Action Scheduler bawaan WooCommerce. Tidak mengubah core WooCommerce atau DIN Order Attach. Dashboard terintegrasi dengan template pada child theme Guest Post Monitoring.

## Satu tampilan order buyer (1.0.3)

- **My Account → Orders** menampilkan order asal sekali. Order renewal/upgrade yang seluruh item produknya terkait satu order asal dikelompokkan ke riwayat order tersebut. Filter dijalankan sebelum pagination; jumlah halaman tetap mengikuti order yang terlihat.
- Order campuran dengan pembelian baru, transaksi beberapa order asal, metadata invalid, atau order asal yang tidak tersedia tetap tampil sendiri agar tidak menyembunyikan item lain. Riwayat terkait menandai nominalnya sebagai total transaksi bersama.
- Badge **UPGRADED TO LIFETIME** berasal dari event `lifetime` yang sudah diterapkan, bukan nama produk atau status Completed. Order dengan beberapa paket memakai jumlah paket yang telah di-upgrade; badge per paket tersedia dalam detail.
- **UPGRADE PENDING** berarti transaksi upgrade belum diterapkan. Transaksi Failed/Cancelled/Refunded tidak mendapat badge pending. Lifetime yang dibeli langsung tidak diberi badge upgrade. Stopped/Pending Review tetap menjadi status layanan tersendiri, tanpa menghapus riwayat upgrade yang sudah terjadi.
- Detail order asal menampilkan nama/tipe paket saat ini, Heading Post, masa layanan, status, bukti, riwayat transaksi beserta nominal/refund, dan timeline aktivasi/renewal/upgrade/penghentian. Nama terbaru hanya proyeksi tampilan; item dan harga pada receipt asli tidak diubah.
- Tombol pembayaran memakai URL order-pay WooCommerce asli dan ditampilkan hanya ketika transaksi membutuhkan pembayaran serta pilihan paket masih valid. Link ke detail transaksi upgrade, bukti, dan receipt asli tetap tersedia. Link lama/email ke transaksi upgrade tidak dialihkan paksa: halaman tersebut memberi tautan kembali ke order utama.
- Admin WooCommerce, checkout, status pembayaran, invoice, dan tabel paket tidak dimodifikasi. Tidak ada penggabungan, penghapusan, atau migrasi order. Pengelompokan menggunakan API WooCommerce (HPOS/legacy) serta state paket yang sudah ada, diperiksa ulang terhadap kepemilikan buyer dan order asal.
- Riwayat buyer dibaca bertahap 100 order/paket dan disimpan hanya dalam cache request; untuk akun dengan riwayat sangat besar, pertimbangkan indeks metadata order asal. Uji tanpa data nyata: `php tests/orders-smoke.php`.

## Penghentian paket oleh admin (1.0.2)

- Buka **order asal** paket → **Package Validity Period** → **Hentikan Paket** pada baris paket yang diinginkan. Untuk paket hasil upgrade, gunakan order asal Annual, bukan order transaksi upgrade.
- Halaman konfirmasi terpisah meminta alasan dan centang persetujuan buyer sebelum tombol **Ya, Hentikan Paket**. Alasan terlihat oleh buyer. Hanya admin/shop manager dengan akses pengelolaan WooCommerce dan edit order asal yang dapat melakukannya.
- Berlaku per paket Annual/Lifetime yang sudah aktif. Status **Stopped**, tanggal/waktu server, ID admin, dan alasan disimpan dalam state serta riwayat paket yang sudah ada; tidak memerlukan migrasi tabel. Paket lain dalam order yang sama tidak berubah.
- Order, pembayaran, bukti, tanggal aktivasi/expiry historis, dan riwayat tetap utuh. Tidak ada refund, perubahan status transaksi, atau penghapusan data. Pengiriman form penghentian tidak menyimpan form Edit order.
- Paket Stopped tidak tampil sebagai layanan berjalan di dashboard, tetapi tetap terlihat di **My Package** dengan tanggal penghentian, alasan, dan link bukti. Renewal/upgrade ditolak termasuk dari cart lama. Pengingat masa aktif dibatalkan; jika pengiriman sedang berlangsung, admin diminta mencoba lagi setelahnya.
- Tidak ada tombol reaktivasi; menyimpan ulang Completed tidak mengaktifkan paket Stopped. Fitur ini mengatur status layanan di aplikasi, bukan otomatis mencabut publikasi/link di situs pihak ketiga.
- Pemeriksaan terisolasi: `core-smoke.php`, `admin-smoke.php`, `dashboard-smoke.php`, dan `mail-smoke.php` mencakup otorisasi, konfirmasi, penghentian per paket, riwayat, race condition, rendering dan pembatalan pengingat. Semua fixture berada di memori.

## Perubahan 1.0.1 — panel dashboard kondisional

- Ada order Pending/On hold/Processing: tampil **Order Progress**, termasuk jika ada paket berjalan. Order Completed terbaru tidak menutupi order belum selesai yang lebih lama.
- Tidak ada order berjalan: tampil maksimum tiga paket **Aktif / Segera berakhir / Lifetime**. Paket Pending, Berakhir, atau Perlu peninjauan tidak memenuhi kondisi ini.
- Keduanya tidak ada: tampil empty state Order Progress, tanpa kartu My Package kosong.
- Invoice tetap mengikuti order terbaru yang memenuhi kriteria sebelumnya; menu My Package tetap menampilkan seluruh status paket. Hook dashboard milik plugin lain tetap dijalankan.
- Perubahan hanya tampilan; tidak memodifikasi order, masa aktif, bukti, atau jadwal email.
- Diverifikasi dengan lima smoke test (termasuk template dashboard nyata memakai data in-memory), lint PHP, dan dashboard lokal dengan order On hold. Dua pengujian lama diselaraskan dengan label Inggris/format HTML yang sudah ada; label UI tidak dikembalikan ke bahasa sebelumnya.

## Penggunaan

1. Buyer login/membuat akun dan checkout produk Annual/Lifetime. Setiap quantity menjadi satu paket terpisah; satu order dapat berisi beberapa paket.
2. Admin atau Shop Manager membuka **Edit order**, mengunggah bukti DIN Order Attach, memilih **Completed**, lalu **Update**. Minimal satu file valid yang benar-benar tersimpan berlaku untuk seluruh item order. Aktivasi dimulai ketika kedua syarat terpenuhi dalam penyimpanan admin, hanya sekali.
3. Buyer membuka **My Account → My Package**. Dashboard menampilkan tiga paket berjalan terbaru jika tidak ada order yang masih dalam proses. Halaman paket menampilkan Heading Post, order/bukti, periode, tanggal mulai/akhir, sisa hari, dan status.
4. Perpanjangan 1/2 tahun tersedia mulai H-14 sampai kedaluwarsa. Upgrade Lifetime tersedia sejak Annual aktif. Pilihan masuk cart WooCommerce tanpa menghapus belanja lain; buyer melihat total sebelum checkout. Satu pilihan per paket, quantity satu; dua tahun berarti harga dua tahun, bukan dua unit layanan.
5. Pada order renewal/upgrade, admin memverifikasi pembayaran, mencentang **Pembayaran renewal/upgrade sudah saya verifikasi**, lalu menyimpan **Completed**. Bukti order asal dipakai kembali. WooCommerce mengisi `date_paid` saat Completed manual, sehingga tanggal itu tidak dipakai sendirian sebagai bukti pembayaran.

Pada checkout klasik (`[woocommerce_checkout]`), upgrade Annual → Lifetime menampilkan **Heading Post** paket asal sebagai informasi baca-saja di **Additional information**. Jika cart juga berisi pembelian baru, kolom Heading Post untuk publikasi baru tetap wajib diisi. Jalankan `php tests/checkout-heading-smoke.php` dari folder plugin untuk memeriksa tampilan upgrade, kepemilikan, escaping, dan cart campuran tanpa mengubah data.

Aktivasi sengaja melalui panel Edit order, bukan perubahan otomatis/API/bulk. Jika Completed disimpan tanpa bukti, tanggal belum dimulai. Penambahan bukti belakangan memakai waktu penyimpanan tersebut, bukan tanggal Completed lama. Update berulang tidak me-reset tanggal.

## Harga dan konfigurasi

**Products → Edit product → Product data → General → Masa aktif paket**:

- Annual, Lifetime, atau Bukan paket.
- ID produk Annual untuk renewal dan ID produk Lifetime untuk upgrade; gunakan ID produk simple atau variasi yang dapat dibeli.
- Konfigurasi disalin saat checkout. Perubahan katalog tidak mengubah hak/periode paket historis. Produk tanpa metadata eksplisit dapat dikenali dari atribut `billing`/`pa_billing` persis `year`, `yearly`, `annual`, atau `lifetime`.
- Harga mengikuti katalog saat checkout: 1 tahun = 1 × Annual, 2 tahun = 2 × Annual, upgrade = harga penuh Lifetime. Tidak ada prorata/kredit Annual atau auto-debit. Pajak/kupon mengikuti WooCommerce.

Konfigurasi lokal yang diterapkan 7 September 2026, tanpa perubahan harga:

| Layanan | Annual | Lifetime |
|---|---|---|
| Guestpost | #91 — 35 | #90 — 250 |
| Link Insertion | #83 — 35 | #51 — 180 |

Annual yang belum berakhir ditambah dari tanggal akhir lama. Setelah berakhir, dihitung dari persetujuan admin. Penambahan tahun memakai kalender zona waktu situs, termasuk 29 Februari → 28 Februari pada tahun nonkabisat. Lifetime mempertahankan tanggal aktivasi pertama/riwayat dan tidak punya kedaluwarsa. Harga/order asal tetap utuh.

## Email dan pemantauan internal

- Email H-14 dan saat berakhir memakai template/transport WooCommerce; tidak membuat Customer Note tambahan. Email batch DIN Order Attach tetap terpisah.
- Queue lama dibatalkan atau diabaikan setelah tanggal berubah/Lifetime/review. Handler memeriksa ulang pemilik, status order, tanggal, versi paket, dan klaim kirim.
- Kegagalan pengiriman yang tegas dicoba maksimum **3 kali total**. Hasil transport tidak pasti atau proses mati ditandai `uncertain`, dicatat di **WooCommerce → Status → Logs**, sumber `din-package-lifecycle`; tidak otomatis dikirim ulang untuk menghindari duplikat. `sent` berarti transport menerima, bukan jaminan inbox.
- **WooCommerce → Status → Scheduled Actions** / **Tools → Scheduled Actions** memuat `din_packages_send_email` dan `din_packages_reconcile`. Rekonsiliasi per jam memperbaiki queue yang gagal dijadwalkan, maksimal 100 paket per batch.
- Server produksi perlu runner WP-Cron/Action Scheduler yang berjalan tanpa bergantung pada kunjungan. Jangan mengubah waktu order nyata untuk mengetes email. WordPress Studio harus tetap berjalan. Zona waktu situs lokal saat diverifikasi adalah **UTC (+00:00)**; tanggal tampilan mengikuti Settings → General, bukan zona waktu komputer.

## Pengaman dan batas

- Kepemilikan paket, produk, quantity, dan harga divalidasi kembali di server. Bukti tetap diakses melalui pemeriksaan DIN Order Attach.
- Unique order-item/unit dan compare-and-swap revision mencegah duplikasi serta kehilangan durasi saat dua update bersamaan. Riwayat renewal dan perubahan masa aktif tersimpan dalam satu penulisan atomik.
- Refund/cancel yang membalap persetujuan akan menolak renewal yang belum diterapkan. Refund sesudah penerapan, perubahan pemilik/item, atau seluruh bukti hilang ditandai untuk peninjauan; tidak menghapus atau memundurkan masa aktif secara otomatis. Penyelesaian kasus review/refund memerlukan kebijakan/admin, bukan rollback otomatis.
- Order lama **tidak dimigrasikan** atau diberi tanggal mulai otomatis. Migrasi historis memerlukan tanggal dan bukti yang disetujui terpisah; belum disediakan importer.
- Kedaluwarsa tidak menghapus attachment, order, atau artikel penerbit. Menghapus/nonaktifkan plugin tidak otomatis menghapus data; tidak ada uninstall cleanup.
- MVP untuk mata uang toko tunggal, tanpa penagihan berulang otomatis. Perbedaan mata uang order asal ditolak untuk pembelian perubahan paket.

## Verifikasi

Jalankan tiap smoke dengan PHP CLI (pengujian memakai PHP 8.4.23). Semuanya memakai objek tiruan/database in-memory dan tidak mengirim email atau mengubah order buyer:

```powershell
$dinPhp = 'C:/Users/donis/.studio/php-bin/8.4.23-studio-1/php.exe'
Get-ChildItem 'wp-content/plugins/din-package-lifecycle/tests' -Filter '*-smoke.php' | ForEach-Object {
    & $dinPhp $_.FullName
    if ($LASTEXITCODE -ne 0) { throw 'Smoke failed' }
}
```

- `core-smoke.php`: aktivasi/bukti, mixed order/quantity, tanggal kabisat, renewal aktif/expired, CAS contention, duplikasi, benturan refund/cancel, legacy, kepemilikan.
- `customer-smoke.php`: kepemilikan, product binding, input malformed, quantity, harga berulang, isolasi cart biasa, snapshot, akun wajib, render/heading checkout.
- `dashboard-smoke.php`: pemilihan Order Progress/paket/empty state, invoice independen, hook tidak ganda, kepemilikan, dan paket aktif di halaman data berikutnya.
- `admin-smoke.php`: nonce, hak akses, fresh read, prioritas, checkbox pembayaran, konfigurasi produk, panel HPOS/legacy.
- `mail-smoke.php`: H-14/expired, job terlambat/stale, duplikasi, tiga percobaan, lease, source ownership/cache, escaping, pembatalan dan rekonsiliasi.

Integrasi lokal baca-saja:

```powershell
studio --version
studio status
studio wp eval-file wp-content/plugins/din-package-lifecycle/tests/site-verify.php
```

Hasil 7 September 2026: empat smoke dan lint lulus; plugin aktif; schema/index unik, hook classic/Block, prioritas admin 80, kemampuan Administrator/Shop Manager, pasangan katalog, dan jadwal rekonsiliasi lulus. HPOS aktif, nol paket historis dimigrasikan. Dashboard/menu dan empty-state Paket Saya telah diperiksa di browser desktop 1280px/mobile 390px; halaman paket mobile tidak tertutup sidebar.

Panel paket pada editor order HPOS juga sudah tampil dan menunjukkan peringatan order historis tanpa melakukan Update order. Belum diverifikasi: checkout pembayaran end-to-end melalui gateway, batch upload + Completed pada order baru sungguhan, penerimaan email di inbox, serta render admin legacy pada database non-HPOS. Uji staging dengan akun/order uji dan mail capture sebelum produksi. Notice deprecation Elementor yang teramati berasal dari plugin yang sudah ada, bukan plugin ini.

`tests/site-inspect.php` hanya membaca katalog/settings/snippet relevan. `tests/configure-local-catalog.php` adalah setup khusus situs lokal terverifikasi, bukan proses instalasi umum; jangan jalankan di situs lain. Rilis ZIP cukup berisi file utama, `includes/`, `assets/`, dan README, tanpa helper pengujian lokal.
