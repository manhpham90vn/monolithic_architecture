# Mức 2 — Giải thích từng file & quy tắc gọi nhau

| | |
| --- | --- |
| **Mã tài liệu** | ARCH-MONO-01-L2 |
| **Phiên bản** | 1.0 |
| **Ngày ban hành** | 2026-07-12 |
| **Phạm vi** | Ứng dụng mẫu `level2/` (có nghiệp vụ, domain bán vé) |
| **Tài liệu gốc** | [README.md](../README.md) §5 — nhóm quy định QĐ-2 |

---

## 1. Định vị mức 2

Mục tiêu mức 2: **tách phần "làm gì" ra khỏi phần "nhận HTTP"**. Nghiệp vụ rời
Controller, đóng gói thành **Action** — mỗi class một hành vi, một method
`handle()` (QĐ-2.1). Dữ liệu giữa các tầng đi bằng **DTO** (QĐ-2.3); việc phụ
(gửi mail) đẩy qua **Event/Listener** (QĐ-2.5). Vẫn một app, một namespace
`App\`, **chia theo tầng kỹ thuật** (`Actions/`, `Data/`, `Events/`,
`Listeners/`), có thư mục con theo nghiệp vụ (`Order/`, `Payment/`, `CheckIn/`).

Nguyên tắc một câu: **Controller chỉ điều phối; mỗi hành vi nghiệp vụ là một
Action nhỏ, không phải một Service phình to** (QĐ-2.1, QĐ-2.2).

## 2. Bản đồ thư mục

```text
app/
  Actions/Order/        # PlaceOrder, CancelOrder, ExpireStaleOrders
  Actions/Payment/      # ConfirmPayment, CreateStripeCheckout
  Actions/CheckIn/      # CheckInTicket
  Data/                 # DTO: PlaceOrderData, PaymentConfirmationData, CheckInResult
  Enums/                # CheckInStatus
  Events/               # OrderPaid
  Listeners/            # IssueTickets (sync), SendOrderConfirmation (queued)
  Http/Controllers/     # mỏng: nhận request → gọi Action → trả response (QĐ-2.4)
  Http/Requests/        # validation
  Models/ Policies/ Mail/ Console/Commands/
```

Chia theo **kỹ thuật** (mọi Action ở `Actions/`) là đặc trưng — và cũng là giới
hạn — của mức 2: khi `Actions/` phình ~60 file trộn Order/Inventory/Billing thì
đó là tín hiệu lên mức 3 (§5.4).

## 3. Quy tắc gọi nhau — cái gì được, cái gì cấm

### 3.1. Chuỗi gọi chuẩn

```text
Controller ──gọi──> Action.handle(DTO) ──> Model
                          │
                          └──dispatch──> Event ──listen──> Listener (việc phụ)
