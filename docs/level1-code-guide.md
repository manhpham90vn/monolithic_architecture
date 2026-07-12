# Mức 1 — Giải thích từng file & quy tắc gọi nhau

| | |
| --- | --- |
| **Mã tài liệu** | ARCH-MONO-01-L1 |
| **Phiên bản** | 1.0 |
| **Ngày ban hành** | 2026-07-12 |
| **Phạm vi** | Ứng dụng mẫu `level1/` (CRUD thuần, domain bán vé) |
| **Tài liệu gốc** | [README.md](../README.md) §4 — nhóm quy định QĐ-1 |

---

## 1. Định vị mức 1

Toàn bộ nghiệp vụ nằm **thẳng trong Controller**, không có tầng trung gian. Đây
là bản Laravel mặc định: Controller gọi Eloquent, validation trong Form Request,
phân quyền trong Policy (QĐ-1.1). Không có `src/`, không module, không Action,
không Repository (QĐ-1.3) — tất cả trong `app/`, một namespace `App\`.

Nguyên tắc một câu: **Controller là nơi nghiệp vụ sống; đừng thêm lớp nào chỉ để
chuyển tiếp `Model::create()`** (QĐ-1.3).

## 2. Bản đồ thư mục

```text
app/
  Http/Controllers/     # nghiệp vụ nằm ở đây (QĐ-1.1)
  Http/Requests/        # validation (QĐ-1.1)
  Models/               # Eloquent + logic đơn giản: scope/accessor/relationship (QĐ-1.2)
  Policies/             # phân quyền (QĐ-1.1)
  Console/Commands/     # tác vụ nền (cho hết hạn đơn)
  Mail/                 # email xác nhận
  Providers/            # AppServiceProvider: định nghĩa gate
