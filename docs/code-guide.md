# Giải thích code & quy tắc gọi nhau — cả bốn mức

| | |
| --- | --- |
| **Mã tài liệu** | ARCH-MONO-01-CODE |
| **Phiên bản** | 1.0 |
| **Ngày ban hành** | 2026-07-12 |
| **Phạm vi** | Bốn ứng dụng mẫu `level1`–`level4` (cùng domain: bán vé sự kiện) |
| **Tài liệu gốc** | [README.md](../README.md) — quy chuẩn ARCH-MONO-01 |

---

## 1. Trang này để làm gì

Bốn thư mục `level1`–`level4` **cài cùng một nghiệp vụ bán vé**, chỉ khác cách
tổ chức code theo bốn mức kiến trúc của README. Trang này là mục lục và bảng so
sánh; mỗi mức có một tài liệu riêng liệt kê **nhiệm vụ từng file** và **quy tắc
cái gì gọi vào cái gì, cái gì bị cấm**:

- [level1-code-guide.md](level1-code-guide.md) — CRUD thuần (nhóm QĐ-1)
- [level2-code-guide.md](level2-code-guide.md) — Có nghiệp vụ (nhóm QĐ-2)
- [level3-code-guide.md](level3-code-guide.md) — Modular monolith (nhóm QĐ-3)
- [level4-code-guide.md](level4-code-guide.md) — DDD chiến thuật (nhóm QĐ-4)

Tài liệu nền: thiết kế DB ([database-design.md](database-design.md)), spec
nghiệp vụ ([ticket-sales-spec.md](ticket-sales-spec.md)).

## 2. Cùng một nghiệp vụ, bốn cách tổ chức

| | Mức 1 | Mức 2 | Mức 3 | Mức 4 |
| --- | --- | --- | --- | --- |
| Code nghiệp vụ nằm ở | `app/` (phẳng) | `app/` (chia theo tầng kỹ thuật) | `src/<Module>/` (chia theo nghiệp vụ) | `src/<Module>/` + Domain/Application/Infrastructure |
| Đơn vị nghiệp vụ | Controller gọi thẳng Eloquent | Action + DTO + Event | Module + Public API | Aggregate + Repository + Mapper |
| Số "module" | 1 (không tách) | 1 (thư mục con Order/Payment/…) | 4 (Catalog, Ticketing, Payment, CheckIn) | 4 (Ticketing nâng mức 4, còn lại mức 3) |
| Ranh giới ép bằng công cụ | không | không | `deptrac` trong CI | `deptrac` trong CI |
| "Đặt vé" viết ở đâu | `OrderController::store` | `Actions/Order/PlaceOrder` | `Ticketing\Actions\PlaceOrder` | `Ticketing\Application\PlaceOrderHandler` + `Domain\Order\Order::place` |

Một điểm quan trọng của mức 4 minh hoạ QĐ-0.3 (**mức áp cho từng module, không
phải cả hệ thống**): trong `level4`, chỉ module **Ticketing** được nâng lên mức
4 (có `Domain/`, `Application/`, `Infrastructure/`); ba module Catalog, Payment,
CheckIn **giữ nguyên mức 3**, giống hệt `level3`.

## 3. Quy tắc "gọi vào" tăng dần theo mức

Mỗi mức thêm ràng buộc, không xoá ràng buộc của mức dưới. Bảng dưới tóm tắt
"cái gì được gọi vào cái gì" và "cái gì bị cấm" — chi tiết trong tài liệu từng
mức.

