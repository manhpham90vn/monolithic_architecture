# Laravel dùng gì trong `level1/` — giải thích qua code thật

| | |
|---|---|
| **Mục đích** | Giải thích các thành phần Laravel trong `level1/`, mỗi khái niệm kèm code thật + cơ chế bên dưới. |
| **Đối tượng** | Người mới với Laravel, muốn hiểu "mảnh này là gì, chạy ra sao, **vì sao** hoạt động". |
| **Phạm vi** | Chỉ `level1/` (bản CRUD thuần — nghiệp vụ nằm thẳng trong Controller/Command). |

> Đọc theo thứ tự: §1 bức tranh tổng thể → §2–§4 là ba nền móng (Container, Middleware, Routing) → §5 trở đi là từng mảnh MVC. Các mục "quan trọng" (Model §7, Middleware §3, Container §2) được viết sâu; mục nhẹ giữ ngắn.

---

## 1. Vòng đời một request — đi qua đâu, ai gọi ai

Khi người dùng bấm "Mua vé" (`POST /events/5/orders`), đây là hành trình đầy đủ:

```
① public/index.php ───────────► điểm vào duy nhất của mọi request
② bootstrap/app.php ──────────► dựng Application + Service Container (§2)
③ Middleware toàn cục ────────► session, cookie, CSRF... (§3)
④ Router khớp route ──────────► /events/{event}/orders → OrderController::store (§4)
⑤ Middleware của route ───────► 'auth' — chưa đăng nhập thì đá về /login (§3)
⑥ Route Model Binding ────────► {event}=5 → tự nạp Event id 5 (§4)
⑦ StoreOrderRequest ──────────► tự validate; sai thì quay lại, controller KHÔNG chạy (§8)
⑧ OrderController::store() ───► nghiệp vụ: khoá kho, chốt giá, tạo đơn (§6, §11)
⑨      └─ Eloquent Model ─────► sinh SQL, đọc/ghi database (§7)
⑩ trả Response ───────────────► RedirectResponse → trình duyệt đi tiếp
      (đi ngược lại qua các middleware một lần nữa — xem §3)
```

**Điểm mấu chốt để hiểu Laravel:** bạn hiếm khi tự `new` một object. Bạn **khai báo mình cần gì** (bằng kiểu tham số), và Laravel **tự tạo và đưa vào**. Cơ chế làm được điều đó là Service Container (§2) — nên ta bắt đầu từ đó.

Toàn bộ được lắp ở `level1/bootstrap/app.php` — "bảng điện" của app:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',          // route web
        commands: __DIR__.'/../routes/console.php', // lệnh CLI + lịch chạy
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['stripe/webhook']); // §3
    })
    ->create();
```

---

## 2. Service Container & Dependency Injection — "phép thuật" cốt lõi

> Đây là khái niệm **quan trọng nhất mà người mới hay bỏ qua**. Hiểu nó thì mọi "tự nhiên có" ở phần sau hết bí ẩn.

### Vấn đề nó giải
Xem chữ ký hàm này trong `OrderController`:

```php
public function store(StoreOrderRequest $request, Event $event): RedirectResponse
```

Bạn **không hề** gọi `new StoreOrderRequest()` hay `Event::find(5)`. Vậy hai object đó ở đâu ra? → **Service Container tạo và tiêm vào cho bạn.** Đó là *Dependency Injection* (DI — tiêm phụ thuộc).

### Container là gì
Service Container là một **cuốn danh bạ + nhà máy** của Laravel. Nó biết:
- cách tạo ra một object khi ai đó cần (kể cả tạo luôn các phụ thuộc lồng nhau);
- những "binding" — quy tắc "khi cần interface X thì đưa class Y".

Khi Laravel chuẩn bị gọi `OrderController::store()`, nó **đọc kiểu của từng tham số** (qua Reflection) rồi tự dựng object tương ứng:
- `StoreOrderRequest $request` → tạo Form Request, nhồi dữ liệu request hiện tại, chạy validate.
- `Event $event` → thấy là Model + có `{event}` trên route → dùng **Route Model Binding** nạp đúng bản ghi (§4).

Cùng cơ chế này giải thích controller Mailable, Command… đều "tự có" thứ chúng khai báo.

### Trong dự án — hai nơi bạn *thấy* container làm việc

**(a) Tiêm qua constructor.** `OrderConfirmationMail` nhận `Order` qua constructor:
```php
public function __construct(public Order $order) {}
```
Khi `new OrderConfirmationMail($order)`, `$order` được giữ lại; container không cần can thiệp ở đây vì bạn tự truyền. Nhưng nếu constructor khai báo một *service* (ví dụ một client Stripe đã đăng ký), container sẽ tự dựng nó.

**(b) Đăng ký binding trong ServiceProvider.** `AppServiceProvider` là nơi bạn "dạy" container:
```php
public function register(): void { /* nơi bind: $this->app->bind(X::class, Y::class) */ }

