# DIN Package Lifecycle 1.0.5

Plugin pendamping DIN Order Attach untuk masa aktif guest post. Memakai hook WooCommerce, tabel `{$wpdb->prefix}din_packages`, dan Action Scheduler bawaan WooCommerce. Tidak mengubah core WooCommerce atau DIN Order Attach. Dashboard terintegrasi dengan template pada child theme Guest Post Monitoring.

## Custom duration (1.0.5)

- Pada **Package Validity Period → Request renewal / upgrade**, pilih **Custom duration**, lalu masukkan **Number of years** berupa tahun bulat positif, misalnya 3, 4, atau 5. Tidak ada pilihan hari/bulan atau field nominal; input pecahan ditolak, bukan dibulatkan diam-diam. Durasi dibatasi agar tanggal usulan tidak melewati tahun kalender 9999.
- **Harga = harga katalog produk Annual × jumlah tahun**; quantity order tetap satu layanan, pajak dihitung oleh WooCommerce. Contoh harga Annual 125 dan durasi 3 tahun menghasilkan subtotal 375 sebelum pajak. Mengedit New expiry tidak mengubah multiplier harga.
- **New expiry date** otomatis diusulkan berdasarkan expiry saat ini (atau hari ini jika sudah habis), termasuk penyesuaian 29 Februari. Tanggal tetap boleh diedit. Isi alasan, centang **Create payment order**, lalu Update. Pembayaran, verifikasi admin, Completed, dan perlindungan order ganda tetap berlaku seperti sebelumnya.
- Invoice, kolom order admin, dan riwayat buyer menampilkan jumlah tahun yang sama. Pilihan Custom duration hanya tersedia melalui admin/Shop Manager; pilihan buyer 1/2 tahun dan Lifetime serta order lama tetap kompatibel. Tidak ada perubahan paket atau migrasi order otomatis.

## Masa aktif admin dan tanggal publikasi

