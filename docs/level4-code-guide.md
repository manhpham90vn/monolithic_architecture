# Mức 4 — Giải thích từng file & quy tắc gọi nhau

| | |
| --- | --- |
| **Mã tài liệu** | ARCH-MONO-01-L4 |
| **Phiên bản** | 1.0 |
| **Ngày ban hành** | 2026-07-12 |
| **Phạm vi** | Ứng dụng mẫu `level4/` (DDD chiến thuật, domain bán vé) |
| **Tài liệu gốc** | [README.md](../README.md) §7 — nhóm quy định QĐ-4 |

---

## 1. Định vị mức 4

`level4` **giống hệt `level3`** ở ba module Catalog, Payment, CheckIn — chúng
vẫn ở mức 3. Chỉ **một module Ticketing** được nâng lên mức 4, minh hoạ QĐ-0.3
(*mức áp cho từng module, không phải cả hệ thống*). Vì vậy tài liệu này:

- **Chỉ giải thích phần khác**: tầng Domain / Application / Infrastructure của
  Ticketing.
- Với Catalog, Payment, CheckIn và các quy tắc chéo module (QĐ-3.x): đọc
  [level3-code-guide.md](level3-code-guide.md) — không lặp lại ở đây.

Khác biệt cốt lõi so với mức 3: **Domain layer của Ticketing không biết Laravel
tồn tại** (QĐ-4.1). Bảng `orders` không còn là "hình chiếu trực tiếp của domain"
mà tụt xuống thành chi tiết hạ tầng nằm sau `Repository` + `Mapper`.

Nguyên tắc một câu: **bất biến nghiệp vụ sống trong aggregate POPO; framework
(Eloquent, transaction, event) bị đẩy ra rìa và chỉ được chạm qua interface.**

## 2. Ba tầng của module Ticketing

```text
src/Ticketing/
  Domain/                 # POPO thuần — KHÔNG import Laravel (QĐ-4.1)
    Order/                #   aggregate Order + Value Object + interface
    Ticket/               #   aggregate Ticket
    Shared/Money.php      #   Value Object dùng chung trong module
    Exception/            #   lỗi nghiệp vụ (DomainException)
  Application/            # use-case: điều phối framework + gọi domain
  Infrastructure/         # hiện thực interface của Domain bằng Eloquent
    Persistence/          #   Repository impl + EloquentModel + Mapper
    UlidTokenGenerator.php
  Contracts/ Http/ Listeners/ Mail/ Policies/ Providers/  # như mức 3
```

Chiều phụ thuộc **một chiều vào trong**: `Http → Application → Domain`;
`Infrastructure → Domain`. Domain không trỏ ra ngoài cho ai.

## 3. Quy tắc gọi nhau — cái gì được, cái gì cấm

### 3.1. Chuỗi gọi chuẩn (đường ghi)

```text
Controller ⇒ Application handler
                 ├─ gọi Domain aggregate (Order::place, markPaid…)  ← quyết định nghiệp vụ
                 ├─ gọi OrderRepository (interface)                  ← lưu/nạp
                 └─ mở DB::transaction, gọi CatalogApi, dispatch Event ← điều phối framework
OrderRepository (impl ở Infrastructure) ⇒ Eloquent + OrderMapper    ← dịch aggregate ↔ bảng
```

### 3.2. Bị cấm (thêm so với mức 3)

| # | Cấm | Vì | QĐ |
| --- | --- | --- | --- |
| C1 | `use Illuminate\…` / `extends Model` trong bất kỳ file `Domain/`. | Domain phải chạy được không cần Laravel; test domain không boot framework. | QĐ-4.1, QĐ-4.4 |
| C2 | Đổi trạng thái đơn bằng `$model->update(['status' => …])` vòng qua aggregate. | Mọi chuyển trạng thái phải qua method có guard của aggregate (`markPaid`/`expire`/`cancel`). | QĐ-4.1 |
| C3 | Viết quy tắc nghiệp vụ (≤ 10 vé, máy trạng thái) trong Application/Controller. | Bất biến thuộc về entity; nếu rơi ra ngoài thì mức 4 mất lý do tồn tại (§7.5). | QĐ-4.1 |
| C4 | Domain gọi `now()`, `Str::ulid()` của Laravel. | Thời gian và token là chi tiết framework — truyền `DateTimeImmutable` vào, sinh token qua `TokenGenerator` interface. | QĐ-4.1, QĐ-4.2 |

### 3.3. Được phép (mới có ở mức 4)

