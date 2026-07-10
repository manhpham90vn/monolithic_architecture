# Quy chuẩn Kiến trúc Monolith — Laravel

| | |
| --- | --- |
| **Mã tài liệu** | ARCH-MONO-01 |
| **Phiên bản** | 1.1 |
| **Ngày ban hành** | 2026-07-08 |
| **Phạm vi áp dụng** | Mọi dự án Laravel triển khai dạng monolith |
| **Đối tượng** | Developer, reviewer, tech lead |

---

## 1. Mục đích và phạm vi

Tài liệu này quy định **bốn mức kiến trúc** cho ứng dụng Laravel monolith, tiêu chí chọn mức khởi điểm, và tín hiệu khách quan để nâng mức. Mục tiêu: mọi quyết định cấu trúc trong code review đều trích dẫn được về một quy định có mã số trong tài liệu này, thay vì tranh luận theo khẩu vị cá nhân.

Tài liệu **không** áp dụng cho: hệ thống đã tách microservice, package/library độc lập, và các dự án không dùng Laravel.

Nguyên tắc xuyên suốt: **kiến trúc là chi phí phải mua đúng lúc, không phải mặc định phải có.** Mức cao hơn không "chuẩn hơn" mức thấp — mức đúng là mức thấp nhất chưa phát sinh tín hiệu nâng cấp.

## 2. Từ khóa quy phạm

Các từ khóa sau được dùng theo tinh thần RFC 2119:

| Từ khóa | Ý nghĩa |
| --- | --- |
| **BẮT BUỘC** | Không có ngoại lệ. Vi phạm là lỗi phải sửa trước khi merge. |
| **KHÔNG ĐƯỢC** | Cấm tuyệt đối. Vi phạm là lỗi phải sửa trước khi merge. |
| **NÊN / KHÔNG NÊN** | Mặc định phải theo. Làm khác được nếu có lý do chính đáng, ghi rõ trong PR. |
| **CÓ THỂ** | Tùy chọn, để ngỏ cho team quyết. |

Quy định được đánh số theo dạng `QĐ-<nhóm>.<số>` để trích dẫn trong code review (ví dụ: *"vi phạm QĐ-3.4"*). Nhóm 1–4 tương ứng bốn mức kiến trúc; hai nhóm đặc biệt: **nhóm 0** là quy định về chọn mức và nâng mức (§3), **nhóm 8** là nguyên tắc chung áp dụng mọi mức (§8).

## 3. Tổng quan bốn mức

| Mức | Tên | Dùng cho | Đơn vị tổ chức code |
| --- | --- | --- | --- |
| 1 | CRUD thuần | Blog, landing, admin nội bộ | Controller + Model mặc định của Laravel |
| 2 | Có nghiệp vụ | E-commerce nhỏ, SaaS giai đoạn đầu | Action + DTO + Event |
| 3 | Modular monolith | Dự án lớn, nhiều mảng nghiệp vụ, nhiều dev | Module theo nghiệp vụ |
| 4 | DDD chiến thuật | Domain thật sự phức tạp (bảo hiểm, ngân hàng, logistics) | Domain layer cách ly khỏi framework |

**QĐ-0.1** — Dự án mới **NÊN** khởi điểm ở mức 1 hoặc 2. **CÓ THỂ** khởi điểm thẳng ở mức 3 khi thỏa **cả hai** điều kiện, ghi rõ trong design doc: (a) ranh giới nghiệp vụ đã được kiểm chứng — rebuild hệ thống đang chạy, hoặc domain đã được chuyên gia nghiệp vụ xác nhận, không phải phỏng đoán; (b) team đủ đông (≥ 5 dev) để chi phí ranh giới module trả được ngay từ đầu. **KHÔNG ĐƯỢC** khởi điểm ở mức 4 trong mọi trường hợp — bắt đầu ở mức 4 cho một MVP là cách chắc chắn nhất để không bao giờ ra được MVP.

**QĐ-0.2** — Việc nâng mức **BẮT BUỘC** đi tuần tự 1 → 2 → 3 → 4, mỗi bước là refactor cơ học, không phải viết lại.

