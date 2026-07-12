# Chiến lược test qua bốn mức

| | |
| --- | --- |
| **Mã tài liệu** | ARCH-MONO-01-TEST |
| **Phiên bản** | 1.0 |
| **Ngày ban hành** | 2026-07-12 |
| **Phạm vi** | Test của bốn ứng dụng mẫu `level1`–`level4` |
| **Tài liệu gốc** | [README.md](../README.md) — QĐ-8.4, QĐ-4.4 |

---

## 1. Nguyên tắc: test là thước đo vị trí của logic

QĐ-8.4: *nếu để test một quy tắc nghiệp vụ phải boot cả Laravel + migrate DB +
tạo 5 factory, logic đang nằm sai chỗ.* Vì vậy **cách test thay đổi theo mức
không phải do sở thích, mà là hệ quả trực tiếp của việc logic nằm ở đâu**:

- Mức 1–3: nghiệp vụ dính với Eloquent → chỉ test được qua **feature test** boot
  Laravel + DB. Điều này hợp lý và đủ cho các mức đó.
- Mức 4: nghiệp vụ tách vào Domain POPO → test được bằng **unit test thuần**,
  chạy tức thì. Đây là lời hứa cốt lõi và cũng là **cách kiểm chứng** mức 4 có
  thật sự đúng hay chỉ là cấu trúc rỗng (QĐ-4.4).

## 2. Hai loại test trong repo

| Loại | Có ở mức | Boot Laravel? | Chạm DB? | Đo cái gì |
| --- | --- | --- | --- | --- |
| **Feature test** (`tests/Feature/`) | 1, 2, 3, 4 | có | có (sqlite `:memory:`) | Luồng end-to-end qua HTTP: đặt vé, webhook, soát vé. |
| **Domain unit test** (`tests/Unit/Ticketing/`) | **chỉ 4** | **không** | **không** | Bất biến nghiệp vụ trong aggregate/Value Object. |
| **Arch test** (`tests/Unit/ArchTest.php`) | **chỉ 4** | không | không | Ràng buộc kiến trúc deptrac không bắt được. |

Ở mức 1–3, `tests/Unit/` chỉ có `ExampleTest.php` (placeholder) — **không phải
thiếu sót**: chưa có Domain POPO để unit-test, mọi nghiệp vụ kiểm qua feature.

## 3. Feature test — nền chung của mọi mức

Cấu hình giống nhau bốn mức:

- `tests/Pest.php` gắn `RefreshDatabase` cho thư mục `Feature`.
- `phpunit.xml` ép `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` — mỗi lần chạy
  migrate vào DB trong RAM, không đụng DB thật, không cần cấu hình.

Phủ cùng bốn luồng nghiệp vụ ở mọi mức (tên file xê dịch chút theo mức):

| Feature test | Kiểm |
| --- | --- |
| `AuthTest` | Đăng ký/đăng nhập. |
| `CatalogTest` | Chỉ sự kiện đã publish mới hiển thị (YC-6.2). |
| `OrderTest` | Đặt đơn giữ vé + chốt giá; hết vé báo lỗi (YC-8.2, YC-8.5). |
| `PaymentWebhookTest` / `PaymentConfirmationTest` | Webhook xác nhận → phát hành vé; idempotent (YC-9.2, YC-9.3). |
| `CheckInTest` | Soát vé hợp lệ/đã dùng/không tồn tại; chỉ `scanner` vào được (YC-11.x, YC-4.2). |
| `MyTicketsTest` / `TicketsPageTest` | Trang vé của tôi. |

Ở mức 3–4, feature test còn khẳng định luôn **kỷ luật ranh giới dữ liệu**: ví dụ
`OrderTest` kiểm `ticketType->reserved_count` tăng qua bộ đếm của Catalog chứ
không JOIN sang `orders` (QĐ-3.7) — xem đoạn "holds tickets" trong
`level3/tests/Feature/OrderTest.php`.

## 4. Domain unit test — chỉ mức 4, và là lý do mức 4 tồn tại

`level4/tests/Unit/Ticketing/` test aggregate và Value Object **bằng PHP thuần**
— không `RefreshDatabase`, không factory, không HTTP. Chạy tính bằng mili giây.

Ví dụ `MoneyTest` (Value Object tiền):

```php
it('adds two amounts immutably', function () {
    $a = Money::yen(5000);
    expect($a->add(Money::yen(3000))->amount)->toBe(8000)
        ->and($a->amount)->toBe(5000);           // bất biến: không đổi vế trái
});

it('cannot represent a negative amount', function () {
    Money::yen(-1);
})->throws(InvalidArgumentException::class);      // trạng thái sai không biểu diễn được
```

Ví dụ `TicketCheckInTest` (bất biến §11) dựng vé bằng `Ticket::reconstitute(...)`
rồi kiểm `checkIn()` ném `TicketAlreadyUsed` ở lần soát thứ hai (YC-11.3) — tất
cả **không đụng database**.

