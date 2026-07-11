# Phụ lục ánh xạ đa framework — Laravel / NestJS / Spring Boot

| | |
| --- | --- |
| **Mã tài liệu** | ARCH-MONO-01-APX-C |
| **Phiên bản** | 1.0 |
| **Ngày ban hành** | 2026-07-11 |
| **Phụ thuộc** | Đọc kèm [README.md](../README.md) (ARCH-MONO-01) — tài liệu này **không** thay thế, chỉ ánh xạ cơ chế |
| **Phạm vi áp dụng** | Dự án monolith viết bằng Laravel, NestJS, hoặc Spring Boot |

---

## 1. Cách dùng phụ lục này

Tài liệu gốc (README) chia làm hai lớp:

- **Lớp triết lý** — §3 (bốn mức), §8 (nguyên tắc chung), các QĐ-0.\*, và mọi "tín hiệu nâng/hạ mức" (§4.4, §5.4, §6.8, §7.5). Lớp này **bất khả tri framework**: áp dụng nguyên văn cho cả ba, không cần ánh xạ.
- **Lớp cơ chế** — cấu trúc thư mục, tên class, công cụ. Lớp này **phụ thuộc framework**: phụ lục này dịch nó sang NestJS và Spring Boot, lấy Laravel làm bản gốc.

Nguyên tắc: **mọi mã QĐ giữ nguyên ý nghĩa quy phạm.** "Vi phạm QĐ-3.4" nghĩa như nhau ở cả ba stack; chỉ *cách hiện thực và cách ép* là khác. Khi một QĐ đổi **lý do tồn tại** (không đổi ý nghĩa) vì khác kiểu ORM, phụ lục ghi rõ ở §6.

## 2. Trục quyết định công sức port: kiểu ORM

Điểm khác biệt lớn nhất giữa ba framework không phải cú pháp mà là **kiểu ORM**, vì nó đổi lập luận của QĐ-2.6, QĐ-3.4 và toàn bộ mức 4.

| Framework | ORM mặc định | Kiểu | Hệ quả |
| --- | --- | --- | --- |
| **Laravel** | Eloquent | **Active Record** | Model mang `->save()/->update()/->delete()` và cả object graph. QĐ-2.6/3.4 giữ **nguyên văn**. |
| **NestJS** | Prisma (khuyến nghị) | **Data Mapper** | Model là POJO/type thuần, không mang hành vi persistence. QĐ-2.6/3.4 **giữ ý nghĩa, đổi lập luận** (xem §6). |
| **Spring Boot** | JPA / Hibernate | **Data Mapper** (có sắc thái) | Entity là POJO nhưng "managed" trong persistence context. QĐ-2.6/3.4 đổi lập luận như NestJS, thêm lưu ý dirty-checking (§6). |

Kết luận: viết phần lập luận ORM **đúng hai lần** — một cho Active Record (Laravel), một dùng chung cho Data Mapper (NestJS + Spring). Mọi phần còn lại share.

## 3. Bảng ánh xạ khái niệm cốt lõi

