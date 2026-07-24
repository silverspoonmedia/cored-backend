# Tugas: Validasi Project Lama dan Implementasi Platform Content Creator Aggregator Baru

Anda bertindak sebagai **Senior Fullstack Engineer, Software Architect, Code Auditor, dan Technical Product Engineer**.

Tugas Anda adalah:

1. Membaca seluruh hasil in-depth analysis terhadap project lama yang dibuat oleh beberapa AI model.
2. Memvalidasi setiap claim dari dokumen-dokumen tersebut secara langsung terhadap source code project lama.
3. Menggunakan hasil validasi dan project lama sebagai referensi untuk merancang serta mengimplementasikan project baru.
4. Mengimplementasikan project baru secara fullstack, mencakup frontend, backend, database, background jobs, integrasi platform eksternal, testing, dokumentasi, dan kebutuhan operasional terkait.

Project lama hanya berfungsi sebagai **referensi**. Jangan menganggap seluruh keputusan arsitektur, pola implementasi, atau kode dari project lama harus dipertahankan.

---



# Input yang Tersedia

Gunakan input berikut:

- Repository project lama: `c:\Users\Administrator\Documents\Git\broadercastv2\`
- Repository atau working directory project baru: `c:\laragon\www\monolith\`
- Hasil analisis dari beberapa AI model:
  - `c:\Users\Administrator\Documents\Git\broadercastv2\Result_Sol.md`
  - `c:\Users\Administrator\Documents\Git\broadercastv2\Result_Grok.md`
  - `c:\Users\Administrator\Documents\Git\broadercastv2\Result_GLM.md`

Baca seluruh file yang relevan sebelum mengambil keputusan implementasi besar.

Jangan hanya membaca README atau dokumentasi tingkat tinggi. Periksa source code, konfigurasi, database schema, migration, API route, service, background worker, frontend component, test, deployment configuration, serta file lain yang berpengaruh terhadap perilaku sistem.

---



# Aturan Utama



## 1. Jangan Menganggap Claim Analisis sebagai Fakta

Seluruh claim dalam hasil analisis AI harus dianggap sebagai hipotesis sampai dibuktikan melalui pemeriksaan source code.

Untuk setiap claim penting:

- Identifikasi dokumen dan model yang membuat claim.
- Cari bukti langsung di codebase.
- Catat file, module, class, function, schema, route, atau konfigurasi yang menjadi bukti.
- Tentukan status claim:
  - `VALID`
  - `PARTIALLY_VALID`
  - `INVALID`
  - `OUTDATED`
  - `NOT_VERIFIABLE`
- Jelaskan alasan penetapan status.
- Catat perbedaan pendapat antarhasil analisis.
- Jangan memilih salah satu analisis hanya karena terdengar lebih meyakinkan.

Buat sebuah **Claim Validation Ledger** sebagai artefak kerja.

Format minimal:


| ID  | Sumber Analisis | Claim | Status | Bukti Codebase | Penjelasan | Dampak ke Project Baru |
| --- | --------------- | ----- | ------ | -------------- | ---------- | ---------------------- |


Claim yang tidak dapat diverifikasi harus dinyatakan secara eksplisit. Jangan membuat bukti atau kesimpulan yang tidak ada.

## 2. Wajib Bertanya untuk Keputusan yang Membutuhkan Persetujuan

Anda **HARUS BERTANYA kepada user** sebelum mengambil keputusan yang:

- Mengubah requirement bisnis.
- Mengubah scope fitur.
- Memilih teknologi utama ketika belum ditentukan dan pilihan tersebut berdampak besar.
- Mengubah model relasi domain.
- Mengandung trade-off signifikan terkait biaya, performa, keamanan, maintainability, atau vendor lock-in.
- Membutuhkan credential, API key, account ID, webhook configuration, atau akses eksternal.
- Membutuhkan interpretasi requirement yang memiliki lebih dari satu kemungkinan masuk akal.
- Berpotensi menghapus atau mengganti bagian penting dari project lama.
- Memerlukan keputusan terkait autentikasi, otorisasi, role, permission, tenancy, atau kepemilikan data.
- Memerlukan perubahan database yang sulit dibatalkan.
- Memerlukan keputusan terkait deployment atau infrastruktur produksi.

Jangan mengajukan pertanyaan yang jawabannya dapat ditemukan secara pasti melalui pemeriksaan repository, konfigurasi, atau dokumentasi yang tersedia.

Saat bertanya:

1. Jelaskan konteks keputusan.
2. Jelaskan pilihan yang tersedia.
3. Jelaskan trade-off setiap pilihan secara ringkas.
4. Berikan rekomendasi Anda.
5. Tunggu jawaban sebelum mengeksekusi keputusan tersebut.

Gunakan format:

```text
DECISION REQUIRED: [judul keputusan]

