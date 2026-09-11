# Draft PRD — Masa Aktif Paket dan Perpanjangan

Tanggal: 7 September 2026  
Status: Dieksekusi atas instruksi "eksekusi ini"; implementasi awal DIN Package Lifecycle 1.0.0 tersedia. Draft di bawah dipertahankan sebagai asal kebutuhan; keputusan operasional implementasi dicatat berikut ini.

Keputusan implementasi 7 September 2026: harga katalog 1x Annual / 2x Annual / harga penuh Lifetime; bukti berlaku per order; renewal menunggu verifikasi pembayaran eksplisit dan Completed oleh admin. Tidak ada migrasi historis atau rollback refund otomatis. Lihat `wp-content/plugins/din-package-lifecycle/README.md` untuk penggunaan, hasil verifikasi, dan batas pengujian. Pengiriman inbox dan pembayaran gateway end-to-end belum diuji.

## 1. Kebutuhan yang disampaikan

- Buyer memantau masa aktif produk Annual dari dashboard setelah pembelian.
- Awal masa aktif ditentukan oleh tindakan admin: order Completed dan bukti attachment sudah diunggah.
- Buyer menerima email 14 hari sebelum berakhir dan saat paket berakhir.
- Paket yang hampir berakhir menawarkan perpanjangan 1 tahun, 2 tahun, atau upgrade Lifetime.
- Paket Annual bisa diubah menjadi Lifetime.
- Administrator dan Shop Manager menjadi petugas internal, mengikuti hak akses pengelolaan order yang ada.

## 2. Temuan pada kode saat ini

- DIN Order Attach versi 1.6.0 menyimpan file pada order melalui `_din_order_attach_files`. File belum dipetakan ke item produk tertentu.
- Setiap file mempunyai ID, lokasi, MIME, ukuran, pengunggah, dan waktu upload. Validitas serta keberadaan file dapat diperiksa melalui API penyimpanan yang sudah ada.
- Pada penyimpanan admin HPOS, data/status order diproses di prioritas 40. Upload DIN Order Attach diproses di prioritas 50. Pemeriksaan aktivasi harus dilakukan setelah kedua tahap selesai, dengan membaca ulang order dan attachment.
- Pricing card membaca atribut `billing` atau `pa_billing` untuk teks periode. Ini belum menjadi pencatatan masa aktif paket.
- Dashboard mempunyai hook `woocommerce_account_dashboard` untuk menambahkan bagian baru.
- Action Scheduler tersedia dalam source WooCommerce untuk pekerjaan terjadwal. Ketersediaan runner dan pengiriman email tetap perlu diuji saat implementasi.

## 3. Pendekatan

Usulan: plugin kustom khusus masa aktif, terintegrasi dengan WooCommerce dan DIN Order Attach. Plugin baru dimulai dari versi 1.0.0; versi DIN Order Attach hanya naik bila source plugin tersebut memang perlu berubah.

Alternatifnya, modul ini dapat dimasukkan ke DIN Order Attach, tetapi cakupannya menjadi pengelolaan paket sekaligus file. Plugin terpisah lebih sesuai karena paket mempunyai pembayaran, jadwal, dan riwayat sendiri. Keduanya menggunakan hook/API; tidak mengedit core WooCommerce.

MVP memakai pembelian perpanjangan melalui checkout WooCommerce. Penagihan berulang otomatis tidak termasuk usulan ini.

## 4. Unit paket dan data

- Masa aktif dicatat per item order, bukan hanya per order atau per akun. Order campuran Annual/Lifetime tidak boleh berbagi satu tanggal berakhir.
- Bila satu item mempunyai quantity lebih dari satu, setiap unit layanan memiliki identitas sendiri. Quantity dua bukan berarti perpanjangan dua tahun.
- Produk diberi konfigurasi periode yang eksplisit: Annual atau Lifetime, serta pasangan produk/opsi untuk renewal dan upgrade. Nama produk saja tidak menjadi dasar perhitungan.
- Konfigurasi periode disalin saat pembelian agar perubahan katalog tidak mengubah hak paket lama.
- Data minimum: ID paket, pemilik, order/item/unit asal, produk, Heading Post order asal, jenis periode, tanggal mulai, tanggal akhir, bukti aktivasi, admin aktivator, dan riwayat order perpanjangan/upgrade.
- Paket Lifetime tidak mempunyai tanggal akhir. Riwayat Annual tetap tersimpan.
- Status layanan: Menunggu aktivasi, Aktif, Segera berakhir, Berakhir, Lifetime; kasus refund/pembatalan dapat ditandai Perlu peninjauan.
- Status layanan terpisah dari status WooCommerce. Paket berakhir tidak mengubah order historis Completed menjadi Cancelled.

## 5. Aktivasi pertama

Usulan aturan operasional:

1. Admin mengunggah bukti, memilih Completed, lalu menyimpan order.
2. Setelah penyimpanan selesai, sistem memeriksa hak admin/Shop Manager, status Completed, akun buyer, periode produk, dan minimal satu bukti yang valid serta benar-benar tersimpan.
3. Jika keduanya terpenuhi, tanggal mulai dicatat sekali pada saat aktivasi. Annual berakhir satu tahun kalender kemudian; Lifetime tidak memiliki tanggal akhir.
4. Jika Completed tersimpan tanpa bukti valid, paket tetap Menunggu aktivasi dan admin melihat alasan. Saat admin menambahkan bukti pada order yang masih Completed, tanggal mulai adalah waktu penyimpanan bukti tersebut, bukan tanggal Completed lama.
5. Update ulang, upload bukti tambahan, maupun perubahan status bolak-balik tidak mengulang tanggal mulai. Status Completed dari proses otomatis saja tidak menggantikan syarat tindakan admin.
6. Bukti yang dihapus setelah aktivasi tidak me-reset atau memperpanjang masa aktif. Riwayat ID bukti dipertahankan untuk audit dan admin mendapat tanda perlu peninjauan bila semua bukti hilang.

Usulan MVP untuk bukti: bukti tingkat order berlaku bagi seluruh item yang diselesaikan dalam order tersebut. Jika bukti hanya mewakili produk tertentu, perlu pemetaan attachment per item sebelum aktivasi dapat dilakukan terpisah. Keputusan ini masih perlu dikonfirmasi.

## 6. Dashboard buyer

Tambahkan bagian **Paket Saya** dengan style dashboard yang ada. Satu baris/kartu per paket menampilkan:

- Nama produk, Heading Post, dan nomor order asal.
- Jenis paket, tanggal mulai, tanggal berakhir, serta sisa hari.
- Badge status dengan teks yang dapat dibaca tanpa mengandalkan warna.
- Link detail order dan bukti melalui pemeriksaan akses DIN Order Attach.

Usulan: mulai H-14 sampai Berakhir, tampilkan pilihan **Perpanjang 1 Tahun**, **Perpanjang 2 Tahun**, dan **Upgrade Lifetime**. Untuk Annual yang masih aktif, Upgrade Lifetime juga dapat ditawarkan lebih awal. Paket Lifetime tidak menampilkan pilihan perpanjangan.

Buyer hanya boleh melihat atau membeli perubahan untuk paket miliknya. Harga dan pilihan periode divalidasi ulang di server; ID paket dari URL/cart tidak dianggap sebagai bukti kepemilikan.

## 7. Perpanjangan dan upgrade

1. Buyer memilih paket serta tindakan di dashboard.
2. Sistem menambahkan pembelian renewal/upgrade ke cart, terikat pada ID paket asal. Checkout memperlihatkan paket tujuan, durasi, harga, dan perubahan yang akan diperoleh. Heading Post/referensi publikasi asal tetap terkait; buyer tidak diminta memesan publikasi baru.
3. Terbit order WooCommerce baru. Order asal, harga historis, bukti, dan tanggal mulai pertama dipertahankan.
4. Usulan: perubahan diterapkan setelah pembayaran terkonfirmasi dan order baru diselesaikan admin. Bukti publikasi dari paket asal bisa dipakai untuk renewal; bukti baru tidak diwajibkan secara default.
5. Jika paket masih aktif, akhir baru = akhir lama + 1 atau 2 tahun kalender.
6. Jika paket sudah berakhir, akhir baru = tanggal persetujuan renewal + 1 atau 2 tahun kalender.
7. Upgrade mengubah hak paket yang sama menjadi Lifetime setelah persetujuan tersebut. Jadwal email kedaluwarsa lama dinonaktifkan.
8. Setiap item pembelian renewal/upgrade hanya boleh diterapkan satu kali, termasuk ketika callback pembayaran atau penyimpanan admin diulang. Pembaruan bersamaan dikunci per paket agar durasi tidak hilang atau terhitung ganda.
9. Pilihan yang sudah tidak valid, misalnya upgrade kedua pada paket Lifetime, ditolak saat checkout dan diperiksa lagi sebelum diterapkan. Order yang terlanjur dibayar ditandai untuk peninjauan admin.
10. Refund/cancel sebelum perubahan diterapkan tidak menambah masa aktif. Refund setelah perubahan diterapkan ditandai untuk peninjauan admin; rollback otomatis dan prorata belum ditentukan.

Contoh usulan: aktif 7 September 2026 sampai 7 September 2027. Renewal satu tahun disetujui 1 September 2027 menghasilkan akhir 7 September 2028. Jika baru disetujui 20 September 2027, akhir menjadi 20 September 2028.

## 8. Email dan penjadwalan

| Peristiwa | Waktu | Isi minimum |
|---|---|---|
| Akan berakhir | 14 hari sebelum tanggal akhir | Produk, Heading Post/order asal, tanggal akhir, tautan login untuk renewal/upgrade |
| Sudah berakhir | Saat tanggal akhir tercapai | Produk, tanggal berakhir, status layanan, tautan login untuk melanjutkan paket |

