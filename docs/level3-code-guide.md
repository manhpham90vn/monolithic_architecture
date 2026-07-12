# Mức 3 — Giải thích từng file & quy tắc gọi nhau

| | |
| --- | --- |
| **Mã tài liệu** | ARCH-MONO-01-L3 |
| **Phiên bản** | 1.0 |
| **Ngày ban hành** | 2026-07-12 |
| **Phạm vi** | Ứng dụng mẫu `level3/` (modular monolith, domain bán vé) |
| **Tài liệu gốc** | [README.md](../README.md) §6 — nhóm quy định QĐ-3 |

---

## 1. Cách đọc tài liệu này

Tài liệu trả lời hai câu hỏi khi đọc code mức 3:

1. **Từng file dùng để làm gì** (§4 — liệt kê theo module).
2. **Cái gì được gọi vào cái gì, cái gì bị cấm** (§3 — ma trận phụ thuộc, và §5
   — luồng chạy thực tế).

Mọi quy tắc đều truy vết về một quy định trong README (ví dụ *QĐ-3.3*) hoặc một
yêu cầu nghiệp vụ trong spec (*YC-8.x*, xem [ticket-sales-spec.md](ticket-sales-spec.md)).
Thiết kế database đi kèm: [database-design.md](database-design.md).

Nguyên tắc một câu để nhớ toàn bộ mức 3: **module chỉ được chạm vào
`Contracts\` của module khác; mọi thứ ngoài `Contracts\` là nội bộ, cấm đụng
tới** (QĐ-3.3).

## 2. Bản đồ bốn module

Code nghiệp vụ nằm trong `src/<Module>/`; hạ tầng (auth, kernel, User) ở `app/`.

| Module | Sở hữu bảng | Public API (`Contracts\`) | Vai trò |
| --- | --- | --- | --- |
| **Catalog** | `events`, `ticket_types` | `CatalogApi` | Nguồn sự thật về sự kiện, hạng vé, tồn kho. Giữ / trả / chốt vé. |
| **Ticketing** | `orders`, `order_items`, `tickets` | `TicketingApi` | Đặt đơn, phát hành vé, soát vé. Trung tâm điều phối. |
| **Payment** | `payments` | `PaymentApi` | Mọi thứ về Stripe: tạo phiên checkout, nhận webhook, xác nhận trả tiền. |
| **CheckIn** | *(không có)* | *(không có)* | Chỉ là UI soát vé cho nhân viên; đọc/ghi qua `TicketingApi`. |

`CheckIn` không sở hữu bảng và không có `Contracts\` vì **không module nào phụ
thuộc ngược vào nó** — nó là "lá" trong đồ thị phụ thuộc, nên không cần cửa ngõ
(QĐ-3.3).

Cấu trúc chuẩn của một module (lấy Catalog làm ví dụ):

```text
src/Catalog/
  Contracts/            # public surface DUY NHẤT (QĐ-3.3)
    CatalogApi.php        #   interface cửa ngõ
    EventInfo.php         #   DTO trả ra ngoài
    TicketTypeInfo.php    #   DTO trả ra ngoài
    NotEnoughTickets.php  #   exception công bố ra ngoài
  Actions/              # nghiệp vụ, mỗi class một handle() (QĐ-2.1)
  Models/               # Eloquent, INTERNAL — không lộ ra ngoài (QĐ-3.4)
  Http/                 # controller + routes của riêng module
  Listeners/            # phản ứng với event (nội bộ / chéo module)
  Database/Migrations/  # migration của riêng module (QĐ-3.2)
  Providers/            # ServiceProvider: bind API, nạp route/migration/view
  Resources/views/      # view của riêng module
  CatalogApiImpl.php    # implementation của CatalogApi — INTERNAL