Konteks:
[...]

Pilihan:
A. [...]
   Kelebihan:
   Kekurangan:

B. [...]
   Kelebihan:
   Kekurangan:

Rekomendasi:
[...]

Pertanyaan:
Pilihan mana yang harus digunakan?
```

Jika keputusan tersebut memblokir sebagian pekerjaan tetapi tidak memblokir bagian lain, lanjutkan pekerjaan yang tidak bergantung pada keputusan itu.

## 3. Jangan Melakukan Implementasi Besar Sebelum Audit Awal Selesai

Sebelum mulai mengimplementasikan fitur secara penuh, selesaikan:

- Inventarisasi struktur project lama.
- Inventarisasi hasil analisis AI.
- Claim validation.
- Gap analysis antara project lama dan requirement baru.
- Usulan domain model.
- Usulan arsitektur.
- Daftar keputusan yang membutuhkan persetujuan user.
- Rencana implementasi bertahap.

Jangan langsung menyalin source code project lama ke project baru.

## 4. Gunakan Bukti, Bukan Asumsi

Setiap kesimpulan mengenai project lama harus memiliki referensi konkret, misalnya:

```text
src/modules/channel/channel.service.ts:42-89
database/migrations/2024xxxx_create_channels.sql
apps/web/src/pages/creator/[slug].tsx
```

Jika line number berubah selama pengerjaan, setidaknya sebutkan file dan symbol terkait.

## 5. Jaga Project Tetap Bisa Dijalankan

Selama implementasi:

- Kerjakan dalam perubahan kecil dan terverifikasi.
- Jangan meninggalkan build dalam keadaan rusak.
- Jalankan formatter, linter, type checker, test, dan build yang relevan.
- Catat command yang dijalankan serta hasilnya.
- Jangan menyatakan fitur selesai jika belum diuji.
- Jangan menyembunyikan test yang gagal.
- Jangan menghapus test hanya agar pipeline lolos.
- Jangan menonaktifkan validasi keamanan tanpa persetujuan.

---



# Konteks Produk Baru

Platform baru adalah platform agregasi konten untuk content creator.

## Struktur Content Creator



### Satu User

Seorang content creator menggunakan satu akun utama untuk login dan mengelola seluruh identitas serta channel miliknya.

### Multiple Channel

Satu content creator dapat memiliki banyak channel pada platform yang sama maupun berbeda.

Contoh:

- Tiga channel YouTube.
- Dua channel Twitch.
- Satu channel Kick.

Relasi konseptual:

```text
User/Creator 1 --- N Channel
```

Pastikan istilah `User`, `Content Creator`, dan `Account` tidak dicampur tanpa definisi yang jelas.

Tentukan apakah:

- Seluruh user otomatis merupakan content creator.
- Content creator merupakan profile terpisah dari authentication user.
- Satu authentication user dapat mengelola lebih dari satu creator profile.
- Channel dimiliki langsung oleh user atau oleh creator profile.

Jika project lama dan requirement belum memberikan jawaban pasti, tanyakan kepada user.

### One Management

Seorang content creator dapat berada di bawah satu talent management.

Satu talent management dapat menaungi banyak content creator.

Relasi konseptual:

```text
Management 1 --- N Content Creator
```

Talent management dapat memperoleh hak akses terhadap sebagian atau seluruh fitur content creator.

Sistem permission harus mendukung kemungkinan:

- Creator mengelola dirinya sendiri.
- Staff management melihat data creator tertentu.
- Staff management mengelola sebagian fitur.
- Staff management mengelola seluruh fitur.
- Hak akses dapat berbeda untuk setiap creator.
- Satu management mempunyai beberapa staff.
- Audit trail atas tindakan yang dilakukan oleh pihak management.

Jangan menggunakan satu field seperti `is_admin` untuk seluruh kebutuhan ini tanpa analisis yang memadai.

Evaluasi penggunaan:

- Role-based access control.
- Permission-based access control.
- Resource-scoped permissions.
- Membership table antara user dan management.
- Creator-management assignment.
- Delegated access.
- Audit log.

Pilihan akhir harus dijelaskan dan, jika memiliki dampak besar, dimintakan persetujuan.

---



# Platform yang Diagregasi



## YouTube

Konten yang diambil:

- Ongoing live streaming.
- Upcoming live streaming.
- Live streaming archive.
- Video on demand yang di-upload langsung.

Gunakan mekanisme WebSub/PubSubHubbub sebagai trigger untuk mendeteksi perubahan atau konten baru ketika memungkinkan.

Trigger bukan satu-satunya sumber kebenaran. Sistem harus memiliki mekanisme rekonsiliasi terhadap official API untuk menghadapi:

- Event yang terlambat.
- Event yang hilang.
- Duplicate notification.
- Notification yang datang lebih dari sekali.
- Perubahan metadata setelah event diterima.
- Subscription expiration.
- Renewal subscription.
- Webhook verification.
- Invalid signature atau request mencurigakan.
- Channel yang dicabut aksesnya.
- API quota exhaustion.

Untuk live streaming:

- Deteksi status upcoming, live, dan ended.
- Ketika live, lakukan polling setiap interval tertentu untuk memperbarui concurrent viewers dan metadata yang relevan.
- Ketika live berakhir, hentikan polling aktif.
- Ambil metadata final dan analytics yang tersedia.
- Ubah atau petakan status konten menjadi live streaming archive jika replay tersedia.
- Pastikan satu konten tidak tercatat sebagai beberapa entitas berbeda secara tidak sengaja.

Untuk VOD:

- Catat metadata ketika ditemukan.
- Lakukan update jika metadata berubah.
- Hindari duplikasi.
- Simpan identifier eksternal yang stabil.

Polling interval, retention data CCV, dan detail analytics yang disimpan merupakan keputusan produk dan teknis. Tanyakan kepada user apabila belum ditentukan.

## Twitch

Konten yang diambil:

- Live streaming.

Gunakan official API dan mekanisme event resmi jika tersedia serta sesuai dengan akses project. Jika arsitektur yang diminta tetap menggunakan polling, buat polling yang:

- Hemat request.
- Mendukung batching.
- Memiliki rate-limit handling.
- Menggunakan retry dengan exponential backoff dan jitter.
- Tidak membuat duplicate active job.
- Menghentikan polling detail setelah stream selesai.
- Memiliki periodic reconciliation.

Jangan mengasumsikan API atau webhook tertentu tersedia tanpa memeriksa dokumentasi integrasi yang digunakan oleh repository atau informasi yang diberikan user.

## Kick

Konten yang diambil:

- Live streaming.

Sebelum implementasi, validasi:

- API resmi yang tersedia.
- Mekanisme autentikasi.
- Rate limit.
- Ketentuan penggunaan.
- Stabilitas endpoint.
- Dukungan webhook atau event notification.
- Data live stream yang dapat diperoleh secara sah.

Jika tidak tersedia API resmi yang memadai, jangan menggunakan scraping atau endpoint tidak resmi tanpa persetujuan eksplisit user.

Ajukan keputusan beserta risiko operasional dan legalnya.

---



# Konsep Domain yang Harus Dianalisis

Jangan langsung menggunakan nama tabel berikut tanpa analisis, tetapi pastikan domain berikut terwakili:

- Authentication user.
- Creator profile.
- Talent management.
- Management staff atau membership.
- Creator-management relationship.
- Delegated permission.
- External platform.
- External channel.
- Content.
- Live stream.
- Live stream session.
- Live stream status history.
- Video.
- Content-platform association.
- Polling job.
- Webhook subscription.
- Webhook event.
- Metric snapshot.
- Concurrent viewer snapshot.
- Creator statistic snapshot.
- Channel statistic snapshot.
- External link.
- Event atau collaboration event untuk pengembangan berikutnya.
- Audit log.
- Sync state.
- Sync error atau integration incident.

Evaluasi apakah `Live Streaming Archive` dan `VOD` sebaiknya:

- Menjadi jenis entity yang berbeda.
- Menjadi satu entity `Content` dengan tipe berbeda.
- Menjadi satu video entity dengan origin dan lifecycle berbeda.

Jelaskan trade-off dan pilih model yang meminimalkan duplikasi data.

---



# Presentation dan Halaman Utama



## Home Page

Urutan awal section:

1. Live Streaming.
2. Live Streaming Archive dan VOD.

Section Event belum perlu diimplementasikan pada fase awal, tetapi arsitektur tidak boleh membuat penambahannya menjadi sangat sulit.

### Live Streaming Section

Tampilkan:

- Ongoing live streaming.
- Upcoming live streaming.

Keduanya berada dalam satu section.

Urutan data didasarkan pada aktivitas terakhir content creator. Definisikan secara eksplisit arti `aktivitas terakhir`, misalnya:

- Waktu stream mulai.
- Waktu metadata terakhir berubah.
- Waktu channel terakhir live.
- Waktu konten terakhir ditemukan.

Jika requirement belum cukup jelas, tanyakan kepada user sebelum mengunci algoritma sorting.

Format presentasi dasar:

```text
Card Image
Card Title / Creator Information
Platform Link 1
Platform Link 2
Platform Link N
```

Untuk multi-streaming:

- Satu creator yang sedang live pada beberapa platform dapat ditampilkan sebagai satu grouping.
- Penonton dapat memilih platform tujuan.
- Jangan menggabungkan stream hanya berdasarkan creator dan waktu yang berdekatan tanpa strategi korelasi yang jelas.
- Tentukan cara mendeteksi bahwa beberapa stream merupakan sesi multi-stream yang sama.
- Sediakan fallback apabila korelasi tidak dapat dipastikan.

Multi-stream correlation dapat mempertimbangkan:

- Creator yang sama.
- Overlap waktu.
- Judul yang sama atau mirip.
- Metadata manual.
- Explicit grouping identifier.

Jika keputusan produk diperlukan, tanyakan kepada user.

### Live Streaming Archive dan VOD Section

Tampilkan konten setelah section live streaming.

Konten mencakup:

- Live streaming archive.
- VOD yang di-upload langsung.

Pastikan UI dapat menjelaskan perbedaan keduanya tanpa membingungkan pengguna.

Tentukan kebutuhan:

- Pagination atau infinite scroll.
- Filtering berdasarkan platform.
- Filtering berdasarkan creator.
- Sorting.
- Deduplication.
- Thumbnail fallback.
- Konten yang private, deleted, unavailable, atau region-restricted.

Tanyakan jika requirement tersebut memengaruhi pengalaman utama dan belum ditentukan.

## Content Creator Profile

Sediakan halaman profile terintegrasi untuk seorang content creator.

Minimal berisi:

- Informasi creator.
- Seluruh channel.
- Platform setiap channel.
- Status live saat ini.
- Upcoming live streaming.
- Live streaming archive.
- VOD.
- Statistik creator.
- Statistik per channel.
- External link.
- Social media.
- Link donasi jika tersedia.
- Informasi talent management.

Pertimbangkan:

- URL slug.
- SEO metadata.
- Open Graph metadata.
- Structured data.
- Pagination.
- Filter konten.
- Empty state.
- Error state.
- Loading state.
- Deleted atau disconnected channel.
- Metric freshness indicator.



## Management Profile

Sediakan halaman profile talent management.

Minimal berisi:

- Nama.
- Deskripsi.
- Branding atau logo.
- External links.
- Daftar content creator yang berada dalam naungan.
- Status live para creator.
- Statistik agregat jika disetujui dalam scope.

Pastikan creator yang sudah keluar dari management dapat ditangani melalui data historis tanpa merusak relasi lama.

---



# Kebutuhan Backend

Backend harus mencakup, sesuai kebutuhan arsitektur:

- Authentication.
- Authorization.
- User dan creator management.
- Talent management.
- Channel connection.
- External integration.
- Webhook receiver.
- Subscription renewal.
- Polling scheduler.
- Background worker.
- Retry dan dead-letter handling.
- Idempotent processing.
- Data normalization.
- Content lifecycle handling.
- Metric collection.
- API untuk frontend.
- Input validation.
- Error handling.
- Structured logging.
- Audit logging.
- Health check.
- Readiness check.
- Observability dasar.
- Rate limiting untuk endpoint sensitif.
- Secure secret handling.

Jangan menjalankan pekerjaan polling berat di request-response lifecycle.

Gunakan background job atau worker yang sesuai dengan stack.

Pastikan job bersifat:

- Idempotent.
- Retry-safe.
- Observable.
- Dapat dihentikan.
- Tidak menghasilkan polling ganda.
- Memiliki timeout.
- Memiliki failure classification.
- Memiliki mekanisme recovery.

---



# Kebutuhan Frontend

Frontend harus:

- Responsive.
- Accessible.
- Memiliki loading, empty, success, dan error state.
- Tidak mengandalkan data mock setelah integrasi selesai.
- Menggunakan reusable component.
- Memisahkan presentational dan data-fetching concerns secara wajar.
- Menangani stale data.
- Menampilkan timestamp freshness jika relevan.
- Memiliki route dan metadata yang konsisten.
- Menghindari layout shift berlebihan.
- Mengoptimalkan image loading.
- Menangani external link secara aman.
- Mendukung platform badge atau indicator.
- Menyajikan multi-stream dengan jelas.

Jangan membuat seluruh halaman sebagai satu component besar.

---



# Database dan Data Integrity

Buat schema yang mendukung:

- Identifier internal.
- External platform identifier.
- Unique constraint yang tepat.
- Idempotent upsert.
- Referential integrity.
- Soft delete jika memang diperlukan.
- Historical relationship.
- Timestamp sumber data.
- Timestamp sinkronisasi.
- Status data.
- Version atau optimistic locking jika diperlukan.

Bedakan setidaknya:

- Waktu event terjadi di platform.
- Waktu event diterima sistem.
- Waktu data diproses.
- Waktu data terakhir disinkronkan.

Hindari hanya menggunakan satu `updated_at` untuk seluruh makna tersebut.

Database migration harus:

- Reversible jika memungkinkan.
- Memiliki index yang relevan.
- Tidak menghapus data penting tanpa persetujuan.
- Diuji pada database kosong.
- Diuji terhadap data existing jika migrasi dari sistem lama termasuk scope.

Jika migrasi data dari project lama belum dipastikan sebagai kebutuhan, tanyakan kepada user.

---



# Keamanan

Periksa dan implementasikan perlindungan terhadap:

- Broken access control.
- Privilege escalation.
- IDOR.
- Invalid webhook request.
- Replay webhook.
- Duplicate webhook.
- Injection.
- Unsafe external URL.
- Leaked API credential.
- Sensitive information dalam log.
- Mass assignment.
- Missing ownership validation.
- Insecure token storage.
- Missing CSRF protection jika relevan.
- Missing CORS restriction jika relevan.
- SSRF dari URL eksternal.
- Abuse terhadap endpoint sync.
- Unbounded polling.
- Unauthorized management access.

Permission management harus diuji pada backend. Menyembunyikan tombol di frontend bukan mekanisme authorization.

---



# Testing

Implementasikan test yang relevan pada beberapa level.

## Unit Test

Minimal untuk:

- Data normalization.
- Status mapping.
- Permission evaluation.
- Multi-platform content mapping.
- Retry decision.
- Polling interval calculation.
- Deduplication.
- Multi-stream grouping jika diterapkan.



## Integration Test

Minimal untuk:

- Database repository.
- API route.
- Authentication.
- Authorization.
- Webhook processing.
- Job enqueueing.
- Idempotent event processing.
- External API adapter menggunakan mock atau fixture.
- Content lifecycle transition.



## End-to-End Test

Minimal mencakup alur utama:

1. User login.
2. Creator profile tersedia.
3. Channel terhubung atau tercatat.
4. Live stream ditemukan.
5. Live stream muncul di homepage.
6. Stream multi-platform ditampilkan sesuai desain.
7. Stream berakhir.
8. Konten berpindah atau dipetakan menjadi archive.
9. VOD muncul pada creator profile.
10. Staff management hanya dapat mengakses resource yang diizinkan.

Jangan melakukan request ke production external API dalam automated test.

Gunakan mock, fixture, contract test, atau sandbox resmi.

---



# Tahapan Pengerjaan



## Fase 0 — Discovery

Lakukan:

- Baca seluruh hasil analisis AI.
- Petakan struktur repository lama.
- Identifikasi stack.
- Identifikasi cara menjalankan project.
- Identifikasi schema database.
- Identifikasi integrasi eksternal.
- Identifikasi fitur yang benar-benar sudah ada.
- Identifikasi technical debt.
- Identifikasi security concern.
- Identifikasi kode yang masih dapat digunakan sebagai referensi.

Output:

- Project inventory.
- Analysis inventory.
- Initial risk list.
- Daftar pertanyaan awal yang benar-benar membutuhkan user.



## Fase 1 — Claim Validation

Buat Claim Validation Ledger.

Validasi seluruh claim penting terhadap codebase.

Prioritaskan claim terkait:

- Arsitektur.
- Database.
- Authentication.
- Authorization.
- Integration.
- Polling.
- Webhook.
- Content lifecycle.
- Frontend behavior.
- Security.
- Performance.
- Testing.
- Deployment.

Output:

- Claim Validation Ledger.
- Contradiction report.
- Daftar claim yang tidak dapat diverifikasi.
- Daftar temuan baru yang tidak disebutkan oleh hasil analisis AI.



## Fase 2 — Gap Analysis

Bandingkan project lama dengan requirement project baru.

Klasifikasikan setiap bagian:

- Reusable concept.
- Reusable code with modification.
- Reference only.
- Must be rewritten.
- Missing entirely.
- Unsafe to reuse.

Output:


| Area | Kondisi Lama | Kebutuhan Baru | Gap | Rekomendasi |
| ---- | ------------ | -------------- | --- | ----------- |




## Fase 3 — Architecture Proposal

Buat usulan:

- System context.
- Container atau service architecture.
- Module boundaries.
- Domain model.
- Database schema.
- API contract.
- Background job architecture.
- External integration architecture.
- Authentication dan authorization.
- Frontend structure.
- Deployment structure.
- Observability.
- Testing strategy.

Gunakan diagram Mermaid jika berguna.

Pisahkan:

- Keputusan yang dapat ditentukan dari fakta.
- Keputusan yang membutuhkan persetujuan user.

Jangan mengimplementasikan keputusan material yang masih menunggu jawaban.

## Fase 4 — Implementation Plan

Buat milestone yang kecil dan dapat diuji.

Contoh milestone:

1. Project foundation.
2. Authentication dan creator domain.
3. Management dan permission.
4. Channel domain.
5. YouTube integration.
6. Twitch integration.
7. Kick integration atau fallback berdasarkan keputusan user.
8. Content normalization.
9. Live polling dan metric snapshots.
10. Homepage.
11. Creator profile.
12. Management profile.
13. Admin atau operational tooling.
14. Testing hardening.
15. Documentation dan deployment preparation.

Setiap milestone harus memiliki:

- Scope.
- File atau module yang diperkirakan berubah.
- Acceptance criteria.
- Test plan.
- Risiko.
- Dependency.



## Fase 5 — Incremental Implementation

Implementasikan milestone satu per satu.

Untuk setiap milestone:

1. Jelaskan apa yang akan dikerjakan.
2. Implementasikan perubahan.
3. Jalankan test terkait.
4. Jalankan lint, type check, dan build.
5. Catat hasil.
6. Perbarui dokumentasi.
7. Laporkan limitation yang masih ada.

Jangan menyatakan milestone selesai sebelum acceptance criteria terpenuhi.

## Fase 6 — Final Validation

Lakukan:

- Full test suite.
- Full build.
- Migration test.
- Permission matrix test.
- Webhook idempotency test.
- Polling duplicate-prevention test.
- Failure recovery test.
- Basic security review.
- Basic performance review.
- Documentation review.
- Requirement traceability review.

Buat tabel traceability:


| Requirement | Implementasi | Test | Status | Catatan |
| ----------- | ------------ | ---- | ------ | ------- |


---



# Dokumentasi yang Harus Dihasilkan

Minimal hasilkan atau perbarui:

- `README.md`
- `docs/project-old-audit.md`
- `docs/claim-validation-ledger.md`
- `docs/gap-analysis.md`
- `docs/architecture.md`
- `docs/domain-model.md`
- `docs/api.md`
- `docs/integrations/youtube.md`
- `docs/integrations/twitch.md`
- `docs/integrations/kick.md`
- `docs/background-jobs.md`
- `docs/permissions.md`
- `docs/local-development.md`
- `docs/deployment.md`
- `docs/testing.md`
- `docs/known-limitations.md`
- `.env.example`

Sesuaikan nama file dengan convention repository jika diperlukan.

Jangan memasukkan credential asli ke dokumentasi atau repository.

---



# Format Laporan Kemajuan

Pada setiap tahap, berikan laporan dengan format:

```text
## Tahap Saat Ini