- **WooCommerce → Orders** memiliki delapan kolom terpisah setelah Status: **Package**, **Period**, **Package Status**, **Start Date**, **Expires / Stopped**, **Remaining Days**, **Original Order**, dan **Upgrade / Renewal**. Berlaku untuk HPOS maupun daftar order legacy. Pilih kolom yang diperlukan melalui **Screen Options**; tabel desktop dapat digeser horizontal tanpa memadatkan isinya.
- Order dengan beberapa paket menampilkan ID paket pada setiap kolom agar nilai dapat dicocokkan. **Expires / Stopped** memakai tanggal penghentian jika Stopped. **Remaining Days** hanya menampilkan hitungan untuk Annual yang berjalan; Lifetime aktif ditandai **No expiration date**, sedangkan paket belum aktif/berhenti/kedaluwarsa menampilkan `—`. Data paket dibaca sekali per baris, bukan diulang untuk setiap kolom.
- Order renewal/upgrade membaca masa aktif paket asal dan menampilkan **Original order** serta status **Awaiting approval / Applied / Not applied**. Pembelian upgrade yang belum disetujui tidak mengubah Annual menjadi Lifetime. Order tanpa data paket menampilkan `—`; membuka daftar tidak membuat atau mengaktifkan paket.
- Untuk **aktivasi baru**, isi **Edit order → Guest Post Result → Publication Date** (field dari GPM Guest Post Results, metadata `_gpm_published_at`). Paket dimulai pukul **00:00 pada tanggal publish**, memakai zona waktu **Settings → General**. Annual berakhir satu tahun kalender sesudahnya; Lifetime tidak kedaluwarsa. Tanggal 29 Februari menjadi 28 Februari pada tahun berikut yang bukan kabisat.
- Tanggal kosong, tidak valid, atau di masa depan menolak aktivasi dan menampilkan pemberitahuan. Bukti DIN Order Attach yang valid, status Completed, dan persetujuan admin tetap wajib. Tanggal persetujuan sebenarnya dicatat terpisah dalam riwayat, bukan dipakai sebagai awal masa layanan.
- Publication Date berlaku untuk seluruh paket pembelian baru dalam satu order. Belum tersedia tanggal publish berbeda per item. Upgrade/perpanjangan tetap menggunakan paket asal, tidak memerlukan tanggal publish baru pada transaksi tersebut dan tidak mereset tanggal mulai.
- **Paket yang sudah aktif tidak dihitung ulang**, walaupun Publication Date diubah/dikosongkan. Tidak ada migrasi otomatis untuk order lama. Lifetime yang dihentikan tetap Stopped; perubahan ini tidak mengaktifkannya kembali.
- **Adjust expiry berbayar (1.0.4):** buka order asal berstatus Completed → **Package Validity Period → Request renewal / upgrade** (menggantikan form Adjust expiry). Pilih **1 Year**, **2 Years**, atau **Lifetime** terlebih dahulu. Annual menampilkan **New expiry date** dengan tanggal usulan dari expiry lama (atau hari ini jika sudah habis) ditambah durasi; tanggal dapat diedit. Lifetime tidak meminta tanggal akhir. Isi alasan (terlihat buyer), centang **Create payment order**, lalu **Update**.
- Form membuat order WooCommerce **Pending payment**, bukan langsung mengubah paket. Harga mengikuti produk Annual × 1/2 atau harga penuh Lifetime; memilih tanggal khusus tidak mengubah harga/prorata. Tanggal Annual mempertahankan jam expiry sebelumnya dalam zona waktu situs, harus di masa depan dan melewati expiry lama. Paket Lifetime, Stopped, belum aktif, atau Pending Review tidak dapat memakai form ini. Form yang tidak dicentang tidak membuat order.
- Buyer menerima invoice WooCommerce dengan link pembayaran, durasi, tanggal akhir yang diminta, dan alasan; transaksi juga tersedia di riwayat order asal. Setelah pembayaran benar-benar diverifikasi, buka **order pembayaran baru**, centang verifikasi pembayaran renewal/upgrade, pilih **Completed**, lalu **Update**. Completed tanpa centang verifikasi tidak menerapkan perubahan. Sistem menggunakan tanggal **New expiry yang disepakati**, bukan menghitung ulang saat persetujuan. Lifetime menghapus batas expiry, bukan tanggal mulai historis.
- Setelah persetujuan, perubahan dan alasan tercatat di **Service history** dan pengingat mengikuti masa aktif baru. Jika paket berubah, bukti/owner/item tidak valid, atau tanggal yang diminta sudah lewat sebelum persetujuan, penerapan ditolak untuk ditinjau admin; tidak digeser diam-diam. Riwayat penyesuaian manual lama tetap ditampilkan.
- Pengiriman form yang sama tidak membuat invoice ganda; satu paket hanya boleh memiliki satu permintaan admin yang belum diselesaikan. Setelah order Failed/Cancelled/Refunded yang belum diterapkan, reload order asal sebelum membuat permintaan pengganti. Kegagalan penyimpanan yang hasilnya tidak pasti mempertahankan reservasi/order untuk peninjauan, tidak menghapus order atau mencoba membuat ulang otomatis. Jika email gagal, order tetap tersedia: periksa order notes/log `din-packages` dan kirim ulang invoice melalui **Order actions**, bukan membuat order baru. Penerimaan inbox tetap harus diuji dengan mail capture/staging.
- Permintaan yang gagal dibuat, ditolak, atau tidak lagi cocok dengan state/tanggal paket diblokir melalui pemeriksaan pembayaran WooCommerce, termasuk link langsung order-pay. Order biasa tidak terpengaruh. Alamat dan status bebas pajak mengikuti snapshot order asal; total memakai kalkulasi pajak WooCommerce saat permintaan dibuat.
- Kolom admin, dashboard buyer, dan email membaca state tanggal paket yang sama. Sisa hari dibulatkan ke atas, diperbarui saat halaman dimuat. Tanggal publish yang sudah lama dapat menghasilkan paket langsung kedaluwarsa; pengingat memakai tanggal akhir tersebut, bukan memberi satu tahun tambahan sejak persetujuan.
- Periksa fitur tanpa order nyata dengan `php tests/core-smoke.php` dan `php tests/admin-smoke.php`: validasi tanggal, zona waktu/kabisat, jejak persetujuan, perlindungan paket lama, HPOS/legacy, berbagai status, order campuran, renewal/upgrade, escaping, dan otorisasi.

## Satu tampilan order buyer (1.0.3)