| Khái niệm (QĐ) | Laravel | NestJS | Spring Boot |
| --- | --- | --- | --- |
| Validation đầu vào (QĐ-1.1) | Form Request | DTO class + `class-validator` + `ValidationPipe` | DTO/record + Bean Validation (`jakarta.validation`) + `@Valid` |
| Phân quyền (QĐ-1.1) | Policy | Guard (`CanActivate`) | Spring Security + `@PreAuthorize` |
| Đơn vị nghiệp vụ / Action (QĐ-2.1) | Action class, method `handle()` | Provider/UseCase, method `execute()` | `@Component` UseCase, hoặc Command + `@CommandHandler` (nếu dùng CQRS) |
| DTO truyền tầng (QĐ-2.3) | `spatie/laravel-data` | class + `class-validator` / `zod` | Java `record` / class |
| Event nội bộ + phụ (QĐ-2.5) | Event/Listener, Job | `EventEmitter2` (`@nestjs/event-emitter`) | `ApplicationEventPublisher` + `@EventListener` |
| Controller mỏng (QĐ-2.4) | Controller | `@Controller` | `@RestController` |
| Đơn vị module (QĐ-3.1) | Thư mục `src/<Module>/` + PSR-4 | Thư mục + `@Module` | Package + Spring Modulith "application module" |
| Đăng ký module (QĐ-3.2) | ServiceProvider | `@Module` (imports/providers/exports) | `@Configuration` + auto-config; ranh giới do package |
| Public API interface (QĐ-3.3) | interface + bind singleton | `interface` + provider token, **chỉ** đưa vào `exports` của module | `interface` trong package gốc module; các class khác `package-private` |
| Bind interface → impl (QĐ-3.2) | `$this->app->singleton(Api::class, Impl::class)` | provider token: `{ provide: 'INVENTORY_API', useClass: InventoryApiImpl }` | `@Component` implement interface, inject theo type |
| **Ép ranh giới (QĐ-3.6)** | `deptrac` / `phparkitect` | `dependency-cruiser` / `eslint-plugin-boundaries` / **Nx tags** | **Spring Modulith `verify()`** / **ArchUnit** |
| Event chéo module (QĐ-3.5) | Event trong `Contracts\` | Event class export từ module | Event trong package gốc module; Spring Modulith khuyến khích |
| After-commit (QĐ-3.12) | `ShouldDispatchAfterCommit` / `$afterCommit` | phát trong callback sau `$transaction`, hoặc `@nestjs/cqrs` outbox | `@TransactionalEventListener(phase = AFTER_COMMIT)` — **built-in** |
| Transaction bao Public API (QĐ-3.11) | `DB::transaction()`, connection tĩnh | Prisma interactive tx: **truyền `tx` client tường minh** | `@Transactional`, propagation qua proxy (ngầm) |
| Repository (chỉ mức 4, QĐ-4.2) | interface `Domain/` + Eloquent impl | interface + Prisma impl | interface `Domain/` + Spring Data / JPA impl |
| Domain thuần (QĐ-4.1) | POPO, không extends Model | class/POJO thuần, không import Prisma | POJO thuần, không import `jakarta.persistence` |

## 4. Ánh xạ cấu trúc thư mục theo mức

### Mức 1 — CRUD thuần

**Laravel** (README §4.2) giữ default. Tương đương:

```
# NestJS
src/post/
  post.controller.ts        # validate bằng DTO + ValidationPipe
  post.service.ts           # gọi thẳng Prisma
  dto/create-post.dto.ts
  post.module.ts

# Spring Boot
com.app.post/
  PostController.java        # @RestController, @Valid
  PostService.java           # gọi thẳng repository JPA
  Post.java                  # @Entity
  dto/CreatePostRequest.java # record + Bean Validation
```

QĐ-1.3 (không thêm Service/Repository thừa) giữ nguyên. Lưu ý NestJS/Spring **có sẵn** một lớp "service" theo quán tính framework — ở mức 1 nó chỉ nên là passthrough mỏng, đừng nhầm với Service layer mà QĐ-1.3 cấm.

### Mức 2 — Có nghiệp vụ

```
# NestJS
src/order/
  actions/place-order.usecase.ts     # 1 class, 1 method execute()
  actions/cancel-order.usecase.ts
  dto/order.dto.ts
  order.controller.ts
  events/order-placed.event.ts
  listeners/send-order-confirmation.listener.ts
  order.module.ts

# Spring Boot
com.app.order/
  PlaceOrder.java                    # @Component, 1 method
  CancelOrder.java
  OrderController.java
  OrderPlacedEvent.java
  SendOrderConfirmation.java         # @EventListener