public function boot(): void
{
    Gate::define('check-in', fn (User $user): bool => $user->isScanner());
}
```
- `register()` — chỉ để **đăng ký** binding vào container (không được dùng service khác ở đây vì có thể chưa sẵn sàng).
- `boot()` — chạy **sau khi mọi provider đã register xong**, nơi an toàn để cấu hình (định nghĩa Gate, observer, macro…).

> Ở mức 1 dự án gần như không tự bind gì (Eloquent + controller là đủ). Nhưng khi lên mức 2–4, container chính là chỗ bạn bind `interface → implementation` (ví dụ `OrderingApi` → `OrderingApiImpl`). Hiểu nó từ bây giờ để không bỡ ngỡ.

**Vì sao thiết kế vậy:** code chỉ *khai báo* phụ thuộc, không *tự tạo* — nên dễ thay thế (test có thể tiêm bản giả) và dễ đọc (nhìn chữ ký hàm biết ngay nó cần gì).

---

## 3. Middleware — các lớp lọc bao quanh request

> Phần này ở bản trước quá sơ sài. Đây là bản đầy đủ.

### Là gì
Middleware là **lớp code chạy trước/sau controller**, bọc quanh request như các lớp vỏ củ hành. Mỗi lớp có quyền: cho request đi tiếp, chặn lại (redirect/lỗi), hoặc chỉnh sửa request/response.

### Mô hình "củ hành" — chạy hai chiều
```
Request →  [ session ] → [ csrf ] → [ auth ] → Controller
Response ← [ session ] ← [ csrf ] ← [ auth ] ← Controller
```
Request đi **vào** qua từng lớp; response đi **ra** qua đúng các lớp đó theo chiều ngược. Nhờ vậy một middleware có thể làm việc *trước* (kiểm đăng nhập) lẫn *sau* controller (ví dụ gắn header, lưu session). Trong code một middleware trông như:

```php
public function handle(Request $request, Closure $next)
{
    // ... phần "trước": chạy TRƯỚC controller
    $response = $next($request);   // đẩy sang lớp trong / controller
    // ... phần "sau": chạy SAU controller, trước khi trả về
    return $response;
}
```
`$next($request)` chính là "đi vào lớp tiếp theo". Nếu một middleware **không** gọi `$next` mà trả thẳng redirect → request bị chặn, controller không bao giờ chạy (đây là cách `auth` đá khách về `/login`).

### Ba tầng áp middleware
1. **Toàn cục** — áp cho *mọi* request. Cấu hình ở `bootstrap/app.php`. Ví dụ session, cookie, và CSRF:
   ```php
   $middleware->validateCsrfTokens(except: ['stripe/webhook']);
   ```
   CSRF (Cross-Site Request Forgery) middleware bắt mọi form POST phải kèm token bí mật, chống trang lạ giả mạo request. **Nhưng** webhook Stripe do *máy chủ Stripe* gọi, không có token đó → phải miễn trừ đúng đường dẫn `stripe/webhook`, nếu không mọi webhook sẽ bị chặn 419.

2. **Theo nhóm/route** — chỉ áp cho route cụ thể, khai trong `routes/web.php`:
   ```php
   Route::middleware('guest')->group(function () { /* /login, /register */ });
   Route::middleware('auth')->group(function () { /* mua vé, vé của tôi */ });
   Route::middleware(['auth', 'can:check-in'])->group(function () { /* soát vé */ });
   ```

3. **Alias (tên gọi tắt)** — `auth`, `guest`, `can:...` là *bí danh* trỏ tới class middleware sẵn của framework. Dự án này **không tự viết middleware nào**; tất cả dùng đồ có sẵn:

| Alias | Class thật (framework) | Làm gì | Dùng cho |
|---|---|---|---|
| `auth` | `Authenticate` | Chưa đăng nhập → chuyển tới `/login` | mua vé, vé của tôi, soát vé |
| `guest` | `RedirectIfAuthenticated` | Đã đăng nhập → đá khỏi trang khách | login, register, quên mật khẩu |
| `can:check-in` | `Authorize` | Chạy Gate `check-in`; false → 403 | chỉ nhân viên soát vé (YC-4.2) |

### `auth` gắn với session thế nào
`auth` không tự biết bạn là ai — nó đọc **session**. Đăng nhập thành công, `AuthenticatedSessionController::store()` làm:

```php
$request->authenticate();          // kiểm mật khẩu (trong LoginRequest)
$request->session()->regenerate(); // cấp session id mới → chống session fixation
```

Từ đó session giữ dấu "user id = N". Mỗi request sau, middleware session khôi phục dữ liệu này, `auth` thấy có user → cho qua, và `auth()->user()` trả về đúng người. Khi logout:

```php
Auth::guard('web')->logout();
$request->session()->invalidate();     // xoá sạch session
$request->session()->regenerateToken(); // token CSRF mới
```

### Vì sao quan trọng
Middleware là nơi gom **mối quan tâm cắt ngang** (đăng nhập, quyền, CSRF, session) ra khỏi controller. Nhờ đó `OrderController::store()` chỉ cần lo nghiệp vụ mua vé — nó *biết chắc* tới được đây thì user đã đăng nhập rồi, vì `auth` đã lọc ở vòng ngoài.

---

## 4. Routing & Route Model Binding — URL → code

### Là gì
Route khai báo "method HTTP + đường dẫn → gọi hàm nào". Toàn bộ trong `routes/web.php`.

### Trong dự án
```php
Route::get('/', [EventController::class, 'index'])->name('events.index');       // công khai
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');