**QĐ-0.3** — Mức áp cho **từng ranh giới nghiệp vụ**, không phải cả hệ thống. Trang landing ở mức 1, `Ordering` ở mức 3, `Billing` ở mức 4 — trong cùng một codebase — là kiến trúc đúng, không phải thiếu nhất quán.

---

## 4. Mức 1 — CRUD thuần (nhóm QĐ-1)

### 4.1. Phạm vi

Ứng dụng mà nghiệp vụ về cơ bản là "lưu form vào DB rồi hiển thị lại": blog, landing page, trang admin nội bộ.

### 4.2. Cấu trúc chuẩn

Giữ nguyên Laravel mặc định:

```php
app/
  Http/Controllers/PostController.php
  Http/Requests/StorePostRequest.php
  Models/Post.php
  Policies/PostPolicy.php
```

### 4.3. Quy định

**QĐ-1.1** — Controller **NÊN** gọi thẳng Eloquent. Validation **BẮT BUỘC** nằm trong Form Request. Phân quyền **BẮT BUỘC** nằm trong Policy.

**QĐ-1.2** — Logic đơn giản (scope, accessor, relationship) **NÊN** nằm trong Model.

**QĐ-1.3** — **KHÔNG ĐƯỢC** thêm Service layer, Action, Repository ở mức này. Một class chỉ để chuyển tiếp `Post::create($data)` là over-engineering: thêm chỗ để đọc, không thêm giá trị.

### 4.4. Tín hiệu nâng lên mức 2

Xuất hiện **một trong các** dấu hiệu sau:

- [ ] Một action ghi dữ liệu phải làm nhiều việc (tạo record + gửi mail + trừ tồn kho).
- [ ] Cùng một logic bị copy giữa Controller web và Controller API.
- [ ] Controller method vượt quá ~30 dòng vì chứa nghiệp vụ.

---

## 5. Mức 2 — Có nghiệp vụ (nhóm QĐ-2)

### 5.1. Phạm vi

Ứng dụng có quy trình nghiệp vụ thật (đặt hàng, thanh toán, quản lý tồn kho) nhưng còn trong một mảng, quy mô team nhỏ. Mục tiêu của mức này: tách phần **"làm gì"** ra khỏi phần **"nhận HTTP"**.

### 5.2. Cấu trúc chuẩn

```php
app/
  Actions/Order/PlaceOrder.php
  Actions/Order/CancelOrder.php
  Data/OrderData.php          // DTO
  Http/Controllers/OrderController.php
  Models/Order.php
  Events/OrderPlaced.php
  Listeners/SendOrderConfirmation.php
```

### 5.3. Quy định

**QĐ-2.1** — Nghiệp vụ **BẮT BUỘC** đóng gói thành **Action**: một class = một hành vi, có duy nhất method `handle()`.

**QĐ-2.2** — **KHÔNG NÊN** dùng Service class gom nhiều method (`OrderService`). Lý do: Service có xu hướng phình thành class 800 dòng — chỉ là đổi tên "fat model" thành "fat service". Action tự nhiên nhỏ, dễ test, dễ đọc tên.

**QĐ-2.3** — Dữ liệu truyền giữa các tầng **BẮT BUỘC** dùng DTO (khuyến nghị `spatie/laravel-data`). **KHÔNG ĐƯỢC** truyền `array $data` mù mờ.

**QĐ-2.4** — Controller chỉ làm ba việc: nhận request đã validate, gọi Action, trả response:

```php
public function store(StoreOrderRequest $request, PlaceOrder $placeOrder)
{
    $order = $placeOrder->handle(OrderData::from($request));
    return new OrderResource($order);
}
```

**QĐ-2.5** — Việc phụ (gửi mail, ghi log, đồng bộ ERP) **BẮT BUỘC** đẩy qua Event/Listener hoặc Job. Action chỉ lo nghiệp vụ chính.

