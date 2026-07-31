# Definisi Routing Config untuk AI Agent

## Implementasi runtime (Monolith)

YAML di [Routing_Price.yaml](Routing_Price.yaml) adalah **source of truth**. Kode memuatnya lewat `App\Domain\PaymentGateway\Support\Routing\RoutingPolicyLoader`, memvalidasi dengan `RoutingPolicyValidator`, lalu menulis ke DB via `PaymentRouteMaterializer` (`php artisan payments:sync-routing-policy`). Resolver checkout: `PaymentChannelResolver` + `PaymentChannelFeeCalculator`.

## Bagaimana config ini didefinisikan

Routing config ini adalah **kontrak deklaratif** antara sistem pembayaran kamu dan AI Agent (atau service router). YAML mendefinisikan policy (channel, PG, settlement, fee); **eksekusi runtime** di Monolith lewat materializer → DB → `PaymentChannelResolver` (lihat bagian "Implementasi runtime" di atas). Agent atau operator mengedit YAML, lalu `php artisan payments:sync-routing-policy` menulis ulang baris `payment_channels` / `payment_channel_routes`. Resolver checkout tidak membaca YAML langsung — ia membaca DB yang sudah disinkronkan, dengan urutan `route_role` lalu `priority`.

Cara Agent menafsirkannya mengikuti alur tetap. Pertama, Agent memetakan intent pembayaran (misalnya “bayar pakai BCA VA” atau “bayar pakai GoPay”) ke **channel enum** internal. Kedua, ia melihat `type`: jika `single`, hanya satu `pg` yang sah dan tidak ada pilihan routing; jika `dual`, Agent wajib mencoba `primary` dulu, lalu `fallback` jika primary gagal, down, atau bank/channel tidak tersedia di PG itu. Ketiga, field `settlement` mengikat **tier waktu pencairan** yang sudah dipilih karena fee lebih kecil pada settlement lebih lama—Agent tidak boleh menaikkan ke H+0 hanya demi cepat cair, kecuali ada override eksplisit di request. Keempat, field `fee` adalah model biaya yang harus dipakai saat estimasi cost, ranking PG, atau menampilkan breakdown ke merchant/user. Field tambahan seperti `single_overrides`, `avoid`, dan `cheap_alt` adalah **aturan khusus**: override memaksa PG tertentu untuk sub-kasus (mis. SeaBank hanya Midtrans), `avoid` adalah larangan keras (mis. iPaymu QRIS H+0), dan `cheap_alt` adalah jalur opsional lebih murah jika UX mengizinkan (mis. GoPay direct 2% bisa diganti QRIS 0,7% bila scan QR acceptable).

Agar Agent konsisten, config harus diperlakukan sebagai **policy immutable per environment** (staging/production), di-versioning, dan dilengkapi skema validasi (tipe `fee` string vs number, enum `pg`, enum `settlement`). Agent tidak “menebak” fee di luar config; ia hanya mengeksekusi policy ini, lalu memanggil API Midtrans atau iPaymu sesuai `pg` terpilih. Jika suatu field tidak ada (mis. channel tidak terdaftar), Agent harus gagal dengan jelas atau minta klarifikasi—bukan mengarang route.

***

## Arti field `fee`

Field `fee` menjawab satu pertanyaan: **berapa yang dipotong PG dari transaksi sukses**, dalam model apa. Ada dua bentuk utama di config ini, dan keduanya **tidak dicampur dalam satu nilai**.

### 1. Fee flat (angka absolut)

Contoh: `fee: 3500` atau `fee: 4000`.

Artinya biaya **tetap per transaksi berhasil**, tidak bergantung pada nominal bayar. Satuan adalah **Rupiah (IDR)**.  

- Transaksi Rp 50.000 → fee Rp 3.500  
- Transaksi Rp 500.000 → fee tetap Rp 3.500  
- Transaksi Rp 5.000.000 → fee tetap Rp 3.500  

Dipakai untuk channel seperti **Virtual Account**, di mana PG/bank mengenakan charge per VA payment, bukan persentase MDR. Di config, `3500` = iPaymu VA pada settlement panjang (H+4); `4000` = Midtrans VA.

**Cara Agent menghitung:**

```text
biaya_layanan = fee                    # sudah dalam IDR
nilai_diterima_merchant ≈ total_bayar - biaya_layanan (- PPN bila berlaku)
```

### 2. Fee persentase (MDR)

Contoh: `fee: "2%"`, `fee: "1.5%"`, `fee: "0.7%"`.

Artinya biaya **proporsional terhadap total transaksi sukses**. String berakhiran `%` = Agent harus parse sebagai desimal (`2%` → `0.02`) lalu dikalikan nominal.  