- **My Account → Orders** menampilkan order asal sekali. Order renewal/upgrade yang seluruh item produknya terkait satu order asal dikelompokkan ke riwayat order tersebut. Filter dijalankan sebelum pagination; jumlah halaman tetap mengikuti order yang terlihat.
- Kolom **Heading Post** setelah **Order** menampilkan judul yang tersimpan pada customer note order, termasuk order lama tanpa data paket. Judul panjang/multibaris dibungkus agar tetap terbaca di desktop dan mobile; nilai kosong ditampilkan sebagai `—`. Tampilan ini tidak mengubah judul, grouping, badge upgrade, atau data transaksi.
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
2. Admin atau Shop Manager membuka **Edit order**, mengisi **Guest Post Result → Publication Date**, mengunggah bukti DIN Order Attach, memilih **Completed**, lalu **Update**. Minimal satu file valid yang benar-benar tersimpan berlaku untuk seluruh item order. Aktivasi disetujui hanya sekali; tanggal mulai layanan mengikuti tanggal publish, bukan waktu Update.
3. Buyer membuka **My Account → My Package**. Dashboard menampilkan tiga paket berjalan terbaru jika tidak ada order yang masih dalam proses. Halaman paket menampilkan Heading Post, order/bukti, periode, tanggal mulai/akhir, sisa hari, dan status.
4. Perpanjangan 1/2 tahun tersedia mulai H-14 sampai kedaluwarsa. Upgrade Lifetime tersedia sejak Annual aktif. Pilihan masuk cart WooCommerce tanpa menghapus belanja lain; buyer melihat total sebelum checkout. Satu pilihan per paket, quantity satu; dua tahun berarti harga dua tahun, bukan dua unit layanan.
5. Pada order renewal/upgrade, admin memverifikasi pembayaran, mencentang **Pembayaran renewal/upgrade sudah saya verifikasi**, lalu menyimpan **Completed**. Bukti order asal dipakai kembali. WooCommerce mengisi `date_paid` saat Completed manual, sehingga tanggal itu tidak dipakai sendirian sebagai bukti pembayaran.

Pada checkout klasik (`[woocommerce_checkout]`), upgrade Annual → Lifetime menampilkan **Heading Post** paket asal sebagai informasi baca-saja di **Additional information**. Jika cart juga berisi pembelian baru, kolom Heading Post untuk publikasi baru tetap wajib diisi. Jalankan `php tests/checkout-heading-smoke.php` dari folder plugin untuk memeriksa tampilan upgrade, kepemilikan, escaping, dan cart campuran tanpa mengubah data.

Aktivasi sengaja melalui panel Edit order, bukan perubahan otomatis/API/bulk. Jika Completed disimpan tanpa bukti atau Publication Date yang valid, paket belum aktif. Penambahan bukti belakangan tetap memakai Publication Date sebagai awal layanan; waktu persetujuan masuk riwayat. Update berulang tidak me-reset tanggal paket yang sudah aktif.

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

Untuk pembelian renewal biasa dari dashboard buyer, Annual yang belum berakhir ditambah dari tanggal akhir lama; setelah berakhir, dihitung dari persetujuan admin. Permintaan **Adjust expiry** dari admin memakai tanggal yang disepakati pada order pembayaran. Penambahan tahun memakai kalender zona waktu situs, termasuk 29 Februari → 28 Februari pada tahun nonkabisat. Lifetime mempertahankan tanggal aktivasi pertama/riwayat dan tidak punya kedaluwarsa. Harga/order asal tetap utuh.

## Email dan pemantauan internal

- Email H-14, H-7, dan saat berakhir memakai template/transport WooCommerce; tidak membuat Customer Note tambahan. Penerima adalah email akun buyer, bukan otomatis email billing. Email batch DIN Order Attach tetap terpisah.
- H-14 memakai event lama `reminder`, H-7 memakai `reminder_7`, dan saat habis memakai `expired`. Tiap event memiliki catatan pengiriman terpisah per versi masa aktif. Catatan H-14 yang sudah terkirim tetap dihormati; tidak perlu migrasi data. Paket Annual yang sudah ada mendapat jadwal H-7 melalui rekonsiliasi per jam berikutnya ketika runner berjalan, tanpa perlu menyimpan ulang order.
- Jadwal dihitung dari `expires_at`: H-14 hanya boleh terkirim mulai 14 hari sebelum habis sampai sebelum H-7; H-7 mulai tujuh hari sebelum habis sampai sebelum expiry. Jika runner terlambat dan tinggal lima hari, hanya H-7 yang dikirim, bukan H-14 bersamaan. Setelah expiry, hanya email expired. Subjek memakai "within 14/7 days" dan isi menampilkan tanggal akhir sebenarnya agar tetap benar saat job terlambat.
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