| # | Được phép | Điều kiện | QĐ |
| --- | --- | --- | --- |
| A1 | Có `Repository` — thứ QĐ-2.6 cấm ở mức dưới. | Interface trong `Domain/`, impl trong `Infrastructure/`; là ranh giới thật, không phải vỏ bọc `find()`. | QĐ-4.2 |
| A2 | Viết `Mapper` copy field giữa entity và model. | Là chỗ **duy nhất** biết cả domain lẫn Eloquent (QĐ-4.3). | QĐ-4.3 |
| A3 | Có hai loại "Order": aggregate `Domain\Order\Order` và `OrderEloquentModel`. | Aggregate giữ bất biến; model chỉ map DB + đường đọc/route-binding. | QĐ-4.1, QĐ-4.3 |

## 4. Nhiệm vụ từng file (Ticketing)

### 4.1. `Domain/` — nghiệp vụ thuần, không Laravel

| File | Nhiệm vụ |
| --- | --- |
| `Domain/Order/Order.php` | **Aggregate root**. `place()` ép ≤ 10 vé (YC-8.1) + ≥ 1 vé; `markPaid()` guard máy trạng thái rồi sinh `IssuedTicket` (YC-10.1); `expire()`/`cancel()` guard; `reconstitute()` dựng lại từ DB (không chạy guard). "Trạng thái không hợp lệ không thể biểu diễn được". |
| `Domain/Order/OrderStatus.php` | Enum máy trạng thái §9; `isFinal()`. |
| `Domain/Order/OrderId.php` | Value Object định danh (số nguyên dương); đơn chưa lưu có `?OrderId = null`. |
| `Domain/Order/LineItem.php` | Value Object bất biến: chốt giá + tên hạng vé tại thời điểm tạo đơn (YC-8.5); `subtotal()`. |
| `Domain/Order/IssuedTicket.php` | Value Object vé vừa phát hành (token + hạng), do aggregate sinh trong `markPaid`. |
| `Domain/Order/OrderRepository.php` | **Interface** ranh giới domain ↔ persistence (QĐ-4.2): `save`, `find`, `findForUpdate` (khoá bi quan), `pendingExpiredIds`. |
| `Domain/Order/TokenGenerator.php` | **Interface** sinh token; Domain không biết ULID là gì. |
| `Domain/Shared/Money.php` | Value Object tiền = số nguyên yên (YC-2.2); tiền âm không biểu diễn được; `add`/`multiply`/`zero`. |
| `Domain/Ticket/Ticket.php` | Aggregate vé: `checkIn()` guard "đã dùng thì không soát lại" (YC-11.3). |
| `Domain/Ticket/TicketId.php`, `TicketStatus.php`, `TicketRepository.php` | Định danh / enum / interface repository của vé. |
| `Domain/Exception/OrderMustHaveItems.php` | Đơn phải có ≥ 1 dòng. |
| `Domain/Exception/TooManyTicketsPerOrder.php` | Trần 10 vé/đơn (YC-8.1). |
| `Domain/Exception/OrderNotPending.php` | Guard máy trạng thái: chỉ đơn pending mới chuyển được (§9) — nền cho idempotency. |
| `Domain/Exception/TicketAlreadyUsed.php` | Vé đã dùng không soát lại (YC-11.3). |

### 4.2. `Application/` — use-case điều phối

Mỗi handler: mở transaction, gọi `CatalogApi`/`PaymentApi`, giao **quyết định
nghiệp vụ** cho aggregate, rồi `Repository->save`. Đọc `now()` của framework rồi
truyền `DateTimeImmutable` vào domain.

| File | Nhiệm vụ | Gọi ra |
| --- | --- | --- |
| `Application/PlaceOrderHandler.php` | `CatalogApi->reserveTickets` → dựng `LineItem` (chốt giá) → `Order::place` (ép bất biến) → `orders->save`. Bắt `NotEnoughTickets` → lỗi form. | `Catalog\Contracts`, `OrderRepository` |
| `Application/ConfirmOrderPaidHandler.php` | `findForUpdate` → nếu quá hạn: `expire()`+trả vé; ngược lại `markPaid()`+`save`+phát `OrderPaid`. Idempotent qua guard aggregate (YC-9.3). | `Catalog\Contracts`, `OrderRepository`, `TokenGenerator`, `OrderPaid` |
| `Application/CheckInTicketHandler.php` | `findByTokenForUpdate` → `Ticket::checkIn()` → `save`; trả `CheckInOutcome`. | `TicketRepository` |
| `Application/ExpireStaleOrdersHandler.php` | `pendingExpiredIds` → mỗi đơn một TX: `findForUpdate`, kiểm lại, `expire()`+`save`+trả vé. | `Catalog\Contracts`, `OrderRepository` |
| `Application/CancelOrderHandler.php` | Nạp đơn dưới khoá, `cancel()`, `save`, `CatalogApi->releaseTickets`. | `Catalog\Contracts`, `OrderRepository` |

### 4.3. `Infrastructure/` — hiện thực bằng Eloquent