| File | Bất biến được kiểm | YC |
| --- | --- | --- |
| `MoneyTest`, `ValueObjectTest` | Tiền không âm, cộng/nhân bất biến, so sánh theo giá trị. | YC-2.2 |
| `LineItemTest` | Chốt giá/tên tại thời điểm tạo đơn; số lượng ≥ 1. | YC-8.5 |
| `OrderTest` (Unit) | Trần 10 vé/đơn; đơn phải có ≥ 1 dòng; máy trạng thái §9; phát hành vé khi `markPaid`. | YC-8.1, §9, YC-10.1 |
| `TicketCheckInTest` | Không soát quá một lần. | YC-11.3 |

**Đây là phép thử của mức 4**: nếu những quy tắc này *không* unit-test được
(phải boot DB mới kiểm được), tức là logic vẫn rò ra Application/Controller —
chính là tín hiệu hạ về mức 3 (§7.5).

## 5. Arch test — bắt cái deptrac không bắt được

`level4/tests/Unit/ArchTest.php` dùng Pest arch để ép các bất biến kiến trúc
**deptrac bỏ lọt** (deptrac chỉ ép chiều phụ thuộc giữa thư mục/layer):

| ArchTest ép | Vì deptrac không thấy | QĐ |
| --- | --- | --- |
| `Ticketing\Domain` không `toUse` `Illuminate`/`Carbon`/`App`/module khác | deptrac không coi `Illuminate` là một layer | QĐ-4.1 |
| Domain không gọi global helper `now()`/`event()`/`config()`… | `toUse` của deptrac chỉ bắt class/namespace, không bắt global function | QĐ-4.1 |
| Value Object là `final` + `readonly`; aggregate `final`; exception `final extends DomainException` | deptrac không nhìn thấy modifier | QĐ-4.1 |
| `OrderRepository`/`TicketRepository`/`TokenGenerator` là interface | dependency inversion là ràng buộc kiểu, không phải chiều phụ thuộc thư mục | QĐ-4.2 |
| `Ticketing\Application` không `toUse` `Illuminate\Database\Eloquent` | đi qua Repository, không chạm ORM trực tiếp | QĐ-4.2 |

Nói cách khác: **deptrac ép ranh giới *giữa* module (QĐ-3.6), arch test ép ranh
giới *bên trong* module mức 4 (QĐ-4.1/4.2).** Hai công cụ bù nhau, đều chạy
trong CI.

## 6. Cách chạy

Mỗi level là một app độc lập — `cd` vào rồi chạy:

```bash
cd level4
composer test                 # = artisan test (Pest), toàn bộ
php artisan test --filter=Money        # lọc theo tên
php artisan test tests/Unit            # chỉ domain + arch test (không boot Feature)
composer deptrac              # ranh giới module (chỉ level3/level4)
vendor/bin/pint --test        # code style
```

CI (`.github/workflows/ci.yml`) chạy **ma trận 4 level song song**, mỗi level ba
gate theo thứ tự: **Pint → Deptrac (chỉ L3–4) → Pest**. Build đỏ nếu bất kỳ gate
nào fail; `fail-fast: false` để thấy hết level nào hỏng.

## 7. Test thay đổi thế nào qua bốn mức

| | Mức 1 | Mức 2 | Mức 3 | Mức 4 |
| --- | --- | --- | --- | --- |
| Feature test (boot + DB) | ✅ toàn bộ nghiệp vụ | ✅ | ✅ + kiểm ranh giới dữ liệu | ✅ (đường HTTP/đọc) |
| Domain unit test (không DB) | — | — | — | ✅ bất biến nghiệp vụ |
| Arch test | — | — | — | ✅ QĐ-4.1/4.2 |
| Deptrac | — | — | ✅ QĐ-3.6 | ✅ QĐ-3.6 |
| "Kiểm một business rule cần gì?" | boot + DB | boot + DB | boot + DB | **PHP thuần, tức thì** |

Dòng cuối là toàn bộ câu chuyện: chi phí kiểm một quy tắc nghiệp vụ giảm từ
"boot cả framework" xuống "gọi một hàm PHP" — nhưng chỉ mua được sau khi đã trả
giá tách Domain ở mức 4 (§7.4).

## 8. Checklist review test

| # | Câu hỏi | Quy định |
| --- | --- | --- |
| 1 | Quy tắc nghiệp vụ mới có test không? | QĐ-8.4 |
| 2 | (Mức 4) Bất biến mới có **domain unit test không cần DB** không? | QĐ-4.4 |
| 3 | (Mức 4) Value Object/aggregate mới có được ArchTest phủ (final/readonly/interface)? | QĐ-4.1, QĐ-4.2 |
| 4 | (Mức 3–4) Có feature test khẳng định không JOIN/không rò Model chéo module? | QĐ-3.4, QĐ-3.7 |
| 5 | Phải boot Laravel + 5 factory để test một rule đơn giản? | ⚠️ Logic nằm sai chỗ (QĐ-8.4). |