**QĐ-2.6** — **KHÔNG ĐƯỢC** thêm Repository ở mức này. Eloquent đã là Active Record kèm abstraction sẵn. Bọc thêm `OrderRepositoryInterface` chỉ để gọi `Order::find()` là indirection vô nghĩa — bạn sẽ không bao giờ đổi Eloquent sang Doctrine. Repository chỉ có lý do tồn tại ở mức 4 (xem QĐ-4.2), hoặc khi phải che giấu nguồn dữ liệu hỗn hợp (DB + API bên thứ ba + cache).

### 5.4. Tín hiệu nâng lên mức 3

- [ ] `app/Actions` có ~60 file trộn lẫn Order, Inventory, Billing, User.
- [ ] Mở project không biết bắt đầu đọc từ đâu.
- [ ] Hai dev thường xuyên sửa cùng file vì "nó liên quan tới nhau".
- [ ] Sửa một tính năng phải mở nhiều thư mục kỹ thuật (Actions, Models, Events, Listeners) rải rác.

---

## 6. Mức 3 — Modular monolith (nhóm QĐ-3)

### 6.1. Phạm vi

Điểm ngọt cho hầu hết dự án lớn — **đa số dự án nên dừng ở đây**. Vẫn một codebase, một deploy, một database; nhưng chia theo **nghiệp vụ**, không chia theo **kỹ thuật**.

Định vị: mức 3 **chính là mức 2 đặt trong ranh giới module**. Vẫn Eloquent, vẫn Action, vẫn Event — không thêm tầng trừu tượng mới. Thứ duy nhất thay đổi là ranh giới: mỗi module là một "ứng dụng Laravel con" tự chứa mọi thứ của nó.

### 6.2. Cấu trúc chuẩn

```php
src/
  Ordering/
    Contracts/                     # public surface DUY NHẤT của module (QĐ-3.3)
      OrderingApi.php              #   interface cửa ngõ
      OrderSummary.php             #   DTO trả ra cho module khác
      OrderPlaced.php              #   event công bố cho module khác (QĐ-3.5)
    Actions/PlaceOrder.php
    Actions/CancelOrder.php
    Data/OrderData.php             # DTO nội bộ module
    Models/Order.php               # vẫn extends Eloquent Model
    Http/OrderController.php
    Http/StoreOrderRequest.php
    Events/                        # event nội bộ module (nếu có)
    OrderingApiImpl.php            # implementation của Contracts\OrderingApi — internal
    Database/Migrations/
    Providers/OrderingServiceProvider.php
  Inventory/
    ...
  Billing/
    ...
app/            # còn lại: kernel, bootstrap, auth
```

Lưu ý: cấu trúc này **cố tình không có** `Domain/`, `Infrastructure/`, Repository — đó là chuyện của mức 4. Nhét chúng vào mức 3 là trả giá của mức 4 mà không nhận được gì.

Autoload trong `composer.json`:

```json
"autoload": {
  "psr-4": {
    "App\\": "app/",
    "Ordering\\": "src/Ordering/",
    "Inventory\\": "src/Inventory/",
    "Billing\\": "src/Billing/"
  }
}
```

### 6.3. Quy định — tổ chức module

**QĐ-3.1** — Module **BẮT BUỘC** chia theo nghiệp vụ và đặt tên theo ngôn ngữ của người dùng nghiệp vụ ("đơn hàng", "kho", "công nợ" → `Ordering`, `Inventory`, `Billing`). Tên module là `Core`, `Common`, `Helpers` là dấu hiệu chưa hiểu domain — **KHÔNG ĐƯỢC** dùng. (Hai ngoại lệ được phép mang tên kỹ thuật: `SharedKernel` — xem QĐ-3.10, và `Reporting` — xem QĐ-3.8.)

**QĐ-3.2** — Mỗi module **BẮT BUỘC** có ServiceProvider riêng, tự đăng ký route/migration và binding Public API của nó (module có view/translation thì đăng ký thêm bằng `loadViewsFrom`/`loadTranslationsFrom` tương tự):

