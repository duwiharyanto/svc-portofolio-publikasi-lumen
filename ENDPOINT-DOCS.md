# ENDPOINT

## ENV

**uuid-sitasi** : uuid sitasi utama

**uuid-sitasi-meta** : uuid sitasi per tahun

## Sitasi

**Desc** : Mengambil sitasi detail <span style="color:red">**(Update)**</span>

**Method** : GET

**Query Param** :

```json
uuid-sitasi : 57d7f30d-50c7-40aa-ab05-a16ffd972c13
```

**URI** : {{base-url}}/sitasi/publikasi/detail?uuid-sitasi=57d7f30d-50c7-40aa-ab05-a16ffd972c13

**Response dengan uuid query param** :

```json
{
    {
    "message": "Data Sitasi berhasil diambil",
    "data": {
        "nama": "AHMAD DJAMLI",
        "nik": "200000101",
        "status": "Diverifikasi",
        "kd_status": "DVR",
        "bentuk_publikasi": "Perolehan sitasi di Scopus/Web of Science (WoS)",
        "kd_bentuk_publikasi": "SIT-1",
        "sitasi_jenis": "individual",
        "sitasi_total": 74,
        "sitasi_link": "https://gateway.uii.ac.id/portofolio/publikasi_karya/update",
        "tgl_input": "2024-07-03 09:02:37",
        "uuid": "57d7f30d-50c7-40aa-ab05-a16ffd972c13",
        "sitasi_meta": [
            {
                "sitasi_jumlah": 10,
                "sitasi_tahun": "2021-01-01",
                "catatan": null,
                "flag_aktif": 1,
                "flag_tolak_remunerasi": 0,
                "flag_perbaikan_remunerasi": 0,
                "kd_status": "DVR",
                "status": "Diverifikasi",
                "gambar_halaman": "dummy-image.jpeg",
                "gambar_halaman_path": "sitasi/200000101/20240703090343-6789cd1b-b04a-4bcd-9042-77cb3d814fed-dummy_image.jpeg",
                "gambar_halaman_url": "https://s3-dev.uii.ac.id/portofolio/sitasi/200000101/20240703090343-6789cd1b-b04a-4bcd-9042-77cb3d814fed-dummy_image.jpeg?X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=lmZPXbUgOtkgHa7yiTO6%2F20240731%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20240731T032021Z&X-Amz-SignedHeaders=host&X-Amz-Expires=1800&X-Amz-Signature=a689faad546506b177bc26ff24df667f1c3c854b971bac0a6ef7fbd837cbcc2f",
                "gambar_halaman_uuid": "798cf3e8-38e0-11ef-a13d-005056af424d",
                "uuid": "6789cd1b-b04a-4bcd-9042-77cb3d814fed"
            },
            {
                "sitasi_jumlah": 20,
                "sitasi_tahun": "2020-01-01",
                "catatan": null,
                "flag_aktif": 1,
                "flag_tolak_remunerasi": 0,
                "flag_perbaikan_remunerasi": 0,
                "kd_status": "DVR",
                "status": "Diverifikasi",
                "gambar_halaman": "dummy-image.jpeg",
                "gambar_halaman_path": "sitasi/200000101/20240703020236-dde5ae9c-0504-4a89-923f-90017a604094-dummy_image.jpeg",
                "gambar_halaman_url": "https://s3-dev.uii.ac.id/portofolio/sitasi/200000101/20240703020236-dde5ae9c-0504-4a89-923f-90017a604094-dummy_image.jpeg?X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=lmZPXbUgOtkgHa7yiTO6%2F20240731%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20240731T032021Z&X-Amz-SignedHeaders=host&X-Amz-Expires=1800&X-Amz-Signature=1416d1e920da6baee8578b4d4de098c97c835d91d8d56e6ab4906cd647e4bff7",
                "gambar_halaman_uuid": "52653f86-38e0-11ef-a13d-005056af424d",
                "uuid": "dde5ae9c-0504-4a89-923f-90017a604094"
            },

        ]
    }
}
}
```

**Response tanpa uuid query param** :

```json
{
    "message": "Data Sitasi berhasil diambil",
    "data": []
}
```

---

**Desc** : Mengambil sitasi detail per tahun

**Method** : GET

**URI** : {{base-url}}/sitasi/publikasi/year/{uuid-sitasi-meta}/detail

**Res Success** :

```json
{
    "data": {
        "sitasi_jumlah": 10,
        "sitasi_tahun": "2021-01-01",
        "catatan": null,
        "flag_aktif": 1,
        "flag_tolak_remunerasi": 0,
        "flag_perbaikan_remunerasi": 0,
        "kd_status": "DVR",
        "status": "Diverifikasi",
        "gambar_halaman": "dummy-image.jpeg",
        "gambar_halaman_path": "sitasi/200000101/20240703090343-6789cd1b-b04a-4bcd-9042-77cb3d814fed-dummy_image.jpeg",
        "gambar_halaman_url": "https://s3-dev.uii.ac.id/portofolio/sitasi/200000101/20240703090343-6789cd1b-b04a-4bcd-9042-77cb3d814fed-dummy_image.jpeg?X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=lmZPXbUgOtkgHa7yiTO6%2F20240731%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20240731T032352Z&X-Amz-SignedHeaders=host&X-Amz-Expires=1800&X-Amz-Signature=18614b824e682854d2c496a2efa5386335cc5c042bc1ac28e1f5ec3ea7b9e620",
        "gambar_halaman_uuid": "798cf3e8-38e0-11ef-a13d-005056af424d",
        "uuid": "6789cd1b-b04a-4bcd-9042-77cb3d814fed"
    },
    "message": "Sitasi detail per tahun ditampilkan"
}
```

---

**Desc** : Menyimpan sitasi detail

**Method** : POST

**Payload** :

```json
uuid_sitasi:57d7f30d-50c7-40aa-ab05-a16ffd972c13
sitasi_tahun:2017
sitasi_jumlah:20
gambar_halaman_file:binary
```

**URI** : {{base-url}}/sitasi/publikasi/{nik}/detail

**Res Success** :

```json
{
    "message": "Sitasi detail ditambahkan",
    "data": {
        "uuid_sitasi_meta": "fa03eb06-ebd1-43de-92c5-e37b193ac46c",
        "uuid_sitasi_file": "5fe08d0e-beb4-4792-8685-b365f7115bd5"
    }
}
```

**Res Fail** :

```json
{
    "message": "Isian yang diberikan tidak valid",
    "data": {
        "sitasi_jumlah": ["Input sitasi jumlah wajib diisi."]
    }
}
```

---

**Desc** : Hapus sitasi detail

**Method** : PUT

**URI** : {{base-url}}/sitasi/publikasi/{uuid}/delete

**Payload** :

```json
    catatan_penghapusan:data tidak valid
```

**Res Success** :

```json
{
    "message": "Sitasi detail barhasil dihapus"
}
```

**Res Fail** :

```json
{
    "message": "Isian yang diberikan tidak valid",
    "data": {
        "catatan_penghapusan": ["Input catatan penghapusan wajib diisi."]
    }
}
```