| File | Nhiệm vụ |
| --- | --- |
| `Infrastructure/Persistence/EloquentOrderRepository.php` | Hiện thực `OrderRepository` (QĐ-4.2): `insert`/`update` bảng `orders`/`order_items`, phát hành `tickets` (`firstOrCreate` theo token → nền idempotent), dịch model → domain qua `OrderMapper`. |
| `Infrastructure/Persistence/EloquentTicketRepository.php` | Hiện thực `TicketRepository` cho aggregate `Ticket`. |
| `Infrastructure/Persistence/OrderMapper.php` | **Mapper** (QĐ-4.3): chỗ duy nhất biết cả aggregate lẫn Eloquent; `toDomain()` dựng lại `Order` qua `reconstitute`. |
| `Infrastructure/Persistence/OrderEloquentModel.php` | Model **chỉ map bảng `orders`** — không chứa bất biến; lo đọc/ghi DB, route-binding, đường đọc (trang đơn). `event_id` là ID (không FK chéo module, QĐ-3.7). |
| `Infrastructure/Persistence/OrderItemEloquentModel.php`, `TicketEloquentModel.php` | Model map bảng `order_items` / `tickets`. |
| `Infrastructure/UlidTokenGenerator.php` | Hiện thực `TokenGenerator` bằng `Str::ulid()` — chi tiết framework nằm ở đây, không ở Domain. |

### 4.4. Nối dây & đường đọc

| File | Nhiệm vụ |
| --- | --- |
| `Providers/TicketingServiceProvider.php` | Ngoài việc như mức 3, **bind interface Domain → impl Infrastructure** (QĐ-4.2): `OrderRepository→Eloquent…`, `TicketRepository→…`, `TokenGenerator→Ulid…`. Policy/route-binding gắn với `OrderEloquentModel`. |
| `Http/OrderController.php` | Đường ghi (`store`/`cancel`) đi qua **Application handler** (nhận/ trả aggregate); đường đọc (`show`) nạp thẳng `OrderEloquentModel`. |
| `Contracts/*`, `TicketingApiImpl.php`, `Listeners/*`, `Mail/*` | Giống mức 3 (xem [level3-code-guide.md](level3-code-guide.md)). |

## 5. Luồng "đặt vé" — nơi phân tầng lộ rõ nhất

```text
OrderController.store
  ⇒ CatalogApi.eventInfo                       (kiểm publish)
  ⇒ PlaceOrderHandler.handle                   [TRANSACTION]
       ⇒ CatalogApi.reserveTickets              (khoá kho, trả TicketTypeInfo)
       → dựng LineItem (chốt giá — Value Object bất biến)
       → Order::place(...)                       ← DOMAIN: ép ≤10 vé, tạo aggregate pending
       ⇒ OrderRepository.save                    ← INFRA: EloquentOrderRepository + OrderMapper
  ⇒ PaymentApi.createCheckoutSession            (tạo phiên Stripe)
```

So với mức 3 (`PlaceOrder` action gọi `Order::create` thẳng): mức 4 chèn thêm
**aggregate** (ép bất biến) và **Repository+Mapper** (dịch sang bảng). Đó chính
là "cái giá phải trả" §7.4 — nhiều code hơn để đổi lấy business rule được bảo vệ
và test domain chạy không cần DB (QĐ-4.4).

## 6. Checklist review nhanh cho mức 4

| # | Câu hỏi | Kết luận nếu "có" |
| --- | --- | --- |
| 1 | File trong `Domain/` có `use Illuminate\…` hay `extends Model`? | ❌ Vi phạm C1 (QĐ-4.1). |
| 2 | Có đổi trạng thái bằng `->update(['status'=>…])` thay vì method aggregate? | ❌ Vi phạm C2. |
| 3 | Quy tắc nghiệp vụ (≤10 vé, máy trạng thái) có nằm trong aggregate? | ✅ Đúng (QĐ-4.1). |
| 4 | Repository interface có ở `Domain/`, impl ở `Infrastructure/`? | ✅ Đúng (QĐ-4.2). |
| 5 | Có Mapper giữa entity và model persistence? | ✅ Đúng (QĐ-4.3). |
| 6 | Test quy tắc domain có chạy được không cần boot Laravel + DB? | ✅ Bắt buộc (QĐ-4.4). |
| 7 | Entity chỉ còn getter/setter, quy tắc thực nằm ở Application? | ⚠️ Tín hiệu **hạ** về mức 3 (§7.5). |
| 8 | Quy tắc chéo module (Contracts/event/no-FK) còn giữ? | ✅ Ticketing vẫn là module mức 3 về đối ngoại (QĐ-3.x). |

> Ba module Catalog, Payment, CheckIn trong `level4` **không đổi** so với
> `level3`. Nếu review chạm chúng, dùng [level3-code-guide.md](level3-code-guide.md).