| Mức | Được gọi thẳng | Bị cấm | QĐ chính |
| --- | --- | --- | --- |
| 1 | Controller → Eloquent Model. Validation trong Form Request, phân quyền trong Policy. | Thêm Service / Action / Repository khi chưa có tín hiệu. | QĐ-1.1, QĐ-1.3 |
| 2 | Controller → Action (`handle()`) → Model. Việc phụ qua Event/Listener/Job. | Fat Service; truyền `array` thay DTO; Repository; nghiệp vụ trong Controller. | QĐ-2.1…QĐ-2.6 |
| 3 | Trong module: như mức 2. Giữa module: chỉ qua `Contracts\` (Public API / event). | Import Model/class internal của module khác; JOIN/FK chéo module; Model trong event; event khi cần kết quả. | QĐ-3.3, QĐ-3.4, QĐ-3.5, QĐ-3.7 |
| 4 | Controller → Application handler → Domain aggregate + Repository interface. Repository impl → Eloquent + Mapper. | Domain import Laravel/Eloquent; đổi trạng thái bằng `->update()` vòng qua aggregate; nghiệp vụ ở Application/Controller thay vì trong entity. | QĐ-4.1, QĐ-4.2, QĐ-4.3 |

## 4. Bốn luồng nghiệp vụ (giống nhau mọi mức, khác chỗ đặt code)

Cùng bốn luồng ở mọi mức — chỉ khác code nằm ở lớp nào:

1. **Đặt vé** — kiểm event đã publish → giữ vé (khoá tồn kho) → chốt giá/tên →
   tạo đơn pending giữ 15' → chuyển Stripe.
2. **Xác nhận thanh toán** — webhook Stripe (idempotent) → đánh dấu đơn paid →
   phát hành vé (token QR) → gửi mail.
3. **Hết hạn đơn** — scheduler mỗi phút → đơn pending quá 15' → expired + trả vé.
4. **Soát vé** — nhân viên `scanner` nhập token → hợp lệ/đã dùng/không tồn tại,
   đánh dấu đã dùng (khoá chống quét đôi).

Bảng "code nằm ở đâu" cho luồng **đặt vé** làm ví dụ:

| Bước | Mức 1 | Mức 2 | Mức 3 | Mức 4 |
| --- | --- | --- | --- | --- |
| Kiểm publish | Controller | Controller | Controller + `CatalogApi` | Controller + `CatalogApi` |
| Giữ vé | inline trong Controller | `PlaceOrder` action | `CatalogApi->reserveTickets` | `CatalogApi->reserveTickets` |
| Chốt giá/tên | inline | trong action | trong action | `Domain\Order\LineItem` (bất biến) |
| Ràng buộc ≤ 10 vé | Form Request | Form Request | Form Request | `Domain\Order\Order::place` (aggregate) |
| Tạo đơn | `Order::create` | `Order::create` | `Order::create` | `OrderRepository->save` (qua Mapper) |
| Tạo phiên Stripe | inline trong Controller | `CreateStripeCheckout` action | `PaymentApi` | `PaymentApi` |

## 5. Ma trận "được / cấm" nhanh khi review

| Câu hỏi khi đọc một file | 1 | 2 | 3 | 4 |
| --- | --- | --- | --- | --- |
| Controller chứa > ~30 dòng nghiệp vụ? | cảnh báo (§4.4) | ❌ QĐ-2.4 | ❌ | ❌ |
| Có class Action/Service/Repository "chuyển tiếp" thuần? | ❌ QĐ-1.3 | Repository ❌ QĐ-2.6 | Repository ❌ | Repository ✅ QĐ-4.2 |
| Truyền `array $data` giữa các tầng? | (chấp nhận) | ❌ QĐ-2.3 | ❌ | ❌ |
| Việc phụ (mail/log) nhét trong nghiệp vụ chính? | (chấp nhận) | ❌ QĐ-2.5 | ❌ QĐ-3.11 | ❌ |
| Import Model của "vùng" khác? | không áp dụng | không áp dụng | ❌ QĐ-3.4 | ❌ QĐ-3.4 |
| JOIN/FK chéo module? | tự do | tự do | ❌ QĐ-3.7 | ❌ QĐ-3.7 |
| Domain import Laravel? | không áp dụng | không áp dụng | không áp dụng | ❌ QĐ-4.1 |

> Nhắc lại nguyên tắc README: **mức cao hơn không "chuẩn hơn" mức thấp — mức
> đúng là mức thấp nhất chưa phát sinh tín hiệu nâng cấp** (QĐ-0.1). Bốn ứng
> dụng mẫu tồn tại để so sánh, không phải để mọi dự án chạy tới mức 4.