[Discovery / Validation / Architecture / Implementation / Testing]

## Pekerjaan yang Sudah Selesai

- [...]

## Temuan Penting

- [...]

## Bukti

- path/to/file: symbol atau line terkait

## Keputusan yang Diambil

- Keputusan:
- Dasar:
- Dampak:

## Pertanyaan yang Membutuhkan User

- [...]

## Test yang Dijalankan

- Command:
- Hasil:

## Risiko atau Blocker

- [...]

## Langkah Berikutnya

- [...]
```

Jangan memberikan laporan samar seperti “implementasi sudah selesai” tanpa bukti, file, dan hasil test.

---



# Acceptance Criteria Global

Project dianggap selesai hanya jika:

- Claim penting dari analisis lama telah divalidasi.
- Perbedaan antarhasil analisis telah didokumentasikan.
- Project baru dapat dijalankan menggunakan dokumentasi yang tersedia.
- Database migration dapat dijalankan.
- Authentication bekerja.
- Authorization creator dan management bekerja.
- Satu creator dapat memiliki beberapa channel.
- Satu management dapat menaungi beberapa creator.
- Delegated access dapat dibatasi per resource atau permission.
- YouTube content dapat dinormalisasi.
- Live stream dapat dideteksi dan diperbarui.
- Stream yang berakhir ditangani dengan benar.
- Twitch live stream dapat dideteksi sesuai integrasi yang dipilih.
- Strategi Kick telah diimplementasikan atau didokumentasikan sebagai blocker berdasarkan keputusan user.
- Homepage menampilkan ongoing dan upcoming live stream.
- Multi-stream memiliki presentasi yang jelas.
- Archive dan VOD tampil pada section yang sesuai.
- Creator profile terintegrasi tersedia.
- Management profile tersedia.
- Background processing bersifat idempotent.
- Duplicate content dan duplicate job dicegah.
- Test utama lulus.
- Build lulus.
- Lint dan type check lulus.
- Known limitation didokumentasikan.
- Tidak ada credential sensitif yang masuk repository.

---



# Perilaku yang Dilarang

Jangan:

- Menganggap hasil analisis AI sebagai fakta.
- Mengarang struktur project yang belum diperiksa.
- Mengarang dukungan API eksternal.
- Menggunakan scraping tanpa persetujuan.
- Menyalin project lama secara keseluruhan.
- Mengubah requirement secara diam-diam.
- Memilih teknologi strategis tanpa penjelasan.
- Mengimplementasikan authorization hanya di frontend.
- Menyimpan API key di source code.
- Menjalankan infinite polling tanpa guard.
- Membuat background job yang tidak idempotent.
- Mengabaikan duplicate webhook.
- Menghapus data atau migration lama tanpa persetujuan.
- Menghapus test yang gagal untuk membuat pipeline hijau.
- Menyatakan test berhasil jika tidak dijalankan.
- Menyatakan fitur production-ready jika masih menggunakan mock.
- Menutup temuan yang bertentangan dengan asumsi awal.
- Mengambil keputusan material yang ambigu tanpa bertanya kepada user.

---



# Instruksi Memulai

Mulai dengan urutan berikut:

1. Periksa seluruh path input dan pastikan file dapat diakses.
2. Inventarisasi seluruh dokumen hasil analisis.
3. Inventarisasi struktur project lama.
4. Jelaskan stack dan cara kerja project lama berdasarkan bukti.
5. Buat daftar claim yang akan divalidasi.
6. Buat Claim Validation Ledger awal.
7. Identifikasi pertanyaan atau keputusan yang benar-benar membutuhkan user.
8. Berhenti dan tanyakan keputusan yang bersifat blocking.
9. Jika tidak ada blocker, lanjutkan ke audit dan gap analysis.
10. Jangan mulai implementasi fullstack sebelum memberikan hasil audit awal, gap analysis, serta architecture proposal.

Respons pertama Anda harus berisi:

- Ringkasan pemahaman tugas.
- Daftar input yang berhasil ditemukan.
- Rencana audit.
- Kriteria validasi claim.
- Risiko awal.
- Pertanyaan blocking, jika ada.

Gunakan bahasa yang jelas, spesifik, dan berbasis bukti.