```

Không có ranh giới module nên **không có quy tắc "gọi chéo"**: mọi class đều có
thể dùng mọi Model. Quy tắc của mức 1 là quy tắc *bố cục trong một Controller*,
không phải quy tắc *giữa các module*.

## 3. Quy tắc gọi nhau — cái gì được, cái gì cấm

### 3.1. Được phép

| # | Được phép | QĐ |
| --- | --- | --- |
| A1 | Controller gọi thẳng Eloquent (`Order::create`, `Ticket::query()`…). | QĐ-1.1 |
| A2 | Logic đơn giản (scope, accessor, relationship, `remaining()`) đặt trong Model. | QĐ-1.2 |
| A3 | Validation trong Form Request; phân quyền trong Policy; gate trong ServiceProvider. | QĐ-1.1 |
| A4 | Dùng `DB::transaction` + `lockForUpdate` ngay trong Controller cho phần cần nguyên tử. | — |

### 3.2. Bị cấm

| # | Cấm | Vì | QĐ |
| --- | --- | --- | --- |
| C1 | Thêm Service layer / Action / Repository. | Một class chỉ để chuyển tiếp `Post::create($data)` là over-engineering: thêm chỗ để đọc, không thêm giá trị. | QĐ-1.3 |
| C2 | Bọc `OrderRepositoryInterface` quanh Eloquent. | Eloquent đã là abstraction; sẽ không bao giờ đổi sang Doctrine. | QĐ-1.3 |

### 3.3. Tín hiệu phải rời mức 1 (lên mức 2)

Xuất hiện **một trong** các dấu hiệu §4.4 README: một action ghi dữ liệu phải
làm nhiều việc (tạo record + gửi mail + trừ kho); cùng logic bị copy giữa
Controller web và API; Controller method vượt ~30 dòng vì chứa nghiệp vụ. Ứng
dụng mẫu này **đã chạm** dấu hiệu đó (xem `OrderController::store` khá dài) — nó
cố tình đứng ở ranh giới để so sánh với mức 2.

## 4. Nhiệm vụ từng file

### 4.1. Controllers — nơi nghiệp vụ sống

| File | Nhiệm vụ |
| --- | --- |
| `Http/Controllers/EventController.php` | Danh sách + chi tiết sự kiện đã publish (đường đọc). |
| `Http/Controllers/OrderController.php` | **Trung tâm**: `store` = kiểm publish → khoá `ticket_types` → kiểm tồn kho → chốt giá → tạo `Order`+`OrderItem` → tạo phiên Stripe (tất cả inline trong transaction). `show` = trang đơn. `cancel` = hủy đơn pending. |
| `Http/Controllers/StripeWebhookController.php` | Webhook Stripe: kiểm chữ ký, `markOrderPaid` = khoá đơn → idempotent → đổi `paid` → **phát hành vé** (token ULID) → gửi mail. Toàn bộ inline. |
| `Http/Controllers/CheckInController.php` | Soát vé: khoá vé theo token → hợp lệ/đã dùng/không tồn tại → đánh dấu `used`. Inline. |
| `Http/Controllers/TicketController.php` | Danh sách vé của người dùng hiện tại (đường đọc). |

### 4.2. Requests, Models, Policies

| File | Nhiệm vụ |
| --- | --- |
| `Http/Requests/StoreOrderRequest.php` | Validate số lượng vé mua (gồm trần 10 vé/đơn, YC-8.1); `selectedQuantities()` trả map đã lọc. |
| `Models/Event.php` | Eloquent + `isPublished()`, scope sự kiện đã công bố (QĐ-1.2). |
| `Models/TicketType.php` | Eloquent + `remaining()` = `quantity − đang giữ − đã bán`; **`reserved` suy ra bằng JOIN sang orders** (được phép vì một app, một DB). |
| `Models/Order.php` | Eloquent + hằng trạng thái + `isPending()`/`isPaid()`/`isExpired()` + quan hệ `user`/`event`/`items`/`tickets`. Cột Stripe nằm ngay trong bảng `orders`. |
| `Models/OrderItem.php`, `Ticket.php` | Eloquent dòng đơn / vé (token unique, `isUsed()`). |
| `Models/User.php` | Auth + `role`, `isScanner()`. |
| `Policies/OrderPolicy.php`, `TicketPolicy.php` | Người dùng chỉ xem đơn/vé của chính mình. |

### 4.3. Tác vụ nền & hạ tầng

| File | Nhiệm vụ |
| --- | --- |
| `Console/Commands/ExpireStaleOrders.php` | `orders:expire`: một câu UPDATE đưa đơn pending quá 15' sang `expired`. Vé được trả **tự nhiên** vì `remaining()` không tính đơn đã hết hạn. Lên lịch mỗi phút (`routes/console.php`). |
| `Mail/OrderConfirmationMail.php` | Mailable email xác nhận kèm vé + QR. |
| `Providers/AppServiceProvider.php` | `Gate::define('check-in', …isScanner())` (YC-4.2). |

## 5. Bốn luồng chạy (tất cả trong Controller)

```text
Đặt vé      : OrderController.store        [TX: khoá ticket_types, kiểm kho, tạo Order] → Stripe
Thanh toán  : StripeWebhookController.handle → markOrderPaid [TX: paid + tạo Ticket] → Mail
Hết hạn     : Schedule orders:expire → ExpireStaleOrders (UPDATE hàng loạt)
Soát vé     : CheckInController.store      [TX: khoá Ticket, đánh dấu used]
```

Khác biệt lớn nhất so với các mức trên: **không có bước "gọi module khác"** — vé
và đơn cùng nằm trong `app/`, Controller thao tác trực tiếp trên mọi Model.

## 6. Checklist review nhanh cho mức 1

| # | Câu hỏi | Kết luận nếu "có" |
| --- | --- | --- |
| 1 | Có class Service/Action/Repository mới chỉ để chuyển tiếp Model? | ❌ Vi phạm C1/C2 (QĐ-1.3) — gọi thẳng Eloquent. |
| 2 | Validation có nằm trong Form Request (không phải trong Controller)? | ✅ Đúng (QĐ-1.1). |
| 3 | Phân quyền có nằm trong Policy? | ✅ Đúng (QĐ-1.1). |
| 4 | Controller method có vượt ~30 dòng vì chứa nghiệp vụ? | ⚠️ Tín hiệu nâng lên mức 2 (§4.4). |