```php
namespace Ordering\Providers;

use Illuminate\Support\ServiceProvider;
use Ordering\Contracts\OrderingApi;
use Ordering\OrderingApiImpl;

class OrderingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrderingApi::class, OrderingApiImpl::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
```

### 6.4. Quy định — giao tiếp giữa module

Đây là chỗ 90% dự án làm hỏng. Nếu `Ordering` gọi thẳng `Inventory\Models\Product::find()`, kết quả là một monolith rối rắm với thư mục đẹp, không hơn.

**QĐ-3.3** — Public surface của module **BẮT BUỘC** gói gọn trong namespace `Contracts\` của nó, gồm đúng ba loại: (1) **một** interface API làm cửa ngõ, (2) các DTO mà API nhận/trả, (3) các event công bố cho module khác (QĐ-3.5). Mọi thứ ngoài `Contracts\` coi như `internal`:

```php
namespace Inventory\Contracts;

interface InventoryApi
{
    public function productInfo(int $productId): ProductInfo;   // trả DTO nằm cùng Contracts

    /** @param int[] $productIds — bản batch: cần N sản phẩm thì gọi 1 lần, không lặp N lần */
    public function productInfos(array $productIds): array;

    public function reserve(int $productId, int $qty): void;    // throw nếu hết hàng
}
```

Implementation (`InventoryApiImpl`) nằm ngoài `Contracts\`, bind trong ServiceProvider (QĐ-3.2). Module khác inject `Inventory\Contracts\InventoryApi`, **KHÔNG ĐƯỢC** chạm vào `Inventory\Models\*` hay bất kỳ class nào ngoài `Contracts\`.

**QĐ-3.4** — **KHÔNG ĐƯỢC** share Eloquent Model giữa các module. Public API trả DTO hoặc ID, không trả model. Trả model tức là trao cho module khác quyền `->update()`, `->delete()` và lazy-load cả object graph của module mình.

**QĐ-3.5** — Việc phụ, một chiều giữa các module **NÊN** dùng Domain Event: `Ordering` phát `OrderPlaced`, `Billing` lắng nghe để tạo hóa đơn. Coupling gần bằng 0, đổi lại khó debug flow — chỉ dùng cho "chuyện đã xảy ra, module khác phản ứng"; **KHÔNG ĐƯỢC** dùng Event khi cần kết quả trả về.

Event công bố cho module khác **BẮT BUỘC** nằm trong `Contracts\` của module phát (QĐ-3.3), payload chỉ gồm scalar/DTO — **KHÔNG ĐƯỢC** nhét Eloquent Model vào event (hệ quả của QĐ-3.4). Event chỉ dùng nội bộ module thì để trong `Events/` như thường. Thời điểm xử lý event: xem QĐ-3.12.

**QĐ-3.6** — Ranh giới module **BẮT BUỘC** được ép bằng công cụ (`deptrac` hoặc `phparkitect`) chạy trong CI. Không có công cụ ép thì quy tắc sẽ bị vi phạm trong 3 tuần. Cấu hình mẫu: xem Phụ lục A.

### 6.5. Quy định — database

**QĐ-3.7** — Vẫn một database, nhưng mỗi module **BẮT BUỘC** chỉ đọc/ghi bảng của mình. **KHÔNG ĐƯỢC** JOIN chéo module, **KHÔNG ĐƯỢC** khai báo foreign key chéo module. Cần dữ liệu của module khác → gọi Public API của nó.

Kỷ luật này đau lúc đầu, nhưng chính nó khiến việc tách microservice sau này (nếu thật sự cần) là chuyện vài tuần thay vì một năm — ranh giới dữ liệu đã sạch sẵn, chỉ còn lo hạ tầng.

**QĐ-3.8** — Ngoại lệ duy nhất của QĐ-3.7: **báo cáo/dashboard**. Màn hình thống kê mà phải gọi API của 4 module rồi ghép tay là tự hành hạ mình. **CÓ THỂ** có một module `Reporting` được quyền **đọc** trên mọi bảng, với điều kiện: chỉ đọc, dùng query builder thuần, không dùng Model của module khác. `Reporting` là ngõ cụt về dependency — không module nào được phụ thuộc ngược vào nó.

### 6.6. Quy định — User và SharedKernel

**QĐ-3.9** — Câu hỏi đầu tiên mọi team sẽ vấp: `User` thuộc module nào? Quy định:

- Authentication (đăng nhập, session, token) **BẮT BUỘC** ở lại `app/` — nó là hạ tầng, không phải nghiệp vụ.
- Mỗi module tự có khái niệm riêng về con người đó: `Ordering` có `Customer`, `Billing` có `Payer` — chung `user_id`, khác bảng, khác model.
- Foreign key từ bảng của module về `users` **CÓ THỂ** khai báo bình thường: `users` thuộc hạ tầng ở `app/`, không phải module, nên đây không phải FK chéo module theo nghĩa của QĐ-3.7.
- **KHÔNG ĐƯỢC** tạo một model `User` chung rồi để nó phình 40 relationship từ mọi module.

**QĐ-3.10** — **CÓ THỂ** có một thư mục `SharedKernel/` cho Value Object thật sự dùng chung (`Money`, `Address`), với điều kiện: giữ thật nhỏ, chỉ chứa kiểu dữ liệu bất biến, **KHÔNG ĐƯỢC** chứa logic nghiệp vụ. Ngày `SharedKernel` có class tên `Helper` hay chứa một business rule là ngày kiến trúc bắt đầu mục.

### 6.7. Quy định — transaction và thời điểm phát event

**QĐ-3.11** — Một hành vi nghiệp vụ **CÓ THỂ** bọc lời gọi Public API của module khác trong cùng một DB transaction: `PlaceOrder` mở transaction, gọi `InventoryApi->reserve()`, lưu `Order` — tất cả commit hoặc rollback cùng nhau. Đây là đặc quyền của monolith một database; dùng nó thay vì mô phỏng saga/outbox của microservice. Đổi lại, method Public API có ghi dữ liệu **BẮT BUỘC** an toàn khi chạy bên trong transaction của module gọi: không tự commit giữa chừng, không tạo side-effect ngoài DB (gửi mail, gọi HTTP bên thứ ba) — side-effect đẩy qua event theo QĐ-3.12.

**QĐ-3.12** — Event chéo module **BẮT BUỘC** chỉ được xử lý sau khi transaction phát nó đã commit: event class implement `ShouldDispatchAfterCommit`, hoặc listener queued đặt `public $afterCommit = true;`. Lý do: nếu `Billing` tạo hóa đơn trong lúc transaction của `Ordering` chưa commit, một cú rollback để lại hóa đơn cho đơn hàng không tồn tại. Nếu kết quả của phản ứng **phải** rollback cùng hành vi gốc thì đó không phải "việc phụ" — gọi Public API trực tiếp trong transaction (QĐ-3.11), không dùng event (QĐ-3.5 đã cấm event khi cần kết quả).

### 6.8. Tín hiệu nâng lên mức 4

Xét **cho từng module**, không phải cả hệ thống (QĐ-0.3):

- [ ] Invariant nghiệp vụ bị vi phạm lặp đi lặp lại vì bất kỳ chỗ nào cũng có thể `$order->update(['status' => ...])`.
- [ ] Quy tắc nghiệp vụ thay đổi thường xuyên hơn schema database.
- [ ] Cần test hàng trăm tổ hợp quy tắc mà mỗi test phải boot Laravel + migrate DB.

Chỉ nâng module đau nhất — `Billing` ở mức 4 trong khi `Ordering` vẫn ở mức 3 là hoàn toàn bình thường.

---

## 7. Mức 4 — DDD chiến thuật (nhóm QĐ-4)

### 7.1. Phạm vi

Chỉ khi domain thật sự phức tạp: bảo hiểm, ngân hàng, logistics — loại nghiệp vụ mà bạn phải ngồi với chuyên gia để hiểu quy tắc. Khác biệt cốt lõi so với mức 3: Domain layer **không biết Laravel tồn tại**.

**Cảnh báo trước khi vào mức này:** rất ít dự án Laravel cần mức 4. Nếu domain về cơ bản là "lưu form vào DB rồi hiển thị lại", mức 4 sẽ giết năng suất. Nhiều team đã phải quay lại mức 3 sau 6 tháng (tín hiệu nhận biết: §7.5).

### 7.2. Cấu trúc chuẩn

```php
src/Ordering/
  Domain/
    Order.php              # POPO, không extends Model
    OrderId.php            # Value Object
    Money.php
    OrderRepository.php    # interface
    Exceptions/
  Application/
    PlaceOrderHandler.php
  Infrastructure/
    Persistence/EloquentOrderRepository.php
    Persistence/OrderEloquentModel.php   # chỉ để map DB