- Setiap peristiwa dijadwalkan sekali per paket dan versi tanggal akhir. Pencegahan duplikasi juga dilakukan pada handler, bukan hanya saat menjadwalkan.
- Tepat sebelum mengirim, baca ulang masa aktif: job lama tidak boleh mengirim email setelah renewal, upgrade Lifetime, atau pembatalan layanan.
- Kegagalan pengiriman dicatat dan dicoba ulang secara terbatas. Scheduler yang terlambat tidak mengirim reminder H-14 bila paket sudah berakhir; hanya peristiwa Berakhir yang masih relevan.
- Gunakan format email WooCommerce dan jalur pengiriman yang terpasang. Jangan menggandakan email dengan sekaligus memicu Customer Note untuk peristiwa yang sama. Email batch attachment yang ada tetap terpisah.
- Waktu disimpan dalam UTC dan ditampilkan memakai zona waktu situs. Penambahan tahun mengikuti kalender; 29 Februari menjadi 28 Februari pada tahun nonkabisat.
- Dashboard menentukan status dari tanggal aktual agar tidak bergantung pada kecepatan runner email.
- Operasional produksi memerlukan runner terjadwal yang berjalan tanpa bergantung pada kunjungan buyer. WordPress Studio harus tetap berjalan untuk pengujian lokal; pengiriman tepat waktu dan penerimaan inbox belum diverifikasi.

## 9. Order lama dan batas MVP

- Order lama tidak otomatis dianggap baru aktif pada hari plugin dipasang. Migrasi perlu daftar pratinjau tanggal/bukti yang ditinjau admin sebelum diterapkan.
- Kedaluwarsa hanya mencatat masa aktif dan mengirim pemberitahuan. Tidak menghapus attachment, order, maupun artikel di situs penerbit.
- Lifetime berarti tidak ada kedaluwarsa pada pencatatan paket ini; definisi komersial layanan mengikuti ketentuan toko.
- Pengujian email awal memakai mail capture/test inbox. Tidak mengirim email ke buyer nyata sebagai bagian dari pengujian tanpa otorisasi.

## 10. Pertanyaan pada draft awal (lihat keputusan implementasi di atas)

1. Harga 1 tahun, 2 tahun, dan upgrade Lifetime: mengikuti katalog, harga khusus admin, atau kredit nilai/sisa periode Annual. Pertanyaan sudah disampaikan; belum ada pilihan yang diterima saat draft ini ditulis.
2. Apakah bukti tingkat order cukup untuk seluruh produk, atau admin perlu memilih bukti per item/unit.
3. Persetujuan usulan bahwa renewal/upgrade menunggu pembayaran terkonfirmasi dan Completed oleh admin serta dapat memakai bukti paket asal.
4. Kebijakan refund setelah renewal/upgrade, dan tanggal migrasi paket lama.

## 11. Kriteria penerimaan

- Completed dengan bukti valid mengaktifkan sekali; tanpa bukti atau upload gagal tidak mengaktifkan.
- Bukti + Completed dalam satu update berhasil; bukti setelah Completed mempunyai tanggal mulai yang benar.
- Satu order campuran dan quantity lebih dari satu tetap memisahkan paket serta durasi.
- Pengulangan event tidak menggandakan paket, masa aktif, atau notifikasi.
- Buyer melihat status/tanggal yang benar dan tidak dapat membuka atau memperpanjang paket akun lain.
- Renewal sebelum dan setelah berakhir mengikuti aturan tanggal yang disepakati, termasuk tahun kabisat.
- Order renewal yang belum dibayar tidak mengubah paket; pembayaran dan Completed yang sah menerapkan tepat satu perubahan.
- Upgrade Lifetime mempertahankan riwayat serta menghentikan reminder yang sudah dijadwalkan.
- H-14 dan hari kedaluwarsa diuji dengan jam terkontrol, termasuk job terlambat, gagal kirim, dan benturan dengan renewal.
- HPOS dan penyimpanan order lama memakai API WooCommerce; dashboard desktop/mobile dan akses file tetap berfungsi.

## 12. Lokasi integrasi yang telah diperiksa

- `wp-content/plugins/din-order-attach/includes/class-din-order-attach.php`
- `wp-content/plugins/din-order-attach/includes/class-din-order-attach-storage.php`
- `wp-content/themes/hello-elementor-child/functions.php`
- `wp-content/themes/hello-elementor-child/woocommerce/myaccount/dashboard.php`
- `wp-content/plugins/woocommerce/src/Internal/Admin/Orders/Edit.php`
- `wp-content/plugins/woocommerce/packages/action-scheduler/functions.php`

Daftar ini adalah titik integrasi, bukan izin atau rencana mengedit file core WooCommerce.