```

QĐ-2.2 (không gom `OrderService` nhiều method) áp cho cả ba. Ở Spring, cám dỗ lớn nhất là `@Service class OrderService` 800 dòng — đây chính xác là thứ QĐ-2.2 cấm; tách thành các `@Component` một-hành-vi.

### Mức 3 — Modular monolith

```
# NestJS
src/ordering/
  contracts/                      # public surface DUY NHẤT (QĐ-3.3)
    ordering-api.interface.ts
    order-summary.dto.ts
    order-placed.event.ts
  actions/place-order.usecase.ts
  models/                         # Prisma models thuộc schema con
  ordering.controller.ts
  ordering-api.impl.ts            # internal
  ordering.module.ts              # exports: chỉ token OrderingApi
src/inventory/ ...

# Spring Boot (Spring Modulith)
com.app.ordering/                 # = 1 application module
  OrderingApi.java                # public interface — file duy nhất public
  OrderSummary.java               # public DTO
  OrderPlacedEvent.java           # public event
  internal/                       # Spring Modulith coi mặc định là nội bộ
    PlaceOrder.java
    Order.java                    # @Entity
    OrderingApiImpl.java
com.app.inventory/ ...
```

**Điểm mạnh riêng của Spring Modulith:** quy ước "mọi thứ trong sub-package `internal/` là riêng tư, chỉ class ở package gốc module mới public" **được `ModularityTests.verify()` ép tự động** — không cần khai báo layer thủ công như `deptrac`. Đây là lý do Spring khớp mức 3 rẻ nhất.

**NestJS** ép ranh giới yếu hơn ở tầng ngôn ngữ (TS không có package-private), nên **bắt buộc** dựa vào `dependency-cruiser`/Nx tags trong CI để cấm import chéo `contracts/`.

### Mức 4 — DDD chiến thuật

```
# NestJS
src/ordering/
  domain/order.ts                 # class thuần, không import Prisma
  domain/order-id.ts
  domain/order.repository.ts       # interface
  application/place-order.handler.ts
  infrastructure/prisma-order.repository.ts
  infrastructure/order.mapper.ts

# Spring Boot
com.app.ordering/
  domain/Order.java               # POJO, không @Entity, không jakarta.persistence
  domain/OrderId.java
  domain/OrderRepository.java      # interface
  application/PlaceOrderHandler.java
  infrastructure/JpaOrderRepository.java
  infrastructure/OrderJpaEntity.java   # @Entity, chỉ để map DB
  infrastructure/OrderMapper.java
```

QĐ-4.1/4.2/4.3/4.4 giữ nguyên nghĩa. Xem §6 cho sắc thái JPA.

## 5. Ép ranh giới (QĐ-3.6) — cấu hình mẫu từng framework

### NestJS — `dependency-cruiser`

```js
// .dependency-cruiser.js — cấm import chéo, chỉ cho qua contracts/
module.exports = {
  forbidden: [{
    name: 'no-cross-module-internal',
    severity: 'error',
    from: { path: '^src/([^/]+)/' },
    to: {
      path: '^src/([^/]+)/(?!contracts/)',
      pathNot: '^src/$1/'          // cùng module thì được
    }
  }]
};
// CI: depcruise src --config
```

Thay thế tương đương: **Nx** với `@nx/enforce-module-boundaries` + tags (`scope:ordering` chỉ được depend `scope:inventory` qua public entry) — mạnh hơn nếu đã dùng monorepo Nx.

### Spring Boot — Spring Modulith (khuyến nghị) hoặc ArchUnit

```java
// Spring Modulith: ép "internal là riêng tư" gần như zero-config
class ModularityTests {
    @Test
    void verifiesModuleBoundaries() {
        ApplicationModules.of(Application.class).verify();
    }
}
```

```java
// ArchUnit: khi cần luật tùy biến rõ ràng hơn
@ArchTest
static final ArchRule modules_only_touch_public_api =
    slices().matching("com.app.(*)..")
        .should().notDependOnEachOther()
        .ignoreDependency(alwaysTrue(),
            resideInAPackage("com.app.*"));   // chỉ package gốc module (public)