```

## 3. Quy tắc gọi nhau — cái gì được, cái gì cấm

### 3.1. Ma trận phụ thuộc (nguồn: `deptrac.yaml`)

Đây là luật được **ép bằng công cụ** trong CI (`composer deptrac`, QĐ-3.6):
build đỏ nếu vi phạm. Đọc theo hàng "X được import Y":

| Từ ↓ \ Được dùng → | Catalog `Contracts` | Ticketing `Contracts` | Payment `Contracts` | Bất kỳ `Internal` khác |
| --- | --- | --- | --- | --- |
| **Catalog** internal | ✅ (của mình) | ✅ (nghe `OrderPaid`) | ❌ | ❌ |
| **Ticketing** internal | ✅ | ✅ (của mình) | ✅ | ❌ |
| **Payment** internal | ❌ | ❌ | ✅ (của mình) | ❌ |
| **CheckIn** internal | ❌ | ✅ (soát vé) | ❌ | ❌ |
| Bất kỳ `Contracts` | ❌ | ❌ | ❌ | ❌ (Contracts tự chứa) |

Đọc ma trận: **mọi ô "được" đều rơi vào cột `Contracts`**. Không có ô nào cho
phép chạm `Internal` của module khác — đó chính là QĐ-3.3. Ô "Contracts tự
chứa" nghĩa là `Contracts\` không được kéo Model/Action nội bộ ra ngoài, nếu
không nó hết là "public surface sạch".

### 3.2. Bốn điều CẤM tuyệt đối

| # | Cấm | Vì | QĐ |
| --- | --- | --- | --- |
| C1 | `use Catalog\Models\TicketType` trong Ticketing (hay bất kỳ Model chéo module) | Trao quyền `->update()/->delete()` + lazy-load object graph module khác | QĐ-3.4 |
| C2 | JOIN hoặc khai báo foreign key giữa bảng của hai module | Ràng buộc chéo module khiến ranh giới dữ liệu không còn tách được | QĐ-3.7 |
| C3 | Nhét Eloquent Model vào payload event chéo module | Model rò rỉ ra ngoài; event phải chỉ mang scalar/DTO | QĐ-3.4, QĐ-3.5 |
| C4 | Dùng event khi **cần kết quả trả về** (ví dụ soát vé) | Event một chiều, không trả kết quả → phải gọi Public API trực tiếp | QĐ-3.5 |

### 3.3. Ba điều ĐƯỢC PHÉP (dễ tưởng là cấm)

| # | Được phép | Điều kiện | QĐ |
| --- | --- | --- | --- |
| A1 | Foreign key từ bảng module về `users` | `users` là hạ tầng ở `app/`, không phải module | QĐ-3.9 |
| A2 | Gọi Public API ghi dữ liệu của module khác **trong cùng transaction** | API đó phải an toàn trong transaction: không tự commit, không side-effect ngoài DB | QĐ-3.11 |
| A3 | Một module có `Listeners/` nghe event trong `Contracts\` của module khác | Chỉ "chuyện đã xảy ra, phản ứng một chiều"; xử lý sau commit | QĐ-3.5, QĐ-3.12 |

### 3.4. Hai kênh giao tiếp giữa module

Chọn kênh theo: **có cần kết quả trả về không?**

- **Gọi Public API trực tiếp** (đồng bộ, có kết quả): `Ticketing → CatalogApi`
  để giữ/trả vé; `Ticketing → PaymentApi` để tạo phiên Stripe; `CheckIn →
  TicketingApi` để soát vé. Dùng khi cần kết quả hoặc cần rollback cùng nhau.
- **Domain Event** (một chiều, sau commit): `Ticketing` phát `OrderPaid`,
  `Payment` phát `PaymentSucceeded`; module khác `Event::listen`. Coupling gần
  0, dùng cho việc phụ "đã xảy ra rồi, module khác phản ứng". Mọi event chéo
  module đều `implements ShouldDispatchAfterCommit` (QĐ-3.12).

## 4. Nhiệm vụ từng file

Ký hiệu cột "Gọi ra ngoài": module/Contract mà file này phụ thuộc vào.

### 4.1. Module Catalog — nguồn sự thật về vé & tồn kho

| File | Nhiệm vụ | Gọi ra ngoài |
| --- | --- | --- |
| `Contracts/CatalogApi.php` | Cửa ngõ duy nhất: `eventInfo(s)`, `ticketTypeInfos`, `reserveTickets`, `releaseTickets`, `commitTicketSales`. | — |
| `Contracts/EventInfo.php` | DTO thông tin sự kiện trả ra ngoài (thay cho Model `Event`). | — |
| `Contracts/TicketTypeInfo.php` | DTO hạng vé (id, giá, tên, số còn lại) — bản chụp trả ra khi giữ vé. | — |
| `Contracts/NotEnoughTickets.php` | Exception công bố: một hạng không đủ vé. Ticketing bắt và dịch sang lỗi form. | — |
| `CatalogApiImpl.php` | Hiện thực `CatalogApi`; đọc Model, map sang DTO; ủy quyền ghi cho Action. | — |
| `Actions/ReserveTickets.php` | Khoá hàng `ticket_types` (`lockForUpdate`), tăng `reserved_count`, trả `TicketTypeInfo` chụp dưới khoá. Chống bán quá số khi mua đồng thời (YC-8.3). | — |
| `Actions/CommitTicketSales.php` | Khi đơn đã trả tiền: chuyển vé từ "đang giữ" sang "đã bán" (`reserved_count-`, `sold_count+`). Vé bị trừ vĩnh viễn khỏi kho ở đây (YC-8.4). | — |
| `Actions/ReleaseTickets.php` | Khi đơn hết hạn/hủy: trả lại vé đã giữ (`reserved_count-`). | — |
| `Listeners/CommitTicketSalesOnOrderPaid.php` | Nghe `Ticketing\Contracts\OrderPaid` → gọi `CommitTicketSales`. Đây là điểm Catalog được phép đụng Contracts của Ticketing (deptrac). | `Ticketing\Contracts` |
| `Models/Event.php`, `Models/TicketType.php` | Eloquent nội bộ. `TicketType::remaining()` = `quantity - reserved - sold`. **Không lộ ra ngoài** (QĐ-3.4). | — |
| `Http/EventController.php`, `Http/routes.php` | Trang danh sách / chi tiết sự kiện (public). | — |
| `Providers/CatalogServiceProvider.php` | Bind `CatalogApi→Impl`; nạp route/migration/view; `Event::listen(OrderPaid → CommitTicketSalesOnOrderPaid)`. | `Ticketing\Contracts\OrderPaid` |

### 4.2. Module Ticketing — đặt đơn, phát hành & soát vé

| File | Nhiệm vụ | Gọi ra ngoài |
| --- | --- | --- |
| `Contracts/TicketingApi.php` | Cửa ngõ: `checkIn(token)`. CheckIn dùng để soát vé (cần kết quả → API, không event). | — |
| `Contracts/OrderPaid.php` | **Event chéo module**: đơn vừa xác nhận trả tiền. Payload scalar (`orderId`, `quantities`). `ShouldDispatchAfterCommit`. | — |
| `Contracts/CheckInResult.php`, `CheckInStatus.php`, `TicketSummary.php` | DTO/enum kết quả soát vé trả ra ngoài. | — |
| `TicketingApiImpl.php` | Hiện thực `checkIn`; map Model `Ticket` → `TicketSummary`; lấy tên sự kiện qua `CatalogApi`, tên người mua qua `User`. | `Catalog\Contracts`, `App\Models\User` |
| `Actions/PlaceOrder.php` | Mở transaction → `CatalogApi->reserveTickets` → chốt giá/tên → tạo `Order`+`OrderItem`, hạn giữ 15'. Giữ vé và tạo đơn commit/rollback cùng nhau (QĐ-3.11). Bắt `NotEnoughTickets` → lỗi form. | `Catalog\Contracts` |
| `Actions/ConfirmOrderPaid.php` | Khoá đơn, kiểm idempotent, đổi `status=paid`, phát hành mỗi vé một token ULID, phát `OrderPaid`. Nếu đơn đã quá hạn → cho hết hạn + trả vé. | `Catalog\Contracts`, `OrderPaid` |
| `Actions/CancelOrder.php` | Người dùng hủy đơn còn pending → `status=cancelled` + `CatalogApi->releaseTickets` trong một transaction. | `Catalog\Contracts` |
| `Actions/ExpireStaleOrders.php` | Quét đơn pending quá 15' → mỗi đơn một transaction: `status=expired` + trả vé. Khoá lại kiểm tra để không đụng webhook. | `Catalog\Contracts` |
| `Actions/CheckInTicket.php` | Khoá vé theo token, trả `Valid`/`Used`/`Nonexistent`, đánh dấu `used` nếu hợp lệ. Chặn quét hai lần đồng thời (YC-11.3). | — |
| `Listeners/HandlePaymentSucceeded.php` | Nghe `Payment\Contracts\PaymentSucceeded` → gọi `ConfirmOrderPaid`. | `Payment\Contracts` |
| `Listeners/SendOrderConfirmation.php` | Nghe `OrderPaid` (nội bộ) → gửi mail vé + QR. `ShouldQueue`, `afterCommit=true`. Lấy thông tin sự kiện qua `CatalogApi`. | `Catalog\Contracts` |
| `Console/ExpireStaleOrdersCommand.php` | Lối vào CLI `orders:expire` → gọi `ExpireStaleOrders`. Lên lịch mỗi phút trong `routes/console.php`. | — |
| `Http/OrderController.php` | `store`: tạo đơn (`PlaceOrder`) rồi tạo phiên Stripe (`PaymentApi`). `show`/`cancel`. Controller chỉ điều phối (QĐ-2.4). | `Catalog\Contracts`, `Payment\Contracts` |
| `Http/TicketController.php`, `StoreOrderRequest.php`, `routes.php` | Danh sách vé của tôi; validate đơn; route. | — |
| `Policies/OrderPolicy.php`, `TicketPolicy.php` | Người dùng chỉ xem đơn/vé của chính mình. | `App\Models\User` |
| `Models/Order.php`, `OrderItem.php`, `Ticket.php` | Eloquent nội bộ. `event_id`/`ticket_type_id` chỉ là ID tham chiếu — **không** relationship chéo module (QĐ-3.7). Quan hệ về `User` được phép (QĐ-3.9). | `App\Models\User` |
| `Mail/OrderConfirmationMail.php` | Mailable email xác nhận, nhận `Order` + `EventInfo`. | `Catalog\Contracts\EventInfo` |
| `Providers/TicketingServiceProvider.php` | Bind `TicketingApi`; nạp route/migration/view; đăng ký policy; `Event::listen(PaymentSucceeded→Handle…, OrderPaid→SendOrderConfirmation)`; đăng ký command. | `Payment\Contracts\PaymentSucceeded` |

### 4.3. Module Payment — cô lập mọi thứ Stripe

| File | Nhiệm vụ | Gọi ra ngoài |
| --- | --- | --- |
| `Contracts/PaymentApi.php` | Cửa ngõ: `createCheckoutSession(data): ?url`. Trả `null` khi chưa cấu hình `STRIPE_SECRET` (dev/test bỏ qua Stripe). | — |
| `Contracts/PaymentSucceeded.php` | **Event chéo module**: Stripe đã xác nhận trả tiền. Payload scalar (`orderId`, `paymentIntent`). `ShouldDispatchAfterCommit`. | — |
| `Contracts/CheckoutSessionData.php`, `CheckoutLineItem.php` | DTO đầu vào tạo phiên checkout (Ticketing dựng, Payment nhận). | — |
| `PaymentApiImpl.php` | Hiện thực `PaymentApi`; ủy quyền cho `CreateCheckoutSession`. | — |
| `Actions/CreateCheckoutSession.php` | Gọi Stripe SDK tạo phiên (JPY, số tiền = tổng đơn), ghi bản ghi `Payment` pending để webhook đối chiếu. | Stripe SDK |
| `Actions/ConfirmStripePayment.php` | Ghi nhận webhook: khoá `Payment`, nếu đã succeeded → no-op (idempotent), cập nhật, rồi phát `PaymentSucceeded` sau commit. | `PaymentSucceeded` |
| `Http/StripeWebhookController.php` | Endpoint webhook: kiểm chữ ký (việc HTTP), lọc `checkout.session.completed`, giao cho `ConfirmStripePayment`. | Stripe SDK |
| `Data/PaymentConfirmationData.php` | DTO nội bộ dựng từ payload webhook. | — |
| `Models/Payment.php` | Eloquent nội bộ. `order_id` chỉ là ID tham chiếu sang Ticketing (không FK, QĐ-3.7). | — |
| `Providers/PaymentServiceProvider.php` | Bind `PaymentApi`; nạp route/migration. | — |

### 4.4. Module CheckIn — chỉ là UI soát vé

| File | Nhiệm vụ | Gọi ra ngoài |
| --- | --- | --- |
| `Http/CheckInController.php` | Màn hình nhập token → `TicketingApi->checkIn` → hiển thị kết quả. Không đụng bảng nào. | `Ticketing\Contracts` |
| `Http/CheckInRequest.php`, `routes.php` | Validate token; route bọc `can:check-in` (chỉ vai trò `scanner`). | — |
| `Providers/CheckInServiceProvider.php` | Nạp route/view. Không bind API (module không có Public API). | — |

### 4.5. Hạ tầng dùng chung — `app/`

| File | Nhiệm vụ |
| --- | --- |
| `app/Models/User.php` | Authentication + `role` (`user`/`scanner`), `isScanner()`. Là hạ tầng, **không** thuộc module nào (QĐ-3.9); mọi module được FK/quan hệ về nó. |
| `app/Providers/AppServiceProvider.php` | `Gate::define('check-in', …isScanner())` (YC-4.2). |
| `app/Http/Controllers/Auth/*` | Đăng ký/đăng nhập/đặt lại mật khẩu — hạ tầng auth. |
| `bootstrap/providers.php` | Đăng ký ServiceProvider của 4 module + App. |
| `routes/console.php` | Lên lịch `orders:expire` mỗi phút. |

## 5. Bốn luồng chạy chính

Đọc kèm §4 để thấy quy tắc gọi nhau vận hành thực tế. Mũi tên `⇒` là gọi Public
API trực tiếp; `⤳` là event sau commit.

### 5.1. Đặt vé (mua)

```text
OrderController.store
  ⇒ CatalogApi.eventInfo                 (kiểm event đã publish)
  ⇒ PlaceOrder.handle                    [TRANSACTION mở]
       ⇒ CatalogApi.reserveTickets        (khoá kho, +reserved, trả TicketTypeInfo)
         → tạo Order + OrderItem (chốt giá/tên)   [TRANSACTION commit]
  ⇒ PaymentApi.createCheckoutSession      (tạo phiên Stripe + Payment pending)
  → redirect sang Stripe
```

Điểm mấu chốt: `reserveTickets` chạy **trong** transaction của `PlaceOrder` —
giữ vé và tạo đơn nguyên tử (QĐ-3.11). Vé chưa bị trừ vĩnh viễn, chỉ "đang
giữ".

### 5.2. Xác nhận thanh toán → phát hành vé

```text
Stripe → StripeWebhookController.handle   (kiểm chữ ký)
  ⇒ ConfirmStripePayment.handle           [TX] khoá Payment, idempotent
       ⤳ PaymentSucceeded (sau commit)
HandlePaymentSucceeded  (Ticketing nghe)
  ⇒ ConfirmOrderPaid.handle               [TX] khoá Order, đổi paid, phát hành Ticket
       ⤳ OrderPaid (sau commit)
   ├─ CommitTicketSalesOnOrderPaid (Catalog nghe) ⇒ CommitTicketSales  (reserved→sold)
   └─ SendOrderConfirmation        (Ticketing nghe, queued) ⇒ gửi mail + QR
```

Ba tầng idempotent chồng nhau (YC-9.3): `Payment` (bản ghi succeeded), `Order`
(kiểm `isPending`), và event chỉ chạy sau commit. Webhook lặp lại không phát
hành vé hai lần.

### 5.3. Hết hạn đơn (scheduler mỗi phút)

```text
Schedule orders:expire → ExpireStaleOrdersCommand
  ⇒ ExpireStaleOrders.handle
       mỗi đơn pending quá 15': [TX] khoá lại, status=expired
         ⇒ CatalogApi.releaseTickets  (trả vé đã giữ)
```

### 5.4. Soát vé (nhân viên)

```text
CheckInController.store  (route: can:check-in → chỉ scanner)
  ⇒ TicketingApi.checkIn(token)
       ⇒ CheckInTicket.handle  [TX] khoá vé → Valid/Used/Nonexistent
       → map Ticket sang TicketSummary (tên sự kiện qua CatalogApi)
  → hiển thị kết quả
```

Vì cần **kết quả** (hợp lệ/đã dùng), đây là gọi API trực tiếp chứ không phải
event (QĐ-3.5, cấm C4).

## 6. Checklist review nhanh cho mức 3

| # | Câu hỏi | Kết luận nếu "có" |
| --- | --- | --- |
| 1 | File này `use` một Model của module khác không? | ❌ Vi phạm C1 (QĐ-3.4) — đổi sang gọi Public API. |
| 2 | Có relationship/FK/JOIN sang bảng module khác? | ❌ Vi phạm C2 (QĐ-3.7) — dùng ID + Public API. |
| 3 | Event chéo module có mang Eloquent Model trong payload? | ❌ Vi phạm C3 — chỉ scalar/DTO. |
| 4 | Dùng event nhưng lại cần giá trị trả về? | ❌ Vi phạm C4 — gọi API trực tiếp. |
| 5 | Event chéo module có `ShouldDispatchAfterCommit`? | ✅ Bắt buộc (QĐ-3.12). |
| 6 | Public API ghi dữ liệu có tự commit / gửi mail / gọi HTTP bên trong? | ❌ Vi phạm QĐ-3.11 — đẩy side-effect qua event. |
| 7 | Public API có trả DTO (không phải Model)? | ✅ Đúng (QĐ-3.4). |
| 8 | Class mới có nằm đúng module nghiệp vụ, không phải `Core`/`Helpers`? | ✅ Đúng (QĐ-3.1). |
| 9 | `composer deptrac` xanh? | ✅ Bắt buộc trước merge (QĐ-3.6). |

> Nhắc lại QĐ-0.3: mức áp cho **từng module**. Trong cùng codebase này, một
> module có thể nâng lên mức 4 (Domain layer tách khỏi Eloquent) trong khi ba
> module còn lại vẫn ở mức 3 — xem [database-design.md §4.4](database-design.md).