Jalankan tiap smoke dengan PHP CLI (pengujian perubahan 1.0.4 memakai PHP 8.4.24). Semuanya memakai objek tiruan/database in-memory dan tidak mengirim email atau mengubah order buyer:

```powershell
$dinPhp = 'C:/Users/donis/.studio/php-bin/8.4.24-studio-2/php.exe'
Get-ChildItem 'wp-content/plugins/din-package-lifecycle/tests' -Filter '*-smoke.php' | ForEach-Object {
    & $dinPhp $_.FullName
    if ($LASTEXITCODE -ne 0) { throw 'Smoke failed' }
}
```

- `core-smoke.php`: aktivasi/bukti, mixed order/quantity, tanggal kabisat, renewal aktif/expired, CAS contention, duplikasi, benturan refund/cancel, legacy, kepemilikan.
- `customer-smoke.php`: kepemilikan, product binding, input malformed, quantity, harga berulang, isolasi cart biasa, snapshot, akun wajib, render/heading checkout.
- `dashboard-smoke.php`: pemilihan Order Progress/paket/empty state, invoice independen, hook tidak ganda, kepemilikan, dan paket aktif di halaman data berikutnya.
- `admin-smoke.php`: nonce, hak akses, fresh read, prioritas, checkbox pembayaran, konfigurasi produk, panel HPOS/legacy.
- `requests-smoke.php`: order pembayaran 1/2 tahun/Lifetime, harga/tax API, CAS/form ulang, kegagalan penyimpanan/email, validasi sumber, dan tidak ada perubahan hak layanan sebelum approval.
- `admin-expiry.cjs`: browser dengan HTML form PHP asli, urutan durasi → tanggal, default tahun, tanggal kustom, Lifetime tanpa tanggal, dan validasi hanya saat meminta order pembayaran. Gunakan Node dengan Playwright yang sudah tersedia: `node wp-content/plugins/din-package-lifecycle/tests/admin-expiry.cjs <path-php>` dari root proyek. Tidak membuka order nyata.
- `mail-smoke.php`: H-14/H-7/expired, batas waktu dan job terlambat/stale, kompatibilitas catatan lama, duplikasi, tiga percobaan, lease, source ownership/cache, escaping, pembatalan dan rekonsiliasi.

Integrasi lokal baca-saja:

```powershell
studio --version
studio status
studio wp eval-file wp-content/plugins/din-package-lifecycle/tests/site-verify.php
```

Hasil 7 September 2026: empat smoke dan lint lulus; plugin aktif; schema/index unik, hook classic/Block, prioritas admin 80, kemampuan Administrator/Shop Manager, pasangan katalog, dan jadwal rekonsiliasi lulus. HPOS aktif, nol paket historis dimigrasikan. Dashboard/menu dan empty-state Paket Saya telah diperiksa di browser desktop 1280px/mobile 390px; halaman paket mobile tidak tertutup sidebar.

Panel paket pada editor order HPOS juga sudah tampil dan menunjukkan peringatan order historis tanpa melakukan Update order. Belum diverifikasi: checkout pembayaran end-to-end melalui gateway, batch upload + Completed pada order baru sungguhan, penerimaan email di inbox, serta render admin legacy pada database non-HPOS. Uji staging dengan akun/order uji dan mail capture sebelum produksi. Notice deprecation Elementor yang teramati berasal dari plugin yang sudah ada, bukan plugin ini.

`tests/site-inspect.php` hanya membaca katalog/settings/snippet relevan. `tests/configure-local-catalog.php` adalah setup khusus situs lokal terverifikasi, bukan proses instalasi umum; jangan jalankan di situs lain. Rilis ZIP cukup berisi file utama, `includes/`, `assets/`, dan README, tanpa helper pengujian lokal.