- `2%` pada Rp 100.000 → fee Rp 2.000  
- `2%` pada Rp 500.000 → fee Rp 10.000  
- `0.7%` pada Rp 100.000 → fee Rp 700  
- `1.5%` pada Rp 200.000 → fee Rp 3.000  

Dipakai untuk **QRIS** dan **e-wallet direct** (GoPay, OVO, DANA, ShopeePay), mengikuti skema MDR.

**Cara Agent menghitung:**

```text
rate = parse_percent(fee)              # "2%" → 0.02
biaya_layanan = total_bayar × rate
nilai_diterima_merchant ≈ total_bayar - biaya_layanan (- PPN bila berlaku)
```

### 3. Yang tidak ada di config ini (tapi perlu diketahui Agent)

Beberapa PG memakai **hybrid** `persentase + flat` (contoh klasik kartu kredit: `2,9% + Rp 2.000`). Channel itu sudah dikurasi keluar dari config ini, jadi Agent **tidak** perlu parser hybrid untuk file routing saat ini. Jika nanti ditambah, konvensinya sebaiknya eksplisit, misalnya:

```yaml
fee:
  percent: 2.9
  flat: 2000
```

bukan string ambigu seperti `"2.9%+2000"` tanpa skema.

### 4. Aturan interpretasi untuk Agent (ringkas)

| Bentuk di YAML | Tipe | Makna | Rumus |
|---|---|---|---|
| `3500` / `4000` (number) | **Flat** | Potongan tetap IDR per trx sukses | `fee` |
| `"0.7%"` / `"1.5%"` / `"2%"` (string `%`) | **Persentase** | Potongan dari **total bayar** | `total × rate` |
| (tidak dipakai di config ini) | Hybrid | % dari total **plus** flat | `(total × rate) + flat` |

Konvensi penting agar Agent tidak salah:

- **Number tanpa `%`** → selalu flat IDR.  
- **String dengan `%`** → selalu fraction of total.  
- Fee dihitung hanya pada **transaksi sukses** (bukan pending/expire/cancel), sesuai model Midtrans/iPaymu.
- `fee` di config adalah **biaya channel yang dibebankan ke pembeli** pada checkout. Nilai ini dimaterialisasi ke `payment_channel_routes.fee_*`, diestimasi di UI/API (`?subtotal=`), dan dicatat pada invoice sebagai `payment_channel_fee`. Midtrans memakai estimasi policy saat charge; iPaymu memakai angka aktual dari provider setelah charge. Saat channel checkout berhasil, policy yang berlaku **disalin immutable** ke `payment_channel_route_snapshots` (baris `payment_channel_routes` tetap mutable setelah YAML sync) — lihat [Route policy snapshot](README.md#route-policy-snapshot) di payment-gateway README.
- **PPN** (mis. 11% atas fee Midtrans pada banyak channel) **belum** digabung ke angka `fee` kecuali channel yang sudah VAT-inclusive di list price (contoh QRIS Midtrans 0,7%). Agent yang butuh net cost harus punya flag terpisah `vat_inclusive: true/false` atau layer kalkulasi pajak di atas config ini.
- Settlement (`H+2`, `H+4`) **bukan fee**, tapi menentukan **tier harga** mana yang sah; mengganti settlement bisa mengganti `fee` (contoh iPaymu QRIS H+0 jauh lebih mahal dari H+2). Itu sebabnya `settlement` dan `fee` selalu dipasangkan di `primary` / `fallback`.

### 5. Contoh bacaan Agent end-to-end

**Kasus A — VA BCA Rp 250.000**

1. Channel = `va` → `type: dual`  
2. Bank dual → `primary.pg = ipaymu`, `settlement = H+4`, `fee = 3500`  
3. Fee flat → biaya = **Rp 3.500** (bukan 1,4% dari 250rb)  
4. Jika iPaymu gagal → `fallback` Midtrans, fee flat **Rp 4.000**

**Kasus B — GoPay direct Rp 250.000**

1. Channel = `gopay` → `type: single`, `pg = midtrans`  
2. `fee = "2%"` → biaya = 250.000 × 0,02 = **Rp 5.000**  
3. Jika user/policy mengizinkan UX QR → `cheap_alt: qris` dengan `fee = "0.7%"` → biaya = **Rp 1.750**

**Kasus C — QRIS Rp 250.000**

1. Channel = `qris` → dual, primary Midtrans H+2, `fee = "0.7%"`  
2. Biaya = **Rp 1.750**  
3. Jangan pernah pilih path di `avoid` (iPaymu H+0), meski settlement lebih cepat.

***

Dengan pemisahan ini, AI Agent punya tiga jaminan: **(1)** tahu channel mana dual vs single, **(2)** tahu PG + settlement mana yang diizinkan agar fee kecil, **(3)** tahu apakah angka fee itu flat Rupiah atau persentase dari total—tanpa ambigu saat menghitung cost atau men-dispatch pembayaran.