```

### 3.2. Được phép

| # | Được phép | QĐ |
| --- | --- | --- |
| A1 | Controller gọi Action, truyền DTO dựng từ request. | QĐ-2.4 |
| A2 | Action gọi Eloquent trực tiếp (Eloquent đã là abstraction). | QĐ-2.6 |
| A3 | Action phát Event; Listener làm việc phụ (mail, log, đồng bộ). | QĐ-2.5 |
| A4 | Listener đồng bộ (sync) chạy trong transaction để nguyên tử; Listener queued đặt `afterCommit=true` để chờ commit. | QĐ-2.5 |

### 3.3. Bị cấm

| # | Cấm | Vì | QĐ |
| --- | --- | --- | --- |
| C1 | Gom nhiều method vào một `OrderService`. | Service phình thành class 800 dòng — chỉ là đổi tên "fat model". | QĐ-2.2 |
| C2 | Truyền `array $data` mù mờ giữa các tầng. | Mất kiểu, mất tự-tài-liệu — dùng DTO. | QĐ-2.3 |
| C3 | Nhét việc phụ (gửi mail, ghi log) vào Action chính. | Action chỉ lo nghiệp vụ chính; việc phụ qua Event/Job. | QĐ-2.5 |
| C4 | Thêm Repository bọc Eloquent. | Indirection vô nghĩa ở mức này. | QĐ-2.6 |
| C5 | Để nghiệp vụ ở lại trong Controller. | Controller chỉ nhận request → gọi Action → trả response. | QĐ-2.4 |

## 4. Nhiệm vụ từng file

### 4.1. Actions — mỗi class một hành vi (QĐ-2.1)

| File | Nhiệm vụ | Gọi ra |
| --- | --- | --- |
| `Actions/Order/PlaceOrder.php` | Khoá `ticket_types`, kiểm tồn kho, chốt giá, tạo `Order`+`OrderItem` giữ 15'. Ném `ValidationException` khi hết vé. | Model |
| `Actions/Order/CancelOrder.php` | Hủy đơn pending (vé trả tự nhiên qua `remaining()`). | Model |
| `Actions/Order/ExpireStaleOrders.php` | Đưa đơn pending quá 15' sang `expired`. | Model |
| `Actions/Payment/CreateStripeCheckout.php` | Tạo phiên Stripe (JPY, tổng đơn), ghi `stripe_session_id`; trả `null` nếu thiếu khoá. | Stripe SDK |
| `Actions/Payment/ConfirmPayment.php` | Khoá đơn, idempotent, đổi `paid`, **phát `OrderPaid`** (việc phụ đẩy qua listener). | `OrderPaid` |
| `Actions/CheckIn/CheckInTicket.php` | Khoá vé theo token, trả `CheckInResult`, đánh dấu `used`. | Model |

### 4.2. DTO, Enum, Event, Listener

| File | Nhiệm vụ |
| --- | --- |
| `Data/PlaceOrderData.php` | DTO đầu vào đặt vé, `fromRequest()` dựng từ Form Request (QĐ-2.3). |
| `Data/PaymentConfirmationData.php` | DTO chuẩn hoá payload webhook Stripe. |
| `Data/CheckInResult.php` | DTO kết quả soát vé (status + ticket). |
| `Enums/CheckInStatus.php` | Enum `Valid`/`Used`/`Nonexistent`. |
| `Events/OrderPaid.php` | Event "đơn đã thanh toán". Payload là Model `Order` (được phép ở mức 2 — chưa có ranh giới module; ở mức 3 sẽ bị cấm, QĐ-3.4). |
| `Listeners/IssueTickets.php` | **Sync**: phát hành mỗi vé một token — chạy trong transaction của `ConfirmPayment` nên nguyên tử với đổi trạng thái. |
| `Listeners/SendOrderConfirmation.php` | **Queued**, `afterCommit=true`: gửi mail sau khi transaction commit (không gửi cho đơn rollback). |

### 4.3. Controller, Model, hạ tầng

| File | Nhiệm vụ |
| --- | --- |
| `Http/Controllers/OrderController.php` | `store`: `PlaceOrder` → `CreateStripeCheckout`. `show`/`cancel`. Mỏng đúng QĐ-2.4. |
| `Http/Controllers/StripeWebhookController.php` | Kiểm chữ ký → `ConfirmPayment->handle(DTO)`. |
| `Http/Controllers/CheckInController.php` | `CheckInTicket->handle(token)` → render kết quả. |
| `Http/Controllers/EventController.php`, `TicketController.php` | Đường đọc (danh sách sự kiện / vé). |
| `Http/Requests/*` | Validation `StoreOrderRequest`, `CheckInRequest`. |
| `Models/*`, `Policies/*`, `Mail/*` | Như mức 1 (Eloquent + logic đơn giản; `remaining()` suy `reserved` bằng JOIN — vẫn một DB). |
| `Console/Commands/ExpireStaleOrdersCommand.php` | Lối vào CLI `orders:expire` → gọi `ExpireStaleOrders` action (nghiệp vụ không nằm trong command). |

## 5. Bốn luồng chạy

```text
Đặt vé      : OrderController.store ⇒ PlaceOrder ⇒ CreateStripeCheckout → Stripe
Thanh toán  : StripeWebhookController ⇒ ConfirmPayment [TX] ⤳ OrderPaid
                 ├─ IssueTickets (sync, trong TX)  → tạo Ticket
                 └─ SendOrderConfirmation (queued, sau commit) → Mail
Hết hạn     : Schedule orders:expire → ExpireStaleOrdersCommand ⇒ ExpireStaleOrders
Soát vé     : CheckInController.store ⇒ CheckInTicket [TX]
```

`⇒` gọi Action, `⤳` event. So với mức 1: nghiệp vụ đã ra khỏi Controller và
việc phụ (mail) đã tách qua Event — nhưng vẫn **một namespace, gọi Model tự do**
(chưa có ranh giới module).

## 6. Checklist review nhanh cho mức 2

| # | Câu hỏi | Kết luận nếu "có" |
| --- | --- | --- |
| 1 | Nghiệp vụ có nằm trong Action với một method `handle()`? | ✅ Đúng (QĐ-2.1). |
| 2 | Có `OrderService` gom nhiều method? | ❌ Vi phạm C1 (QĐ-2.2). |
| 3 | Có truyền `array $data` giữa các tầng? | ❌ Vi phạm C2 (QĐ-2.3). |
| 4 | Việc phụ (mail/log) bị nhét vào Action chính thay vì Event/Job? | ❌ Vi phạm C3 (QĐ-2.5). |
| 5 | Có Repository bọc Eloquent? | ❌ Vi phạm C4 (QĐ-2.6). |
| 6 | `Actions/` đã phình trộn nhiều mảng nghiệp vụ chưa? | ⚠️ Tín hiệu lên mức 3 (§5.4). |