```

### 7.3. Quy định

**QĐ-4.1** — Domain layer **KHÔNG ĐƯỢC** import bất kỳ class nào của Laravel/Eloquent. Entity là POPO; trạng thái không hợp lệ phải không thể biểu diễn được (không thể tạo `Order` sai trạng thái).

**QĐ-4.2** — Đây là lúc Repository — thứ QĐ-2.6 cấm ở mức dưới — **mới có lý do tồn tại**: nó không còn là vỏ bọc quanh Eloquent mà là ranh giới thật giữa domain thuần và persistence. Interface **BẮT BUỘC** nằm trong `Domain/`, implementation nằm trong `Infrastructure/`.

**QĐ-4.3** — **BẮT BUỘC** viết mapper giữa entity domain (`Order`) và model persistence (`OrderEloquentModel`).

**QĐ-4.4** — Test quy tắc domain **BẮT BUỘC** chạy được không cần database, không cần boot Laravel.

### 7.4. Cái giá phải trả (ghi nhận trước khi quyết)

Mất Eloquent magic, mất `$order->update()`, mất factory trong test feature, viết nhiều code hơn 2–3 lần. Đổi lại: business rule được bảo vệ trong entity, test domain chạy tức thì, logic không rò rỉ ra Controller. Team **BẮT BUỘC** cân nhắc bảng giá này trong design review trước khi nâng bất kỳ module nào lên mức 4.

### 7.5. Tín hiệu hạ về mức 3

Xét cho từng module (QĐ-0.3). Nếu sau một thời gian vận hành xuất hiện các dấu hiệu sau, mức 4 đang không trả được chi phí của nó:

- [ ] Entity domain chỉ còn getter/setter — quy tắc nghiệp vụ thực tế vẫn nằm ở Application layer hoặc Controller.
- [ ] Mapper chỉ copy field 1-1, và phần lớn thay đổi là thêm/bớt cột: schema đổi nhanh hơn quy tắc nghiệp vụ (ngược với tín hiệu nâng mức ở §6.8).
- [ ] Thời gian phát triển một tính năng chủ yếu dành cho việc đi xuyên các tầng (entity + mapper + repository) thay vì viết nghiệp vụ.

Hạ mức là refactor cơ học theo chiều ngược lại: gộp entity về Eloquent Model, chuyển nghiệp vụ về Action — module trở lại đúng cấu trúc mức 3 (§6.2). Việc hạ mức **BẮT BUỘC** ghi rõ tín hiệu kèm theo trong design doc, giống như khi nâng.

---

## 8. Nguyên tắc chung (áp dụng mọi mức)

**QĐ-8.1 — Đi lên từng bước, không nhảy cóc.** Mỗi bước 1 → 2 → 3 là refactor cơ học, không phải viết lại (xem QĐ-0.2).

**QĐ-8.2 — Chia theo nghiệp vụ, không theo kỹ thuật.** `Ordering/` chứa mọi thứ về đặt hàng; không phải `Services/` chứa mọi service. Thước đo: sửa một tính năng, mở **một** thư mục — không phải bảy.

**QĐ-8.3 — Cấu trúc phản ánh ngôn ngữ người dùng.** Tên module = từ mà bên nghiệp vụ dùng hằng ngày (xem QĐ-3.1).

**QĐ-8.4 — Test là thước đo vị trí của logic.** Nếu để test một quy tắc nghiệp vụ phải boot cả Laravel + migrate DB + tạo 5 factory, logic đang nằm sai chỗ.

**QĐ-8.5 — Mỗi module một mức.** Nhắc lại QĐ-0.3: mức áp cho từng ranh giới nghiệp vụ, không phải cả codebase.

---

## 9. Checklist review nhanh

Dùng khi review PR có thay đổi cấu trúc:

| # | Câu hỏi | Quy định |
| --- | --- | --- |
| 1 | Có thêm tầng trừu tượng (Service/Repository/Action) khi chưa có tín hiệu? | QĐ-1.3, QĐ-2.6 |
| 2 | Nghiệp vụ có nằm trong Action với một method `handle()`? | QĐ-2.1 |
| 3 | Có truyền `array $data` giữa các tầng? | QĐ-2.3 |
| 4 | Việc phụ có bị nhét vào Action chính thay vì Event/Job? | QĐ-2.5 |
| 5 | Module có import Model/class internal của module khác? | QĐ-3.3, QĐ-3.4 |
| 6 | Có JOIN hoặc foreign key chéo module? | QĐ-3.7 |
| 7 | Có class mới trong `SharedKernel` chứa logic nghiệp vụ? | QĐ-3.10 |
| 8 | Domain layer (mức 4) có import Laravel? | QĐ-4.1 |
| 9 | Việc nâng mức có tín hiệu khách quan kèm theo trong mô tả PR? | §4.4, §5.4, §6.8 |
| 10 | Event chéo module có được xử lý sau khi transaction commit? | QĐ-3.12 |
| 11 | Method Public API có ghi dữ liệu có tự commit hoặc tạo side-effect ngoài DB (mail, HTTP)? | QĐ-3.11 |

---

## Phụ lục A — Cấu hình `deptrac` mẫu

Chặn module này import internal của module kia, chỉ cho phép đi qua `Contracts\` (QĐ-3.3, QĐ-3.6):

```yaml
deptrac:
  paths: [src]
  layers:
    - name: Ordering
      collectors: [{type: directory, value: src/Ordering/.*}]
    - name: InventoryContracts
      collectors: [{type: directory, value: src/Inventory/Contracts/.*}]
    - name: InventoryInternal
      collectors: [{type: directory, value: src/Inventory/(?!Contracts/).*}]
  ruleset:
    Ordering: [InventoryContracts]           # chỉ được đụng Contracts của Inventory
    InventoryInternal: [InventoryContracts]  # implementation được dùng interface/DTO của chính nó
    InventoryContracts: []                   # Contracts tự chứa, không được kéo internal ra ngoài
```

Mở rộng tương tự cho từng cặp module. Lưu ý: số layer và số dòng ruleset tăng theo số module (mỗi module 2 layer, mỗi quan hệ phụ thuộc 1 dòng), nên khi hệ thống có nhiều module, **NÊN** generate file cấu hình này bằng script từ danh sách module thay vì duy trì bằng tay. Chạy `deptrac analyse` trong CI; build đỏ khi có vi phạm.

## Phụ lục B — Lịch sử phiên bản

| Phiên bản | Ngày | Thay đổi |
| --- | --- | --- |
| 1.1 | 2026-07-08 | Đổi chỗ §6.6/§6.7 cho đúng thứ tự số quy định; sửa tham chiếu sai trong QĐ-3.1 (QĐ-3.9 → QĐ-3.10) và ghi nhận `Reporting` là ngoại lệ đặt tên; thêm mục 11 vào checklist (QĐ-3.11); thêm §7.5 — tín hiệu hạ mức 4 → 3; thêm nhãn nhóm QĐ vào heading các mức; chỉnh sửa biên tập nhỏ (QĐ-3.2, Phụ lục A). |
| 1.0 | 2026-07-08 | Ban hành lần đầu: bốn mức kiến trúc, quy định đánh số, checklist review. |