```

Chạy trong CI như một test thường; build đỏ khi vi phạm — đúng tinh thần QĐ-3.6.

### Laravel — `deptrac` (bản gốc, xem README Phụ lục A)

Không đổi.

## 6. Các QĐ đổi lập luận theo kiểu ORM

Ý nghĩa quy phạm **không đổi**; chỉ phần "vì sao" phải viết lại cho Data Mapper.

**QĐ-2.6 (không thêm Repository ở mức ≤ 3):**
- *Laravel (nguyên văn):* Eloquent đã là Active Record kèm abstraction; bọc `OrderRepositoryInterface` quanh `Order::find()` là indirection vô nghĩa.
- *NestJS/Spring (Data Mapper):* `PrismaClient` / Spring Data repository **đã chính là** lớp data-mapper sẵn có. Bọc thêm một repository của-riêng-mình chỉ để chuyển tiếp `prisma.order.findUnique` / `jpaRepo.findById` cũng là indirection thừa. → **Kết luận giống nhau, lập luận khác.** Repository chỉ có lý do ở mức 4 (QĐ-4.2), nơi nó là ranh giới domain↔persistence chứ không phải vỏ bọc ORM.

**QĐ-3.4 (không share model giữa module):**
- *Laravel:* trả Eloquent Model = trao quyền `->update()/->delete()` + lazy-load object graph. Rủi ro cao, cấm gắt.
- *NestJS (Prisma):* model là type thuần, **không** mang hành vi mutate, nên rủi ro "trao quyền ghi" thấp hơn. Mối nguy thật cần cấm là **module khác gọi thẳng `prisma` trên bảng không thuộc nó** (đây cũng là QĐ-3.7). Vẫn giữ QĐ-3.4: Public API trả **DTO của `contracts/`**, không trả Prisma model — để không rò rỉ schema nội bộ và không tạo coupling vào cột DB.
- *Spring (JPA):* **nguy hiểm hơn Prisma** — entity JPA "managed" có **dirty checking**: sửa một field trên entity đang trong persistence context sẽ tự flush xuống DB dù không gọi `save()`. Trả entity JPA ra ngoài module = đúng loại rủi ro Active Record. → QĐ-3.4 ở Spring áp **gắt như Laravel**: Public API trả DTO/record, tuyệt đối không trả `@Entity`.

**QĐ-3.11 (Public API an toàn trong transaction của module gọi):**
- *Laravel:* connection tĩnh, gọi lồng nhau tự chung transaction — "đặc quyền monolith một DB".
- *NestJS (Prisma):* transaction **không** truyền ngầm. Muốn `PlaceOrder` và `InventoryApi.reserve()` cùng một tx, phải **truyền `tx` client tường minh** qua chữ ký Public API: `reserve(tx, productId, qty)`. Ghi rõ trong `contracts/` rằng method ghi dữ liệu nhận `tx`.
- *Spring (JPA):* `@Transactional` propagation qua proxy lo việc này gần như ngầm — gọi Public API bên trong một `@Transactional` là đã chung transaction (propagation `REQUIRED`). Gần Laravel hơn Prisma. Vẫn giữ ràng buộc QĐ-3.11: method Public API ghi dữ liệu không tự commit, không side-effect ngoài DB.

**QĐ-3.12 (event chéo module xử lý sau commit):**
- *Laravel:* `ShouldDispatchAfterCommit` / `$afterCommit = true`.
- *NestJS:* `EventEmitter2` phát **đồng bộ** mặc định → phải chủ động phát event **sau khi** `$transaction` resolve, hoặc dùng outbox (`@nestjs/cqrs`). Đây là chỗ dễ sai nhất ở NestJS, ghi cảnh báo rõ.
- *Spring:* `@TransactionalEventListener(phase = AFTER_COMMIT)` là **built-in** — khớp QĐ-3.12 tốt nhất trong ba, gần như không phải nghĩ.

**Mức 4 (QĐ-4.1 domain không import framework):**
- *Laravel:* phải chủ động gỡ khỏi Eloquent (mất magic) — tốn công, đúng như README §7.4 cảnh báo.
- *NestJS/Spring (Data Mapper):* **rẻ hơn Laravel** — vì persistence vốn tách khỏi domain type, viết domain POJO + mapper là kiểu tự nhiên, không phải chống lại framework. QĐ-4.1 gần như miễn phí. Nhưng README §7.1 vẫn đúng: rẻ hơn *không* nghĩa là *nên* — chỉ lên mức 4 khi có tín hiệu §6.8.

## 7. Bảng tổng: QĐ nào giữ nguyên, QĐ nào cần chú thích

| Nhóm QĐ | Laravel | NestJS | Spring Boot |
| --- | --- | --- | --- |
| §3, §8, QĐ-0.\*, mọi tín hiệu nâng/hạ mức | Nguyên văn | Nguyên văn | Nguyên văn |
| QĐ-1.\* (validation/policy/model) | Nguyên văn | Đổi tên cơ chế (§3) | Đổi tên cơ chế (§3) |
| QĐ-2.1–2.5 | Nguyên văn | Đổi tên cơ chế | Đổi tên cơ chế; cảnh giác `@Service` phình (QĐ-2.2) |
| **QĐ-2.6** | Nguyên văn | **Đổi lập luận (Data Mapper)** | **Đổi lập luận (Data Mapper)** |
| QĐ-3.1–3.3 (module/public API) | Nguyên văn | Cần tooling ép (không có package-private) | **Spring Modulith ép sẵn — rẻ nhất** |
| **QĐ-3.4** | Nguyên văn | Đổi lập luận; vẫn trả DTO | **Gắt như Laravel** (dirty checking) |
| QĐ-3.5 | Nguyên văn | Nguyên văn | Nguyên văn |
| QĐ-3.6 (ép ranh giới) | `deptrac` | `dependency-cruiser`/Nx | Spring Modulith/ArchUnit |
| QĐ-3.7–3.10 (DB/User/SharedKernel) | Nguyên văn | Nguyên văn | Nguyên văn |
| **QĐ-3.11** | Nguyên văn | **Truyền `tx` tường minh** | Gần nguyên văn (`@Transactional`) |
| **QĐ-3.12** | Nguyên văn | **Phải tự phát sau commit** | **Built-in — khớp nhất** |
| QĐ-4.\* | Nguyên văn (tốn công gỡ ORM) | Rẻ hơn (Data Mapper) | Rẻ hơn (Data Mapper) |

## 8. Chốt chọn framework theo bối cảnh

- **Đã ở hệ PHP / muốn công sức port ít nhất:** Laravel — bản gốc, không dịch gì.
- **Hệ Node/TypeScript:** NestJS — khớp tốt, nhưng nhớ hai điểm dễ sai: ép ranh giới **phải** dựa tooling CI (QĐ-3.6), và event chéo module **phải** tự phát sau commit (QĐ-3.12).
- **Hệ JVM / domain phức tạp, nhiều module, cần kỷ luật ranh giới mạnh:** Spring Boot + **Spring Modulith** — khớp mức 3–4 **rẻ nhất** (ranh giới + after-commit đều built-in). Đừng dùng Spring Boot trần cho mức 3; thiếu Spring Modulith là thiếu đúng mảnh cần.

---

## Phụ lục — Lịch sử phiên bản

| Phiên bản | Ngày | Thay đổi |
| --- | --- | --- |
| 1.0 | 2026-07-11 | Ban hành: ánh xạ cơ chế của ARCH-MONO-01 sang NestJS và Spring Boot; bảng khái niệm, cấu trúc theo mức, cấu hình ép ranh giới, và các QĐ đổi lập luận theo kiểu ORM. |