Route::middleware('auth')->group(function (): void {
    Route::post('/events/{event}/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});
```

### Ba cơ chế cần hiểu sâu

**(a) Route parameter `{event}` + Route Model Binding.**
`{event}` là chỗ trống nhận giá trị từ URL. Vì controller khai báo tham số **cùng tên và kiểu Model** (`Event $event`), Laravel tự chạy `Event::findOrFail($idTừUrl)` và tiêm object vào. `/events/5` → `$event` là bản ghi id 5; không có → tự trả **404**, bạn không phải viết dòng nào.

Đây là DI (§2) kết hợp router: container thấy kiểu là Eloquent Model + có param cùng tên trên route → dùng binding thay vì tự dựng object rỗng. Muốn bind theo cột khác (ví dụ slug thay vì id) thì khai `{event:slug}`.

**(b) Named route.** `->name('events.show')` cho route một cái tên. Code khác **không viết URL cứng**:
```php
route('events.show', $event)   // → "/events/5"
```
Đổi đường dẫn sau này chỉ sửa ở `web.php`, mọi nơi gọi `route()` tự đúng. Trong dự án thấy khắp nơi: redirect, link trong Blade, `success_url` của Stripe.

**(c) Nhóm middleware.** `Route::middleware(...)->group(...)` áp một bộ lọc cho cả cụm route — tránh lặp (§3).

Xem toàn bộ route: `php artisan route:list`.

---

## 5. Migration — cấu trúc database bằng code

### Là gì
File PHP mô tả một thay đổi cấu trúc DB. Nhờ nó, cấu trúc nằm trong Git, tái lập được trên máy khác, hoàn tác được.

### Chạy thế nào
`php artisan migrate` chạy `up()` của các migration chưa chạy (theo thứ tự tiền tố ngày giờ), ghi lại vào bảng `migrations` để không lặp. `migrate:rollback` chạy `down()`.

### Trong dự án
`create_ticket_types_table`:
```php
public function up(): void
{
    Schema::create('ticket_types', function (Blueprint $table) {
        $table->id();                                             // khoá chính tự tăng
        $table->foreignId('event_id')->constrained()->cascadeOnDelete(); // FK → events.id
        $table->string('name');
        $table->unsignedInteger('price');    // giá = số nguyên yên (JPY không có xu) — YC-2.2
        $table->unsignedInteger('quantity'); // tổng vé bán ra — YC-6.3
        $table->timestamps();                // created_at, updated_at
    });
}
public function down(): void { Schema::dropIfExists('ticket_types'); }
```
- `foreignId(...)->constrained()` tạo cột + ràng buộc khoá ngoại (đoán bảng từ tên cột). `cascadeOnDelete()` = xoá cha thì xoá con.
- `->nullable()` (ở `events.published_at`) cho cột để trống — `null` nghĩa "chưa công bố" (YC-6.2).
- `Schema::table('users', ...)` (migration `add_role`) **thêm cột vào bảng đã có** thay vì tạo mới.

---

## 6. Controller — điều phối một request

### Là gì
Class gom các *action* xử lý request. Ở **mức 1**, controller làm luôn nghiệp vụ (đặc trưng level1 — QĐ-1.1, QĐ-1.3).

### Action "sạch"
```php
// EventController::show
public function show(Event $event): View
{
    abort_unless($event->isPublished(), 404);        // chưa công bố → 404 (YC-6.2)
    $event->load('ticketTypes');                     // eager load quan hệ (§7)
    return view('events.show', ['event' => $event]);
}
```

### Action chứa nghiệp vụ (chất mức 1)
`OrderController::store()` gói cả luồng mua vé — trích phần lõi:
```php
$order = DB::transaction(function () use ($event, $quantities): Order {
    $ticketTypes = TicketType::query()
        ->whereKey(array_keys($quantities))
        ->lockForUpdate()->get()->keyBy('id');       // khoá kho (§11)

    $total = 0; $items = [];
    foreach ($quantities as $ticketTypeId => $quantity) {
        $ticketType = $ticketTypes->get($ticketTypeId);
        if ($quantity > $ticketType->remaining()) {  // kiểm tồn (YC-8.2)
            throw ValidationException::withMessages([...]);
        }
        $total += $ticketType->price * $quantity;     // chốt giá (YC-8.5)
        $items[] = [...];
    }
    $order = Order::create([..., 'expires_at' => now()->addMinutes(15)]); // giữ 15' (YC-9.1)
    $order->items()->createMany($items);
    return $order;
});
```
> Action này dài, trộn validate tồn + tính tiền + tạo đơn + gọi Stripe — **cố ý** để lộ cơn đau mà mức 2 giải quyết bằng cách tách thành Action `PlaceOrder`.

---

## 7. Eloquent Model — trái tim của ứng dụng

> Phần dài nhất, vì đây là nơi bạn làm việc nhiều nhất.

### 7.1. Ý tưởng nền: Active Record
Model là class đại diện **một bảng**; mỗi *object* là **một dòng**. Eloquent theo mẫu *Active Record*: object vừa mang dữ liệu, vừa mang hành vi lưu/xoá chính nó.
```php
$e = Event::find(5);   // SELECT * FROM events WHERE id=5 LIMIT 1  → object Event
$e->title = 'Concert';
$e->save();            // UPDATE events SET title='Concert' WHERE id=5
$e->delete();          // DELETE ...
```
Bạn không viết SQL; Eloquent sinh SQL từ lời gọi PHP.

### 7.2. Query Builder — chuỗi điều kiện
`Model::query()` mở một *builder*; nối các mệnh đề rồi kết bằng `get()`/`first()`/`sum()`…:
```php
Event::query()
    ->published()             // scope (7.5)
    ->withCount('ticketTypes')// kèm cột đếm số hạng vé
    ->orderBy('starts_at')
    ->paginate(12);           // phân trang 12 mục/trang
```
`whereKey([...])` = lọc theo khoá chính; `where('col','>',x)`, `whereHas(...)` (lọc theo quan hệ), `lockForUpdate()`… đều là mắt xích của builder. Kết quả nhiều dòng trả về **Collection** (mảng "xịn" có `map/filter/sum/keyBy`, thấy nhiều trong dự án: `->get()->keyBy('id')`).

### 7.3. `$fillable` — chặn Mass Assignment
```php
class Event extends Model
{
    /** @var list<string> */
    protected $fillable = ['title', 'description', 'venue', 'starts_at', 'published_at'];
}
```
Khi `Event::create($data)` hoặc `->update($data)`, **chỉ** các cột liệt kê được nhận từ `$data`. Đây là hàng rào an ninh: nếu không có nó, kẻ xấu thêm `role=scanner` vào form đăng ký sẽ tự nâng quyền. `User` còn có `protected $hidden = ['password', 'remember_token']` — ẩn các cột này khi model bị chuyển thành JSON. (Laravel 13 cũng cho lối attribute `#[Fillable]`/`#[Hidden]`, nhưng **dự án dùng property kiểu cũ**.)

### 7.4. Casts — DB lưu string, PHP muốn kiểu thật
```php
protected function casts(): array
{
    return [
        'starts_at' => 'datetime',   // → object Carbon, gọi được ->isPast(), ->addMinutes()
        'price' => 'integer',
        'password' => 'hashed',      // gán mật khẩu là tự băm bcrypt
    ];
}
```
Không có cast, `$event->starts_at` chỉ là chuỗi `"2026-07-08 19:00:00"`; có cast, nó là `Carbon` nên `$event->starts_at->isPast()` chạy được. Cast `hashed` là lý do `User::create(['password' => 'plaintext'])` vẫn an toàn — Eloquent băm trước khi lưu.

### 7.5. Relationship — nối bảng bằng object
Khai báo quan hệ một lần, dùng như thuộc tính. Ba loại trong dự án:

| Method | Loại | Nghĩa | SQL sinh ra (đại ý) |
|---|---|---|---|
| `Event::ticketTypes()` | `hasMany` | 1 sự kiện → nhiều hạng vé | `WHERE ticket_types.event_id = ?` |
| `Order::user()` | `belongsTo` | đơn thuộc 1 user | `WHERE users.id = orders.user_id` |
| `TicketType::event()` | `belongsTo` | hạng vé thuộc 1 sự kiện | `WHERE events.id = ...` |

Dùng:
```php
$event->ticketTypes;   // Collection các TicketType (đọc như thuộc tính)
$order->user->email;   // đi ngược lên chủ đơn
$order->items()->createMany($items); // gọi như method → tạo bản ghi con gắn khoá ngoại sẵn
```
Phân biệt: `$order->items` (thuộc tính) = **kết quả đã nạp**; `$order->items()` (method) = **builder** để nối thêm điều kiện hoặc tạo/ghi.

### 7.6. N+1 và Eager Loading — cạm bẫy hiệu năng
Nếu lặp 100 đơn rồi mỗi vòng gọi `$order->user` → 1 query lấy đơn + 100 query lấy user = **N+1 query** (chậm). Cách tránh là **nạp trước** quan hệ:
```php
$order->load(['event', 'items.ticketType', 'tickets.ticketType']); // OrderController::show
Ticket::with(['event', 'ticketType'])->get();                      // TicketController::index
Event::withCount('ticketTypes');                                    // EventController::index
```
- `load(...)` — nạp quan hệ cho object đã có sẵn.
- `with(...)` — nạp kèm ngay khi truy vấn.
- `withCount(...)` — chỉ lấy **số lượng** (không kéo cả bản ghi con).
Dấu `.` (`items.ticketType`) là nạp lồng nhiều tầng trong một lần. Đây là kỹ thuật hiệu năng bạn sẽ dùng liên tục.

### 7.7. Scope — mảnh truy vấn tái dùng
```php
public function scopePublished(Builder $query): void
{
    $query->whereNotNull('published_at')->where('published_at', '<=', now());
}
```
Gọi bằng tên bỏ tiền tố `scope`: `Event::published()->get()`. Điều kiện "đã công bố" (YC-6.2) viết **một lần** trong Model, dùng lại ở controller — không rải `whereNotNull` khắp nơi.

### 7.8. Method nghiệp vụ trong Model (đúng chất mức 1)
Ở mức 1, logic đơn giản đặt ngay trong Model (QĐ-1.2). Ví dụ hay nhất — quy tắc chống bán quá số:
```php
// TicketType.php
public function reservedQuantity(): int   // số vé đã bị "chiếm"
{
    return (int) $this->orderItems()
        ->whereHas('order', function (Builder $q): void {
            $q->where('status', Order::STATUS_PAID)          // đã trả tiền: trừ vĩnh viễn
              ->orWhere(function (Builder $q): void {
                  $q->where('status', Order::STATUS_PENDING) // đang giữ: chưa hết hạn
                    ->where('expires_at', '>', now());
              });
        })->sum('quantity');
}
public function remaining(): int { return max(0, $this->quantity - $this->reservedQuantity()); }
```
Đây là chỗ **"vé tự trả lại khi đơn hết hạn"** (YC-8.4): đơn hết hạn không khớp điều kiện `whereHas`, nên tự động không bị tính là đang giữ — **không cần** cập nhật cột tồn kho ở đâu cả. Toàn bộ số vé còn bán được suy ra động từ trạng thái đơn.

Hằng số trạng thái cũng đặt trong Model để tránh gõ chuỗi rải rác:
```php
public const string STATUS_PENDING = 'pending';
public const string STATUS_PAID = 'paid';
// dùng: $order->status === Order::STATUS_PAID
```

**Tóm tắt Model:** query builder sinh SQL; `$fillable` chặn mass-assignment; casts đổi kiểu; relationship nối bảng; eager loading tránh N+1; scope gói truy vấn; method/const gói nghiệp vụ nhỏ.

---

## 8. Form Request & Validation — làm sạch dữ liệu vào

### Là gì
Class chuyên kiểm tra dữ liệu request hợp lệ trước khi controller chạy (tách validate khỏi controller — QĐ-1.1).

### Chạy thế nào
Khai tham số kiểu `StoreOrderRequest` → Laravel (qua container §2) tạo nó, **tự validate trước**. Sai → tự redirect về kèm lỗi, **controller không chạy**. Đúng → controller nhận dữ liệu chắc sạch.

### Trong dự án
```php
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }   // có được phép gửi request này?

    public function rules(): array                        // luật trên TỪNG field
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:'.self::MAX_TICKETS_PER_ORDER],
        ];
    }

    protected function withValidator(Validator $validator): void  // luật "cần suy nghĩ"
    {
        $validator->after(function (Validator $validator): void {
            $total = array_sum(array_map('intval', $this->input('quantities', [])));
            if ($total > self::MAX_TICKETS_PER_ORDER) {           // tổng > 10 vé (YC-8.1)
                $validator->errors()->add('quantities', 'Mỗi đơn không được quá 10 vé.');
            }
            // ... còn kiểm mọi hạng vé phải thuộc đúng sự kiện này (YC-7.1)
        });
    }
}
```
- `rules()` — luật khai báo trên từng field (`required`, `integer`, `max:10`…).
- `withValidator()+after()` — luật liên-field (tổng vé, hạng vé thuộc đúng sự kiện) mà `rules()` không diễn tả nổi.
- Form Request cũng là nơi đặt method chuyển hoá đầu vào (`selectedQuantities()`), giữ controller gọn.
`LoginRequest` còn chứa cả logic đăng nhập + chặn brute-force bằng `RateLimiter` — cho thấy Form Request không chỉ để validate.

---

## 9. Policy — quyền gắn với một object cụ thể

### 9.0. Trước hết: Authentication ≠ Authorization
Hai khái niệm dễ lẫn:
- **Authentication (xác thực, authN)** — *bạn là ai*. Do login + session + middleware `auth` lo (§3).
- **Authorization (phân quyền, authZ)** — *bạn được làm gì*. Do **Policy** và **Gate** lo (§9, §10).

Middleware `auth` chỉ đảm bảo "đã đăng nhập"; nó **không** biết đơn này có phải của bạn không. Việc đó là của Policy.

### 9.1. Là gì
Policy là **class gom các quy tắc quyền cho MỘT model**. Mỗi *method* trong policy trả lời một câu hỏi quyền ("được xem không?", "được sửa không?") và trả về `bool`.

```php
// app/Policies/OrderPolicy.php
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;   // chỉ chủ đơn được xem
    }
}
```

Tham số **luôn theo thứ tự**: `(user hiện tại, object cần kiểm)`. Laravel tự truyền user đang đăng nhập vào `$user` — bạn không tự lấy.

### 9.2. Auto-discovery — vì sao không thấy đăng ký ở đâu
Trong dự án **không có dòng nào "gắn `Order` với `OrderPolicy`"**. Laravel tự khớp theo **quy ước tên**:

```
App\Models\Order   →  App\Policies\OrderPolicy
App\Models\Ticket  →  App\Policies\TicketPolicy
```

Cứ đặt policy trong `app/Policies/` tên `<Model>Policy` là Laravel tự tìm ra. (Muốn khác quy ước thì mới cần `Gate::policy(Model::class, XxxPolicy::class)` — dự án không cần.)

### 9.3. `$this->authorize()` đến từ đâu, chạy thế nào
Base controller "mở khoá" khả năng phân quyền bằng một trait:
```php
// app/Http/Controllers/Controller.php
abstract class Controller
{
    use AuthorizesRequests;   // trait này cấp method $this->authorize(...)
}
```

Nhờ đó mọi controller con gọi được `$this->authorize(...)`. Luồng khi chạy `$this->authorize('view', $order)`:

```
$this->authorize('view', $order)
   │
   ├─ lấy user đang đăng nhập
   ├─ nhìn kiểu $order = Order  → tìm OrderPolicy (auto-discovery, 9.2)
   ├─ gọi OrderPolicy::view($user, $order)
   │
   ├─ true  → không làm gì, controller chạy tiếp
   └─ false → ném AuthorizationException → Laravel trả HTTP 403
```

Điểm hay: bạn **không viết `if (...) abort(403)`**. Chỉ cần một dòng `authorize`, sai là tự 403.

### 9.4. Trong dự án — dùng ở đâu
```php
// OrderController::show()  — xem chi tiết đơn
$this->authorize('view', $order);   // không phải chủ đơn → 403, dừng ngay

// OrderController::cancel() — hủy đơn
$this->authorize('view', $order);   // tái dùng cùng quyền "view"

// TicketController::qr()   — tải ảnh QR của vé
$this->authorize('view', $ticket);  // TicketPolicy::view — chỉ chủ vé
```

Đây chính là hàng rào chống "đổi id trên URL để xem đồ người khác": dù route `/orders/{order}` nằm sau `auth`, một user đăng nhập vẫn có thể thử `/orders/999`. Policy chặn đúng chỗ đó — Route Model Binding nạp được đơn 999, nhưng `authorize('view', ...)` thấy `user_id` không khớp → 403.

### 9.5. Tên method policy = "ability"
Chuỗi truyền vào `authorize('view', ...)` khớp **tên method** trong policy. Laravel có bộ tên quy ước cho CRUD: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`. Dự án chỉ cần `view` nên policy chỉ có đúng method đó — không phải hiện thực đủ bộ.

### 9.6. Các cách gọi khác (cùng một policy)
`authorize()` trong controller chỉ là một lối vào. Cùng `OrderPolicy::view` có thể gọi bằng:
```php
$user->can('view', $order);      // trả bool — không ném lỗi, hợp để rẽ nhánh
Gate::authorize('view', $order); // như $this->authorize ngoài controller
@can('view', $order) ... @endcan // ẩn/hiện nút trong Blade
```

### 9.7. Mẹo mở rộng (không có trong dự án, nhưng nên biết)
- **Cho phép trả về câu lỗi riêng:** thay `bool` bằng `Response::deny('Lý do...')` / `Response::allow()`.
- **Quyền "trùm" (super-admin bỏ qua mọi policy):** thêm method `before(User $user)` trong policy trả `true` cho admin — nó chạy trước mọi method khác.
- **Khách chưa đăng nhập:** để `$user` là nullable (`?User $user`) nếu muốn policy áp cho cả khách; ở đây không cần vì các route đều nằm sau `auth`.

---

## 10. Gate — quyền chung, không gắn object

### 10.1. Là gì
Gate là một **quy tắc quyền dạng closure, không cần object cụ thể**. Ở đây trả lời: "user có phải nhân viên soát vé không?" (YC-4.2) — một câu hỏi về *vai trò*, không về "đơn nào/vé nào".

Khai báo một lần trong `AppServiceProvider::boot()` (nơi an toàn để cấu hình, §2):
```php
Gate::define('check-in', fn (User $user): bool => $user->isScanner());
//            ^tên ability   ^logic: đúng khi user.role == 'scanner'
```

### 10.2. Cùng một Gate, ép ở HAI lớp — defense in depth
Điểm đáng học nhất trong dự án: ability `check-in` được kiểm ở **cả hai tầng**, mỗi tầng một mục đích.

**(a) Lớp server — route middleware (bắt buộc thật):**
```php
// routes/web.php
Route::middleware(['auth', 'can:check-in'])->group(function (): void {
    Route::get('/check-in',  [CheckInController::class, 'create']);
    Route::post('/check-in', [CheckInController::class, 'store']);
});
```
`can:check-in` chạy Gate; false → **403**, không vào được controller. Đây là hàng rào *thật* — kẻ gõ thẳng URL vẫn bị chặn.

**(b) Lớp giao diện — Blade `@can` (chỉ để gọn mắt):**
```blade
{{-- resources/views/layouts/app.blade.php --}}
@auth
    <a href="{{ route('tickets.index') }}">Vé của tôi</a>
    @can('check-in')
        <a href="{{ route('checkin.create') }}">Soát vé</a>   {{-- chỉ scanner mới thấy link --}}
    @endcan
@endauth
```
`@can('check-in')` ẩn link "Soát vé" với người mua thường. **Nhưng đây chỉ là tiện lợi UI, không phải bảo mật** — ẩn link không ngăn ai gõ tay URL. Bảo mật thật nằm ở (a). Nguyên tắc: **UI ẩn cho gọn, server chặn cho chắc.**

### 10.3. Các cách gọi Gate
```php
Gate::allows('check-in')        // bool, trong code PHP
Gate::denies('check-in')        // phủ định
$user->can('check-in')          // qua model user
'can:check-in' (middleware)     // ở route — dùng trong dự án
@can('check-in') ... @endcan    // trong Blade — dùng trong dự án
```

---

## 10bis. Policy vs Gate vs `FormRequest::authorize()` — đừng lẫn ba thứ

Cả ba đều nói về "được phép hay không", nhưng khác vai trò. Đây là chỗ người mới hay rối:

| | Trả lời | Gắn object? | Khai ở đâu | Ví dụ trong dự án |
|---|---|---|---|---|
| **Policy** | Được thao tác trên **object NÀY** không? | Có (một instance) | `app/Policies/` | `OrderPolicy::view` — chủ đơn |
| **Gate** | Có **khả năng/vai trò** chung này không? | Không | `AppServiceProvider::boot()` | `check-in` — là scanner |
| **`FormRequest::authorize()`** | Được **gửi request này** không? (chạy trước validate) | Không | ngay trong Form Request | `StoreOrderRequest`, `LoginRequest` → `return true` |

Về bản chất, **Policy chỉ là Gate được tổ chức theo từng model** — cả hai chạy qua cùng một hệ thống Gate bên dưới, nên dùng chung `can()` / `authorize()` / `@can`.

Còn `FormRequest::authorize()` là **cơ chế tách biệt**: nó là "cổng" của riêng Form Request, chạy *trước* khi validate dữ liệu. Trong dự án cả ba Form Request đều `return true` (ai cũng được gửi form) vì quyền thật đã do middleware/policy lo ở nơi khác. Nếu để `return false`, request bị chặn 403 ngay, chưa cần validate.

**Chọn cái nào?**
- Câu hỏi có "object NÀY" (đơn này, vé này, bài viết này) → **Policy**.
- Câu hỏi về vai trò/khả năng chung (là admin, là scanner) → **Gate**.
- Muốn chặn ngay ở tầng "được nộp form này không" → **`FormRequest::authorize()`**.

---

## 11. Transaction & Lock — chống bán quá số khi mua đồng thời

Kỹ thuật database dùng ở `OrderController`, `StripeWebhookController`, `CheckInController`. Phần khó và quan trọng nhất.

**Vấn đề:** còn 1 vé, hai người bấm mua cùng lúc; nếu cả hai cùng đọc "còn 1" rồi cùng tạo đơn → bán 2 vé cho 1 chỗ (race condition, YC-8.3).

**`DB::transaction()`** — mọi lệnh bên trong là **một khối nguyên tử**: thành công tất cả, hoặc ném exception thì **rollback sạch**. Không để lại đơn dở dang.

**`lockForUpdate()`** — khoá các dòng đọc ra tới hết transaction (*pessimistic lock*):
```php
TicketType::query()->whereKey($ids)->lockForUpdate()->get();
```
Transaction A khoá dòng hạng vé; B đụng cùng dòng **phải đợi** A xong. Nên người sau đọc tồn kho **sau khi** người trước đã trừ → thấy hết vé, bị từ chối (YC-8.3). Cùng kỹ thuật lặp lại ở webhook (khoá đơn, §13) và soát vé (khoá vé, chống quét trùng YC-11.3).

---

## 12. Artisan Command + Scheduler — việc nền theo giờ

**Command** = lệnh CLI (không qua trình duyệt). **Scheduler** = bộ hẹn giờ.
```php
#[Signature('orders:expire')]
class ExpireStaleOrders extends Command {
    public function handle(): int {
        $expired = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->update(['status' => Order::STATUS_EXPIRED, 'updated_at' => now()]);
        $this->info("Đã cho hết hạn {$expired} đơn.");
        return self::SUCCESS;
    }
}
```
Tinh tế: lệnh chỉ đổi trạng thái, **không cộng trả** số vé — vé tự trả lại vì `reservedQuantity()` (§7.8) không tính đơn hết hạn. Hẹn giờ ở `routes/console.php`:
```php
Schedule::command('orders:expire')->everyMinute(); // YC-9.1
```
(Server cần một cron gọi `php artisan schedule:run` mỗi phút để lịch chạy thật.)

---

## 13. Idempotency ở Stripe webhook

Stripe xác nhận thanh toán bằng cách **gọi vào server ta** (không tin trình duyệt — YC-9.2), và **có thể gọi lặp** (retry). Nếu mỗi lần đều phát hành vé → vé nhân đôi. Xử lý phải **idempotent**:
```php
$order = Order::query()->where('stripe_session_id', $sessionId)
    ->lockForUpdate()->first();       // khoá đơn: hai webhook cùng lúc xếp hàng
if (! $order->isPending()) return false; // đã xử lý rồi → bỏ qua (YC-9.3)
$order->update(['status' => Order::STATUS_PAID, 'paid_at' => now(), 'expires_at' => null]);
foreach ($order->items as $item)
    for ($i = 0; $i < $item->quantity; $i++)
        Ticket::create([..., 'token' => (string) Str::ulid()]); // 1 vé = 1 token QR (YC-10.1)
```
Chìa khoá: **khoá đơn + kiểm cờ `isPending`**. Webhook đầu đổi sang `paid`; các webhook sau thấy không còn `pending` nên dừng. `Str::ulid()` sinh token duy nhất cho mỗi vé.

---

## 14. Mailable — email xác nhận vé
```php
class OrderConfirmationMail extends Mailable {
    public function __construct(public Order $order) {}     // DI qua constructor (§2)
    public function envelope(): Envelope { return new Envelope(subject: 'Xác nhận vé — '.$this->order->event->title); }
    public function content(): Content { return new Content(view: 'emails.order-confirmation', with: ['order' => $this->order]); }
}
```
Gửi sau khi thanh toán chắc chắn thành công (YC-7.4): `Mail::to($order->user->email)->send(new OrderConfirmationMail($order));`. Dev đặt `MAIL_MAILER=log` để email ghi vào log thay vì gửi thật.

---

## 15. View (Blade) — HTML
`return view('events.show', ['event' => $event])` render `resources/views/events/show.blade.php`, thay biến, trả HTML. Trong view `{{ $event->title }}` in ra (tự chống XSS); `@foreach`/`@if` là cú pháp điều khiển. Ở mức 1, view chỉ hiển thị — nghiệp vụ đã xong ở controller.

---

## 16. Factory & Seeder — dữ liệu giả
**Factory** sinh model giả (chủ yếu cho test); **Seeder** đổ dữ liệu mẫu (`php artisan db:seed`).
```php
Event::factory()->count(5)->create();
User::factory()->create(['role' => 'scanner']); // ghi đè field cụ thể
```
Mỗi model có factory ở `database/factories/`. Test trong `tests/` dùng chúng dựng tình huống ("hạng vé còn 1 vé, 2 người cùng mua"). Chạy: `php artisan test --compact` (27/27 pass).

---

## 17. Package ngoài — Stripe & QR
Cắm qua Composer (`composer.json`):
- **`stripe/stripe-php`** — tạo phiên Checkout JPY (`OrderController::redirectToPayment`), kiểm chữ ký webhook (`StripeWebhookController`).
- **`simplesoftwareio/simple-qrcode`** — sinh QR: `QrCode::format('svg')->size(220)->generate($ticket->token)` (`TicketController::qr`).
Cả hai "tắt mềm" khi thiếu cấu hình (không có `STRIPE_SECRET` thì bỏ qua Stripe) — chạy được ở dev/test không cần khoá thật.

---

## 18. Facade & helper — lối gọi tắt
Không phải phép thuật, chỉ là cú pháp gọn trỏ tới service trong container (§2):

| Viết | Là gì | Ví dụ |
|---|---|---|
| `DB::transaction(...)` | Facade database | `OrderController::store` |
| `Auth::attempt(...)` | Facade xác thực | `LoginRequest` |
| `Gate::define(...)` | Facade phân quyền | `AppServiceProvider` |
| `Mail::to(...)->send(...)` | Facade mail | `StripeWebhookController` |
| `auth()->id()` | helper — user hiện tại | `OrderController` |
| `now()` | helper — Carbon hiện tại | khắp nơi |
| `config('services.stripe.secret')` | helper — đọc cấu hình | `OrderController` |
| `route('events.show', $e)` | helper — URL từ tên route | `OrderController` |

`DB::transaction()` ≈ lấy service `db` rồi `->transaction()`; `now()` ≈ `Carbon::now()`.

---

## 19. Bản đồ tổng kết

| Thành phần | Trả lời câu hỏi | File tiêu biểu |
|---|---|---|
| Service Container / DI | Object ở đâu ra, ai tạo? | `AppServiceProvider.php`, mọi chữ ký hàm |
| Middleware | Ai được đi tiếp, lọc gì? | `bootstrap/app.php` + `routes/web.php` |
| Route + Binding | URL nào gọi code nào? | `routes/web.php` |
| Migration | Bảng có cấu trúc gì? | `database/migrations/*` |
| Controller | Xử lý một request thế nào? | `OrderController.php` |
| Eloquent Model | Dữ liệu cư xử ra sao? | `TicketType.php`, `Order.php` |
| Form Request | Dữ liệu vào hợp lệ không? | `StoreOrderRequest.php` |
| Policy / Gate | Được phép làm không? | `OrderPolicy.php`, `AppServiceProvider.php` |
| Transaction + Lock | Chống race condition | `OrderController.php` |
| Command + Scheduler | Việc nền theo giờ | `ExpireStaleOrders.php` |
| Mailable | Gửi email gì? | `OrderConfirmationMail.php` |
| View (Blade) | Hiển thị HTML | `resources/views/*` |
| Factory / Seeder | Dữ liệu giả để test | `database/factories/*` |

**Đặc trưng mức 1:** không Action/Service/Repository — mọi nghiệp vụ nằm thẳng trong Controller/Command. Cố ý, để khi sang `level2/` bạn thấy rõ các action phình to này được tách ra thế nào.
