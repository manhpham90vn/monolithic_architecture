# Eloquent Model — Sổ tra cứu đầy đủ (Laravel 13)

| | |
|---|---|
| **Mục đích** | Tài liệu tra cứu mọi tính năng của Eloquent Model, minh hoạ bằng model thật trong `level1/`. |
| **Phiên bản** | Laravel Framework **13.19** (đã kiểm trong `vendor/`). |
| **Cách dùng** | Đọc §0 để nắm bố cục, sau đó tra theo mục lục. Mục nào **chưa dùng trong dự án** được đánh dấu 🔹 — nêu để bạn biết khi cần. |

> Ký hiệu: 🟢 = có dùng trong `level1/` (kèm file). 🔹 = chưa dùng, nêu để tra cứu.
>
> **Quy ước code của dự án:** dùng **kiểu property truyền thống** (`protected $fillable`, `protected $hidden`, method `casts()`), **không** dùng bộ attribute PHP 8 (`#[Fillable]`, `#[Scope]`…). Attribute chỉ nêu để tham khảo và luôn đánh dấu 🔹.

### 🧪 Cách "Chạy thử" các ví dụ

Xuyên suốt tài liệu có các block **🧪 Chạy thử** — bạn gõ trực tiếp được để thấy kết quả thật. Mở bảng điều khiển tương tác của Laravel:

```bash
cd level1
php artisan tinker
```

Trong tinker, gõ một dòng PHP rồi Enter; nó in kết quả sau dấu `=>`. Ví dụ:
```
>>> 1 + 1
=> 2
>>> App\Models\Event::count()
=> 3
```
Thoát bằng `exit` (hoặc Ctrl-D).

**Các ví dụ giả định dữ liệu mẫu** từ seeder — nạp lại bằng:
```bash
php artisan migrate:fresh --seed
```
Sau lệnh này DB có: 3 sự kiện — *"Live Concert 2026"* (2 hạng vé: **Vé thường** 5000₫/100 vé, **Vé VIP** 15000₫/20 vé), *"Tech Expo"* (1 hạng), và *"Bí mật"* (chưa công bố) — cùng 2 người dùng. Kết quả in trong tài liệu là output **thật** với bộ dữ liệu này.

## Mục lục
- [§1. Khai báo model](#1-khai-báo-model)
- [§2. Mass assignment: Fillable / Guarded](#2-mass-assignment-fillable--guarded)
- [§3. Casts — đổi kiểu thuộc tính](#3-casts--đổi-kiểu-thuộc-tính)
- [§4. Accessor & Mutator — biến đổi thuộc tính](#4-accessor--mutator--biến-đổi-thuộc-tính)
- [§5. Serialization: Hidden / Visible / Appends](#5-serialization-hidden--visible--appends)
- [§6. Quan hệ — khai báo](#6-quan-hệ--khai-báo)
- [§7. Quan hệ — cách DÙNG (đọc, tạo, gắn/gỡ)](#7-quan-hệ--cách-dùng)
- [§8. Eager Loading: with / load / withCount … (chống N+1)](#8-eager-loading--chống-n1)
- [§9. Truy vấn theo quan hệ: has / whereHas …](#9-truy-vấn-theo-quan-hệ)
- [§10. Scope — local & global](#10-scope)
- [§11. Method, hằng số, helper trong model](#11-method-hằng-số-helper-trong-model)
- [§12. Query & CRUD qua model](#12-query--crud-qua-model)
- [§13. Model Events & Observer](#13-model-events--observer)
- [§14. Soft Deletes, Timestamps, và trait khác](#14-soft-deletes-timestamps-và-các-trait-khác)
- [§15. Route Model Binding](#15-route-model-binding)
- [§16. Các method tiện ích trên một instance](#16-các-method-tiện-ích-trên-một-instance)
- [§17. Bảng tra nhanh Attribute v13](#17-bảng-tra-nhanh-attribute-v13)
- [§18. Đối chiếu tính năng ↔ file trong dự án](#18-đối-chiếu)

---

## 1. Khai báo model

### 1.1. Tạo model bằng Artisan
```bash
php artisan make:model Event                # chỉ model
php artisan make:model Event -m             # + migration
php artisan make:model Event -mfsc          # + migration, factory, seeder, controller
php artisan make:model Event --all          # tất cả (migration, factory, seeder, policy, controller, request)
```
Ghi nhớ options: `-m` migration, `-f` factory, `-s` seeder, `-c` controller, `-p` policy, `-r` resource controller.

### 1.2. Khung một model
🟢 `app/Models/Event.php` (kiểu property truyền thống — quy ước dự án):
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /** @var list<string> */
    protected $fillable = ['title', 'description', 'venue', 'starts_at', 'published_at'];

    // casts, quan hệ, scope, method ...
}
```
Model `User` là ngoại lệ: nó `extends Authenticatable` (không phải `Model`) để có khả năng đăng nhập.

### 1.3. Quy ước ngầm (convention over configuration)
Không khai báo gì thì Eloquent tự đoán:

| Thứ | Quy ước mặc định | Ví dụ |
|---|---|---|
| Tên bảng | snake_case, số nhiều của tên class | `Event` → `events`, `TicketType` → `ticket_types` |
| Khoá chính | cột `id`, kiểu int tự tăng | `$event->id` |
| Timestamps | có cột `created_at`, `updated_at` | tự điền khi tạo/sửa |
| Khoá ngoại | `<model>_id` | `Order` belongsTo `User` → `user_id` |

Ý tưởng "convention over configuration": nếu bạn **theo quy ước đặt tên**, bạn **không phải cấu hình gì cả** — Eloquent tự suy ra. Chỉ khi lệch quy ước mới cần khai (§1.4).

> **🧪 Chạy thử** — hỏi model xem nó tự suy ra bảng/khoá nào:
> ```
> >>> (new App\Models\TicketType)->getTable()     // class TicketType → bảng nào?
> => "ticket_types"                               // tự đổi sang snake_case số nhiều
> >>> (new App\Models\TicketType)->getKeyName()   // khoá chính là cột nào?
> => "id"
> >>> (new App\Models\Event)->getTable()
> => "events"
> ```

### 1.4. Ghi đè quy ước — dùng property (kiểu cũ)
Laravel 13 cho cả hai lối: property truyền thống **và** attribute PHP 8. **Dự án dùng property** (kiểu cũ):

| Việc | Property truyền thống (dùng trong dự án) | Attribute v13 (🔹 không dùng) |
|---|---|---|
| Danh sách fillable | `protected $fillable = [...];` | `#[Fillable([...])]` |
| Ẩn khỏi JSON | `protected $hidden = [...];` | `#[Hidden([...])]` |
| Đổi tên bảng | `protected $table = 'my_events';` | `#[Table('my_events')]` |
| Đổi khoá chính | `protected $primaryKey = 'uuid';` | — |
| Khoá không tự tăng | `public $incrementing = false;` | `#[WithoutIncrementing]` |
| Kiểu khoá | `protected $keyType = 'string';` | — |
| Tắt timestamps | `public $timestamps = false;` | `#[WithoutTimestamps]` |
| Đổi connection | `protected $connection = 'mysql2';` | `#[Connection('mysql2')]` |
| Định dạng ngày lưu | `protected $dateFormat = 'U';` | `#[DateFormat('U')]` |

Dự án khai `$fillable`/`$hidden` khi cần và để **mặc định** cho phần còn lại (bảng/khoá/timestamps đúng quy ước nên không khai). Bảng attribute đầy đủ ở [§17](#17-bảng-tra-nhanh-attribute-v13) chỉ để tham khảo.

---

## 2. Mass assignment: Fillable / Guarded

**Mass assignment** = gán nhiều cột một lúc từ mảng (`create($data)`, `update($data)`, `fill($data)`). Nếu không kiểm soát, kẻ xấu nhồi field lạ (vd `role=scanner`) qua form → nâng quyền. Hai cách rào:

🟢 **Fillable** — danh sách trắng, *chỉ* các cột này được gán hàng loạt:
```php
// Event.php — kiểu property (dùng trong dự án)
/** @var list<string> */
protected $fillable = ['title', 'description', 'venue', 'starts_at', 'published_at'];
```

> **🧪 Chạy thử** — `role` KHÔNG có trong `$fillable` của `User` (chỉ có `name`, `email`, `password`), nên dù cố nhồi vào vẫn bị bỏ qua:
> ```
> >>> $u = new App\Models\User(['name' => 'Hacker', 'email' => 'h@x.com', 'password' => 'secret123', 'role' => 'scanner']);
> >>> $u->name
> => "Hacker"                 // name có trong $fillable → nhận
> >>> $u->role
> => null                     // role KHÔNG trong $fillable → bị chặn, không thành 'scanner'
> ```
> Đây chính là lá chắn: kẻ xấu thêm `role=scanner` vào form đăng ký cũng không leo thang quyền được.

🔹 **Guarded** — danh sách đen (ngược lại): cấm các cột này, còn lại cho phép.
```php
protected $guarded = ['id'];       // property (kiểu cũ)
// 🔹 attribute tương đương: #[Guarded(['id'])] / #[Unguarded] — dự án không dùng
```
Quy tắc: chọn **một** trong hai. Dự án dùng `Fillable` (an toàn hơn vì mặc định cấm).

**Ghi/đọc không qua mass assignment** thì không bị chặn:
```php
$event->title = 'X'; $event->save();   // gán trực tiếp — không dính Fillable
$event->forceFill([...])->save();      // cố tình bỏ qua rào
```

---

## 3. Casts — tự đổi kiểu dữ liệu khi đọc/ghi

### 3.0. Vấn đề: database chỉ lưu chuỗi và số

Cột `starts_at` trong DB thực chất là một **chuỗi** `"2026-08-08 09:10:20"`. Nếu không làm gì, khi đọc `$event->starts_at` bạn nhận về một chuỗi — muốn hỏi "sự kiện này đã qua chưa?" thì phải tự tay phân tích chuỗi, rất phiền.

**Cast** giải quyết việc đó: bạn khai *"cột này hãy coi là kiểu datetime"*, thế là Eloquent tự biến chuỗi thành một đối tượng ngày giờ (`Carbon`) mỗi khi đọc, và tự biến ngược lại thành chuỗi khi lưu. Bạn được dùng luôn các hàm tiện lợi như `->isPast()`, `->addMinutes(15)`, `->diffForHumans()`.

### 3.1. Khai cast bằng method `casts()`

🟢 Thấy ở mọi model dự án:
```php
// Order.php
protected function casts(): array
{
    return [
        'total_amount' => 'integer',    // luôn đọc ra là số nguyên, không phải chuỗi "5000"
        'expires_at'   => 'datetime',   // → đối tượng Carbon: gọi được ->isPast(), ->addMinutes()
        'paid_at'      => 'datetime',
    ];
}
// User.php: 'password' => 'hashed'  → gán mật khẩu thô là tự động băm bcrypt trước khi lưu
```

> **🧪 Chạy thử** — thấy rõ khác biệt "chuỗi trong DB" vs "Carbon khi đọc":
> ```
> >>> $e = App\Models\Event::first();
> >>> $e->getRawOriginal('starts_at')      // giá trị THÔ đúng như lưu trong DB
> => "2026-08-08 09:10:20"                 //   → chỉ là chuỗi
>
> >>> get_class($e->starts_at)             // còn khi đọc qua model thì...
> => "Illuminate\Support\Carbon"           //   → đã thành đối tượng ngày giờ (nhờ cast 'datetime')
>
> >>> $e->starts_at->diffForHumans()       // nên gọi được hàm ngày giờ
> => "4 weeks from now"
>
> >>> $e->starts_at->isPast()
> => false                                 // sự kiện ở tương lai
> ```

### 3.2. Các cast dựng sẵn hay dùng
| Cast | Kết quả | Ghi chú |
|---|---|---|
| `'integer'`, `'float'`, `'boolean'`, `'string'` | ép kiểu cơ bản | |
| `'datetime'`, `'date'` | object `Carbon` | thêm `:format`: `'datetime:Y-m-d H:i'` |
| `'immutable_datetime'` | `CarbonImmutable` | |
| `'array'`, `'json'` | mảng ⇄ JSON trong DB | cột kiểu `json`/`text` |
| `'collection'` | `Collection` ⇄ JSON | |
| `'object'` | `stdClass` ⇄ JSON | |
| `'encrypted'`, `'encrypted:array'` | tự mã hoá/giải mã | dữ liệu nhạy cảm |
| `'hashed'` | băm khi ghi | mật khẩu |
| `'decimal:2'` | chuỗi số cố định phần thập phân | tiền |
| `AsStringable::class` | `Stringable` | |

**Cách đọc bảng:** cột trái là chuỗi bạn viết trong `casts()`, cột giữa là thứ bạn nhận được khi đọc thuộc tính. Vài cast đáng chú ý:

- **`'array'`** — DB không có kiểu "mảng", nên muốn lưu mảng PHP ta phải đổi nó thành chuỗi JSON. Cast `'array'` làm việc đó hai chiều tự động:
  ```php
  // casts(): ['options' => 'array']
  $event->options = ['color' => 'red', 'seats' => [1, 2]];  // gán mảng bình thường
  $event->save();               // DB lưu chuỗi: {"color":"red","seats":[1,2]}
  $event->options['color'];     // đọc ra lại là mảng PHP → "red"
  ```
- **`'hashed'`** — băm một chiều ngay khi **gán**, dùng cho mật khẩu. "Băm" = biến `"secret123"` thành chuỗi loằng ngoằng không thể đảo ngược; khi đăng nhập Laravel so bằng `Hash::check()` chứ không bao giờ giải mã lại.
- **`'decimal:2'`** — trả về **chuỗi** `"49.90"` (đúng 2 chữ số thập phân) chứ không phải float, vì float làm tròn sai với tiền. (Dự án né hẳn vấn đề này bằng cách lưu tiền là **số nguyên** — 5000₫ lưu `5000`, cast `'integer'`.)
- **`'encrypted'`** — mã hoá khi ghi, giải mã khi đọc (hai chiều, khác `hashed` một chiều). Dùng cho dữ liệu nhạy cảm cần đọc lại được (số CMND…). Trong DB chỉ thấy chuỗi rác.

> **🧪 Chạy thử** — cast `'hashed'` của `User` băm ngay lúc gán (chưa cần save, không đụng DB):
> ```
> >>> $u = new App\Models\User(['name' => 'A', 'email' => 'a@a.com', 'password' => 'secret123']);
> >>> $u->password
> => "$2y$12$Xk3..."            // KHÔNG còn là "secret123" — đã băm bcrypt ngay khi gán
> >>> Illuminate\Support\Facades\Hash::check('secret123', $u->password)
> => true                       // đăng nhập so sánh bằng cách này, không giải mã
> ```

### 3.3. 🔹 Cast Enum

**Enum PHP là gì?** Một kiểu dữ liệu (PHP 8.1+) liệt kê sẵn các giá trị hợp lệ — như một danh sách "chỉ được chọn một trong các món này":
```php
// app/Enums/OrderStatus.php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid    = 'paid';
    case Expired = 'expired';
}
```
Cast cột sang enum thì đọc/ghi đều là **case của enum**, không phải chuỗi trần:
```php
protected function casts(): array
{
    return ['status' => OrderStatus::class];
}

$order->status;                          // OrderStatus::Paid (một object, không phải chuỗi "paid")
$order->status === OrderStatus::Paid;    // so sánh an toàn — gõ sai tên case là PHP báo lỗi ngay
$order->status = OrderStatus::Expired;   // gán case → DB lưu chuỗi 'expired'
$order->status->value;                   // cần chuỗi thô thì lấy ->value → "expired"
```
Lợi ích so với chuỗi trần: gán `$order->status = 'paidd'` (gõ nhầm) với chuỗi thì âm thầm sai; với enum thì **nổ lỗi ngay** vì không có case nào như vậy.

> Dự án **không** cast enum: nó lưu status là string kèm hằng số `const STATUS_PENDING` (§11). Đây là lựa chọn hợp lệ ở mức 1 (đơn giản, ít file hơn); enum là bước nâng cấp tự nhiên khi lên level2+.

### 3.4. 🔹 Custom cast

Khi phép biến đổi bạn cần **không có sẵn** trong bảng §3.2, tự viết một class biến đổi. Ví dụ kinh điển: hai cột `latitude`/`longitude` muốn gom thành một object `Point` cho dễ dùng:
```php
// app/Casts/AsPoint.php
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class AsPoint implements CastsAttributes
{
    // get() — chạy khi ĐỌC: nhận giá trị thô từ DB, trả về thứ bạn muốn dùng
    public function get($model, string $key, $value, array $attributes): Point
    {
        return new Point($attributes['latitude'], $attributes['longitude']);
    }

    // set() — chạy khi GHI: nhận thứ bạn gán, trả về mảng [cột => giá trị thô] để lưu
    public function set($model, string $key, $value, array $attributes): array
    {
        return ['latitude' => $value->lat, 'longitude' => $value->lng];
    }
}
```
```php
// Đăng ký trong model — dùng y hệt cast dựng sẵn:
protected function casts(): array
{
    return ['location' => AsPoint::class];
}

$venue->location;                        // → object Point (qua get())
$venue->location = new Point(35.6, 139.7); // → set() tách ra 2 cột rồi lưu
```
So với accessor/mutator (§4): custom cast là một **class tách riêng, tái dùng được** cho nhiều model; accessor/mutator viết ngay trong model, cho đúng một thuộc tính.

### 3.5. 🔹 Cast bằng attribute
Ngoài method `casts()`, v13 có `#[Casts]`-style qua attribute cột — nhưng method `casts()` là cách chuẩn, cứ dùng nó.

---

## 4. Accessor & Mutator — biến đổi thuộc tính

🔹 (Chưa dùng trong dự án — nhưng rất hay dùng, nên biết.)

**Vấn đề:** đôi khi bạn muốn giá trị đọc ra/ghi vào **đi qua một phép biến đổi** — ví dụ luôn viết hoa chữ đầu khi hiển thị, hoặc muốn có một "thuộc tính" `full_name` trong khi DB chỉ có hai cột `first_name`/`last_name`. Đó là việc của:
- **Accessor** = biến đổi khi **ĐỌC** (`$user->full_name` → chạy hàm `get`).
- **Mutator** = biến đổi khi **GHI** (`$post->title = 'abc'` → chạy hàm `set` trước khi lưu).

Laravel 9+ gói cả hai trong một method trả về `Attribute`:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

// "Thuộc tính ảo" fullName không có cột trong DB — ghép từ first/last
protected function fullName(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => $attributes['first_name'].' '.$attributes['last_name'],
    );
}

// Biến đổi khi đọc VÀ khi ghi cột title
protected function title(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => ucfirst($value),   // đọc ra: viết hoa chữ đầu
        set: fn (string $value) => strtolower($value), // lưu vào: chữ thường
    );
}
```

**Giải phẫu từng phần:**
- Hai tham số closure nhận được: `$value` = giá trị thô của **chính cột đó** trong DB (với thuộc tính ảo như `fullName` thì là `null` vì không có cột); `$attributes` = mảng **tất cả** cột thô của bản ghi — dùng khi cần ghép từ nhiều cột.
- `get:` trả về thứ người dùng model sẽ thấy; `set:` trả về thứ sẽ **được lưu xuống DB**. Khai một trong hai hoặc cả hai đều được.
- **Quy tắc tên** (dễ vấp nhất): method đặt **camelCase** (`fullName`), còn khi dùng thì truy cập bằng **snake_case** (`$user->full_name`). Laravel tự đổi qua lại — nếu bạn gõ `$user->fullName` sẽ KHÔNG chạy accessor.

Luồng chạy cụ thể:
```php
$user->full_name;            // ĐỌC → Laravel thấy có method fullName() → chạy get → "Nam Tran"
$post->title = 'HELLO';      // GHI → chạy set → strtolower → bộ nhớ giữ "hello"
$post->save();               // DB lưu "hello"
echo $post->title;           // ĐỌC → chạy get → ucfirst → in "Hello"
```
Thêm `->shouldCache()` sau `Attribute::make(...)` nếu phép tính nặng — kết quả chỉ tính một lần cho mỗi instance.

**Phân biệt với casts (§3):** cast dùng cho phép đổi kiểu chuẩn/tái dùng (datetime, array…); accessor/mutator cho logic riêng của *một* thuộc tính trong *một* model. Nếu bạn viết cùng một `Attribute::make` ở model thứ hai → dấu hiệu nên chuyển thành custom cast (§3.4).

---

## 5. Serialization: Hidden / Visible / Appends

Kiểm soát khi model bị đổi thành mảng/JSON (`->toArray()`, `->toJson()`, trả về từ API/route).

🟢 **Hidden** — ẩn cột nhạy cảm khỏi JSON:
```php
// User.php — kiểu property (dùng trong dự án)
/** @var list<string> */
protected $hidden = ['password', 'remember_token'];
```

> **🧪 Chạy thử** — `toArray()` đổi user thành mảng; `password` và `remember_token` **không** xuất hiện:
> ```
> >>> array_keys(App\Models\User::first()->toArray())
> => ["id", "name", "email", "email_verified_at", "created_at", "updated_at", "role"]
>    // KHÔNG có "password", "remember_token" — đã bị $hidden loại
> ```

🔹 **Visible** — ngược lại của `$hidden`: *chỉ* các cột trong danh sách này mới xuất hiện, mọi cột khác bị ẩn hết:
```php
protected $visible = ['id', 'name'];   // toArray() chỉ còn đúng 2 key này
```
Chọn theo tình huống: ẩn vài cột nhạy cảm → `$hidden`; chỉ muốn lộ vài cột → `$visible`. (Giống quan hệ Fillable/Guarded ở §2 — một cái danh sách đen, một cái danh sách trắng; chỉ dùng **một** trong hai.)

🔹 **Appends** — vấn đề: accessor (§4) là "thuộc tính ảo", **không phải cột thật**, nên mặc định `toArray()` KHÔNG đưa nó vào (Laravel chỉ serialize các cột lấy từ DB). Muốn nó xuất hiện trong JSON phải khai báo tường minh:
```php
protected $appends = ['full_name'];   // full_name là accessor ở §4
// Giờ $user->toArray() có thêm key "full_name" => "Nam Tran" — dù DB không có cột này
```
Điều kiện: phải có accessor `fullName()` tương ứng, vì `$appends` chỉ nói "hãy gọi accessor này lúc serialize" chứ không tự tạo ra dữ liệu.

**Chỉnh theo từng lần (runtime)** — `$hidden`/`$visible` là mặc định cho *mọi* lần serialize; khi một chỗ cụ thể cần khác đi, đổi tạm cho riêng instance đó:
```php
$user->makeVisible('password')->toArray();  // riêng lần này lộ password (vd trang admin/debug)
$user->makeHidden('email')->toArray();      // riêng lần này giấu thêm email
$user->append('full_name')->toArray();      // riêng lần này thêm accessor vào JSON
```

> **🧪 Chạy thử** — mặc định ẩn, `makeVisible` mở lại cho một lần:
> ```
> >>> array_key_exists('password', App\Models\User::first()->toArray())
> => false                       // $hidden đang ẩn
> >>> array_key_exists('password', App\Models\User::first()->makeVisible('password')->toArray())
> => true                        // chỉ instance này, lần này — model khác vẫn ẩn như thường
> ```

**Vì sao chuyện này quan trọng:** route/controller trả model về là Laravel tự gọi `toJson()` — quên `$hidden` là hash mật khẩu của người dùng **bay thẳng ra API** cho cả thế giới xem. Đây là lỗi bảo mật thật, rất phổ biến.

---

## 6. Quan hệ — khai báo

### 6.0. Nguyên tắc gốc: **khoá ngoại nằm ở bảng nào** quyết định loại quan hệ

Đây là chỗ người mới hay rối nhất. Chỉ cần nhớ một quy tắc: **bảng con giữ cột khoá ngoại `<cha>_id` trỏ lên bảng cha.** Từ đó suy ra loại quan hệ khai ở mỗi phía:

- Đứng ở **phía cha** (bảng KHÔNG chứa khoá ngoại) → khai `hasOne` / `hasMany`.
- Đứng ở **phía con** (bảng CHỨA khoá ngoại) → khai `belongsTo`.

Sơ đồ khoá ngoại thật của dự án (mũi tên = "chứa khoá ngoại trỏ tới"):
```text
users ◄────user_id──── orders ────event_id────► events
                          │                        ▲
                          │ order_id               │ event_id
                          ▼                         │
                     order_items ──ticket_type_id──►│
                                                 ticket_types
tickets ──order_id──► orders,  ──ticket_type_id──► ticket_types,
        ──event_id──► events,  ──user_id─────────► users
```
Ví dụ: bảng `orders` chứa cột `user_id` → `Order` là **con** của `User`. Vậy `Order` khai `belongsTo(User)`, còn `User` khai `hasMany(Order)`. **Hai chiều là hai khai báo riêng** (§6.5).

Mỗi quan hệ khai bằng một method trả về đối tượng quan hệ. Kiểu generic PHPDoc (`@return HasMany<TicketType, $this>`) giúp IDE gợi ý — theo convention dự án (§6.9).

---

### 6.1. `hasMany` — "tôi là cha, con nằm ở bảng kia" (một → nhiều)

🟢 Trong dự án:
```php
// Event.php — 1 sự kiện có NHIỀU hạng vé và NHIỀU đơn
/** @return HasMany<TicketType, $this> */
public function ticketTypes(): HasMany { return $this->hasMany(TicketType::class); }

/** @return HasMany<Order, $this> */
public function orders(): HasMany { return $this->hasMany(Order::class); }

// Order.php — 1 đơn có nhiều dòng hàng và nhiều vé
public function items(): HasMany { return $this->hasMany(OrderItem::class); }
public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
```

**Chữ ký đầy đủ + quy ước ngầm:**
```php
$this->hasMany(Related::class, $foreignKey = null, $localKey = null);
//                              │                   └─ cột khoá ở BẢNG NÀY, mặc định 'id'
//                              └─ cột khoá ngoại ở BẢNG CON, mặc định '<model_này>_id'
```
`Event::hasMany(TicketType)` mặc định tìm cột `ticket_types.event_id` khớp `events.id`. Tên `event_id` được suy từ **tên model cha** (`Event` → `event_id`), **không** phải từ tên method. Vì thế `Order::items()` (method tên `items`, không phải `orderItems`) vẫn đúng — Eloquent dùng `order_id` vì model cha là `Order`.

---

### 6.2. `belongsTo` — "tôi là con, tôi giữ khoá ngoại" (nhiều → một)

🟢 Trong dự án (đây là loại nhiều nhất):
```php
// TicketType.php
/** @return BelongsTo<Event, $this> */
public function event(): BelongsTo { return $this->belongsTo(Event::class); }

// Order.php
public function user(): BelongsTo { return $this->belongsTo(User::class); }
public function event(): BelongsTo { return $this->belongsTo(Event::class); }

// OrderItem.php
public function order(): BelongsTo { return $this->belongsTo(Order::class); }
public function ticketType(): BelongsTo { return $this->belongsTo(TicketType::class); }

// Ticket.php — thuộc về 4 model cha cùng lúc
public function order(): BelongsTo { return $this->belongsTo(Order::class); }
public function ticketType(): BelongsTo { return $this->belongsTo(TicketType::class); }
public function event(): BelongsTo { return $this->belongsTo(Event::class); }
public function user(): BelongsTo { return $this->belongsTo(User::class); }
```

**Chữ ký đầy đủ + một khác biệt quan trọng:**
```php
$this->belongsTo(Related::class, $foreignKey = null, $ownerKey = null);
//                               │                   └─ cột khoá ở BẢNG CHA, mặc định 'id'
//                               └─ cột khoá ngoại ở BẢNG NÀY, mặc định '<tên_method>_id'
```
⚠️ Với `belongsTo`, khoá ngoại suy từ **tên method**, không phải tên class. Đây là lý do method **phải** đặt đúng:
- `TicketType::event()` → tìm cột `event_id` ✓
- `OrderItem::ticketType()` → tìm cột `ticket_type_id` (camelCase `ticketType` → snake `ticket_type_id`) ✓

Nếu đổi tên method thành `type()` thì Eloquent lại tìm `type_id` (sai) — khi đó phải truyền tay `belongsTo(TicketType::class, 'ticket_type_id')`.

> **🧪 Chạy thử** — từ một hạng vé đi ngược lên sự kiện cha:
> ```
> >>> $t = App\Models\TicketType::first();
> >>> $t->name
> => "Vé thường"
> >>> $t->event               // property belongsTo → trả về MỘT đối tượng Event
> => App\Models\Event {#... title: "Live Concert 2026" ...}
> >>> $t->event->title        // đi thẳng sang thuộc tính của cha
> => "Live Concert 2026"
> ```

> **Nhớ nhanh sự bất đối xứng:** `hasMany`/`hasOne` lấy tên khoá ngoại từ **model cha**; `belongsTo` lấy từ **tên method**.

---

### 6.3. 🔹 `hasOne` — "tôi là cha, nhưng chỉ có một con" (một → một)
```php
// Ví dụ: User có 1 hồ sơ
/** @return HasOne<Profile, $this> */
public function profile(): HasOne { return $this->hasOne(Profile::class); }

// Lấy "một trong nhiều" theo tiêu chí — hasOne dựng trên hasMany:
public function latestOrder(): HasOne { return $this->hasOne(Order::class)->latestOfMany(); }
public function firstOrder(): HasOne  { return $this->hasOne(Order::class)->oldestOfMany(); }
public function biggestOrder(): HasOne{ return $this->hasOne(Order::class)->ofMany('total_amount', 'max'); }
```
Chữ ký giống `hasMany`. Khác biệt duy nhất: trả về **một** model (hoặc `null`) thay vì Collection.

---

### 6.4. 🔹 `belongsToMany` — nhiều ↔ nhiều (qua bảng pivot)

**Vì sao cần bảng thứ ba?** Lấy ví dụ User ↔ Role: một user có nhiều role, một role thuộc về nhiều user. Đặt `role_id` vào bảng `users` thì mỗi user chỉ chứa được **một** role; đặt `user_id` vào `roles` thì ngược lại. Không bảng nào chứa nổi quan hệ này → sinh ra **bảng trung gian (pivot)**, mỗi dòng của nó = một "sợi dây nối" giữa một user và một role:

```text
users            role_user (pivot)          roles
id | name        user_id | role_id          id | name
1  | An     ◄──     1    |    1      ──►    1  | admin
2  | Bình   ◄──     1    |    2      ──►    2  | editor
                    2    |    2      ──►    (An có 2 role; role editor có 2 người)
```
Muốn "An thêm role editor"? Thêm 1 dòng pivot. Muốn gỡ? Xoá dòng đó. Hai bảng chính không bị đụng gì — đó là toàn bộ ý tưởng.

```php
// User ↔ Role qua bảng pivot 'role_user'
/** @return BelongsToMany<Role, $this> */
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class)   // pivot mặc định: tên 2 model, số ít, xếp abc → role_user
        ->withPivot('assigned_at', 'expires_at')  // lấy thêm cột trên pivot
        ->withTimestamps()                        // pivot có created_at/updated_at
        ->as('membership');                       // đổi tên truy cập pivot: $role->membership
}
```
**Quy ước pivot:** tên bảng = ghép hai model (số ít, snake_case, xếp thứ tự chữ cái) → `role_user` (không phải `user_role`). Cột khoá: `role_id`, `user_id`.

**Chữ ký đầy đủ** (khi lệch quy ước):
```php
$this->belongsToMany(Related::class,
    $table = null,             // tên bảng pivot
    $foreignPivotKey = null,   // khoá của model NÀY trong pivot
    $relatedPivotKey = null,   // khoá của model KIA trong pivot
    $parentKey = null, $relatedKey = null);
```
🔹 Muốn pivot có logic riêng: tạo model pivot kế thừa `Pivot` rồi `->using(RoleUser::class)`.

---

### 6.5. Hai chiều là hai khai báo độc lập

Một quan hệ vật lý (một cột khoá ngoại) cần khai **hai lần** — mỗi phía một method. Chúng độc lập; đặt tên riêng cho từng phía:
```php
// events.id ◄── orders.event_id
class Event { public function orders(): HasMany  { return $this->hasMany(Order::class); } }  // cha → con
class Order { public function event(): BelongsTo { return $this->belongsTo(Event::class); } } // con → cha
```
Không bắt buộc khai cả hai — chỉ khai chiều nào bạn thật sự dùng. Dự án khai đầy đủ hai chiều cho các cặp hay truy vấn (Event↔Order, Order↔OrderItem…).

---

### 6.6. 🔹 `hasManyThrough` / `hasOneThrough` — đi xuyên một model trung gian

**Tình huống:** bạn đứng ở `Event`, muốn lấy các `OrderItem` — nhưng `order_items` **không có** cột `event_id`. Nó chỉ nối tới `ticket_types`, và `ticket_types` mới nối tới `events`. Tức là dữ liệu cách bạn **hai bước**:

```text
events ◄──event_id── ticket_types ◄──ticket_type_id── order_items
 (bạn đứng đây)        (trung gian)                     (thứ bạn muốn lấy)
```

Không có `hasManyThrough`, bạn phải tự lặp: lấy từng hạng vé, gom order_items của từng cái. Với `hasManyThrough`, khai một lần là Eloquent tự JOIN xuyên qua bảng giữa:
```php
// Event → (qua TicketType) → OrderItem
/** @return HasManyThrough<OrderItem, TicketType, $this> */
public function orderItems(): HasManyThrough
{
    return $this->hasManyThrough(OrderItem::class, TicketType::class);
    //                            ↑ đích (muốn lấy)   ↑ trung gian (đi xuyên qua)
}
// Điều kiện để quy ước tự chạy: ticket_types.event_id (trung gian trỏ về cha)
//                             + order_items.ticket_type_id (đích trỏ về trung gian)

$event->orderItems;   // dùng y hệt hasMany — Collection các OrderItem, dù cách 2 bảng
```
`hasOneThrough` giống hệt nhưng trả về **một** bản ghi (vd `Mechanic → Car → Owner`: thợ máy có một chủ xe, xuyên qua chiếc xe).

> Dự án không dùng, vì đã có sẵn `Ticket` mang thẳng `event_id`/`user_id` (phi chuẩn hoá có chủ đích) nên soát vé/"vé của tôi" query trực tiếp, khỏi through.

### 6.7. 🔹 Quan hệ đa hình (polymorphic) — một con gắn nhiều loại cha

**Tình huống:** làm tính năng bình luận cho cả *bài viết* lẫn *video*. Theo lối thường phải có `comments.post_id` **và** `comments.video_id` — mỗi loại cha mới lại thêm cột, xấu dần. Lối đa hình thay bằng **một cặp cột**: `*_id` (id của cha) + `*_type` (cha đó là **class nào**):

```text
comments
id | body        | commentable_id | commentable_type
1  | "Hay quá"   |       7        | App\Models\Post    ← bình luận của Post số 7
2  | "Tuyệt!"    |       7        | App\Models\Video   ← bình luận của Video số 7 (id trùng nhau
3  | "..."       |       12       | App\Models\Post       không sao — cột _type phân biệt)
```

---

Khai báo — chiều cha dùng `morphMany` (kèm tên "vai" `commentable`), chiều con dùng `morphTo`:
```php
class Comment { public function commentable(): MorphTo { return $this->morphTo(); } }
class Post  { public function comments(): MorphMany { return $this->morphMany(Comment::class, 'commentable'); } }
class Video { public function comments(): MorphMany { return $this->morphMany(Comment::class, 'commentable'); } }
```
Dùng:
```php
$post->comments;          // các comment của bài viết (Eloquent tự lọc _id = 7 AND _type = Post)
$comment->commentable;    // trả về cha ĐÚNG LOẠI: khi là Post, khi là Video — Eloquent đọc cột _type để biết
```
Tên `commentable` do bạn chọn (quy ước: động-từ-able), và nó quyết định tên hai cột `commentable_id`/`commentable_type` trong migration (`$table->morphs('commentable')`).

Các biến thể: `morphOne` (1-1 đa hình), `morphMany` (1-nhiều), `morphTo` (chiều con), `morphToMany`/`morphedByMany` (nhiều-nhiều đa hình, vd tags gắn được cho mọi loại nội dung).

---

### 6.8. Tinh chỉnh ngay trong khai báo

Định nghĩa quan hệ thực chất là một câu truy vấn (§7.1), nên bạn nối thêm điều kiện/hành vi **ngay trong khai báo** — mọi nơi dùng quan hệ đó đều tự hưởng:

**Lọc sẵn** — quan hệ "hẹp" hơn quan hệ gốc, đặt tên riêng:
```php
public function orders(): HasMany     { return $this->hasMany(Order::class); }
public function paidOrders(): HasMany { return $this->hasMany(Order::class)->where('status', 'paid'); }
// $event->orders → tất cả đơn;  $event->paidOrders → chỉ đơn đã thanh toán. Hai method sống song song.
```

🔹 **`withDefault()`** — vá lỗi `null` kinh điển. `$ticket->order` mà đơn không tồn tại → `null` → `$ticket->order->user` nổ *"attempt to read property on null"*. `withDefault()` bảo Eloquent: không tìm thấy thì trả một **object rỗng** thay vì null:
```php
public function user(): BelongsTo { return $this->belongsTo(User::class)->withDefault(); }
// → $order->user KHÔNG BAO GIỜ null; không có thật thì là User rỗng (mọi thuộc tính null)

public function user(): BelongsTo { return $this->belongsTo(User::class)->withDefault(['name' => 'Khách']); }
// → object rỗng nhưng điền sẵn name — view cứ in $order->user->name, ra "Khách" nếu đơn vô chủ
```

🔹 **Sắp xếp mặc định** — mọi nơi đọc `$event->ticketTypes` đều nhận danh sách đã xếp theo giá, khỏi lặp `orderBy` ở từng chỗ:
```php
public function ticketTypes(): HasMany { return $this->hasMany(TicketType::class)->orderBy('price'); }
```

🔹 **`chaperone()`** — chống "N+1 ngược". Tình huống: eager load `Order::with('items')` rồi trong vòng lặp lại đi ngược `$item->order` — Eloquent không biết item này vừa được nạp từ order nào, nên **query lại** cha cho từng item (N+1 theo chiều ngược). `chaperone()` bảo Eloquent lúc eager load hãy **gắn sẵn cha vào từng con**:
```php
public function items(): HasMany { return $this->hasMany(OrderItem::class)->chaperone(); }
// Giờ sau Order::with('items'), gọi $item->order lấy ngay từ bộ nhớ — 0 query thêm
```

---

### 6.9. Import & convention PHPDoc

Mỗi loại quan hệ là một class riêng, cần `use` tương ứng (thấy ở đầu mỗi model dự án):
```php
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;      // ... MorphOne, MorphMany
```
Convention PHPDoc của dự án — luôn ghi generic để IDE/phpstan hiểu kiểu:
```php
/** @return HasMany<TicketType, $this>  */   // <Model đích, $this>
/** @return BelongsTo<Event, $this>     */
```

---

### 6.10. Bảng tổng hợp — toàn bộ quan hệ trong dự án

| Model | Method | Loại | Trỏ tới | Cột khoá ngoại |
|---|---|---|---|---|
| `Event` | `ticketTypes()` | hasMany | `TicketType` | `ticket_types.event_id` |
| `Event` | `orders()` | hasMany | `Order` | `orders.event_id` |
| `TicketType` | `event()` | belongsTo | `Event` | `ticket_types.event_id` |
| `TicketType` | `orderItems()` | hasMany | `OrderItem` | `order_items.ticket_type_id` |
| `Order` | `user()` | belongsTo | `User` | `orders.user_id` |
| `Order` | `event()` | belongsTo | `Event` | `orders.event_id` |
| `Order` | `items()` | hasMany | `OrderItem` | `order_items.order_id` |
| `Order` | `tickets()` | hasMany | `Ticket` | `tickets.order_id` |
| `OrderItem` | `order()` | belongsTo | `Order` | `order_items.order_id` |
| `OrderItem` | `ticketType()` | belongsTo | `TicketType` | `order_items.ticket_type_id` |
| `Ticket` | `order()` `ticketType()` `event()` `user()` | belongsTo ×4 | 4 model | `tickets.{order,ticket_type,event,user}_id` |
| `User` | `orders()` | hasMany | `Order` | `orders.user_id` |
| `User` | `tickets()` | hasMany | `Ticket` | `tickets.user_id` |

---

## 7. Quan hệ — cách DÙNG

Ở §6 bạn đã **khai báo** quan hệ (viết các method `ticketTypes()`, `event()`…). Mục này trả lời câu hỏi tiếp theo: **khai xong rồi thì trong code viết gì để lấy/tạo dữ liệu liên quan?**

### 7.1. Khác biệt quan trọng nhất: đọc bằng "property" hay bằng "method có `()`"

Cùng một quan hệ `ticketTypes` bạn viết được **hai kiểu**, và chúng làm **hai việc khác nhau**. Đây là chỗ người mới nhầm nhiều nhất, nên đi thật chậm.

**Kiểu 1 — property (không có ngoặc): `$event->ticketTypes`**

Viết như đọc một thuộc tính bình thường. Khi bạn viết dòng này, Eloquent **âm thầm chạy một câu SQL** để lấy tất cả hạng vé của sự kiện, rồi trả về danh sách kết quả:

```php
$event = Event::find(5);        // lấy sự kiện id = 5
$list  = $event->ticketTypes;   // ← ở ĐÂY Eloquent chạy: SELECT * FROM ticket_types WHERE event_id = 5
```

`$list` bạn nhận được là một **Collection** — hãy hình dung nó như một *mảng "xịn"*: vẫn là danh sách các đối tượng `TicketType`, nhưng có sẵn nhiều hàm tiện lợi (`count()`, `map()`, `filter()`, `sum()`…). Bạn duyệt nó như mảng thường:

```php
foreach ($event->ticketTypes as $type) {
    echo $type->name;      // "Vé thường", "Vé VIP", ...
    echo $type->price;
}
echo $event->ticketTypes->count();   // ví dụ: 3 (có 3 hạng vé)
```

Một điểm hay: Eloquent **nhớ kết quả lại (cache)**. Lần đầu chạm `$event->ticketTypes` nó query DB; các lần sau trong cùng request nó trả lại danh sách đã lấy, **không** query lại. Nên đừng ngại gọi `$event->ticketTypes` nhiều lần.

**Kiểu 2 — method (có ngoặc): `$event->ticketTypes()`**

Khi thêm `()`, bạn **chưa** lấy dữ liệu. Thứ trả về là một **câu truy vấn đang xây dở** (gọi là *query builder*) — một "câu SQL chưa hoàn thành" mà bạn có thể **nối thêm điều kiện** rồi mới bảo nó chạy:

```php
// "Lấy các hạng vé của sự kiện này, NHƯNG chỉ hạng giá dưới 5000, sắp theo giá"
$cheap = $event->ticketTypes()          // câu SQL dở: ... WHERE event_id = 5
    ->where('price', '<', 5000)         // nối thêm: AND price < 5000
    ->orderBy('price')                  // nối thêm: ORDER BY price
    ->get();                            // ← ->get() mới thật sự chạy SQL, trả Collection
```

Không có `->get()` (hay `->first()`, `->count()`, `->sum()`…) ở cuối thì **SQL chưa chạy** — bạn chỉ đang mô tả câu truy vấn.

**Quy tắc nhớ đơn giản:**
> • Chỉ cần **lấy dữ liệu như-nó-vốn-có** → dùng **property**: `$event->ticketTypes`.
> • Cần **lọc/sắp xếp thêm, hoặc tạo/ghi** → dùng **method có `()`** rồi nối lệnh: `$event->ticketTypes()->where(...)->get()`.

Ví dụ thật trong dự án cho thấy cả hai:
```php
// TicketController::index — chỉ cần danh sách → property (qua $ticket->event, $ticket->ticketType trong view)
// TicketType::reservedQuantity — cần lọc thêm điều kiện → method có ()
$this->orderItems()                     // method: mở câu truy vấn các dòng hàng của hạng vé này
    ->whereHas('order', ...)            // nối điều kiện (§9)
    ->sum('quantity');                  // chạy và cộng dồn
```

> **🧪 Chạy thử**
> ```
> >>> $e = App\Models\Event::first();
> >>> $e->title
> => "Live Concert 2026"
>
> >>> $e->ticketTypes            // PROPERTY → chạy query luôn, trả về Collection
> => Illuminate\Database\Eloquent\Collection {#... items: [ TicketType {name: "Vé thường"...}, TicketType {name: "Vé VIP"...} ]}
>
> >>> $e->ticketTypes->count()   // Collection có sẵn count()
> => 2
>
> >>> $e->ticketTypes->pluck('name')->all()   // lấy riêng cột name
> => ["Vé thường", "Vé VIP"]
>
> >>> $e->ticketTypes()->where('price', '>', 10000)->get()->pluck('name')->all()
> => ["Vé VIP"]              // METHOD + nối where → chỉ hạng giá > 10000
> ```

### 7.2. Đọc quan hệ chiều "thuộc về" (belongsTo)

Với `belongsTo`, property trả về **một** đối tượng (không phải danh sách), hoặc `null` nếu không có:

```php
$order = Order::find(10);
$order->user;          // đối tượng User đã mua đơn này (chạy: SELECT * FROM users WHERE id = orders.user_id)
$order->user->name;    // tên người mua
$order->event->title;  // đi tiếp sang sự kiện của đơn
```

Bạn có thể "đi xuyên" nhiều quan hệ bằng cách nối dấu `->`:
```php
$ticket->order->user->email;   // vé → đơn của vé → người mua → email
```
⚠️ Nếu một mắt xích giữa chừng là `null` (vd đơn chưa có user) thì dòng này lỗi. Khi không chắc, dùng toán tử `?->`: `$ticket->order?->user?->email` (mắt nào null thì cả biểu thức trả null, không lỗi).

### 7.3. Tạo bản ghi con **qua** quan hệ (tiện hơn tạo trực tiếp)

Đây là cách dự án tạo các dòng hàng của đơn. So sánh hai lối để thấy vì sao đi qua quan hệ tiện hơn:

```php
// ❌ Lối thủ công: phải TỰ NHỚ gán order_id
OrderItem::create([
    'order_id'       => $order->id,   // ← dễ quên, dễ gán nhầm
    'ticket_type_id' => 5,
    'quantity'       => 2,
    'unit_price'     => 3000,
]);

// ✅ Lối qua quan hệ: Eloquent TỰ điền order_id = $order->id cho bạn
$order->items()->create([
    'ticket_type_id' => 5,
    'quantity'       => 2,
    'unit_price'     => 3000,
]);
```

Vì bạn gọi `create()` **trên** `$order->items()`, Eloquent biết bản ghi mới này thuộc về `$order`, nên tự thêm `order_id`. Bớt một cột phải nhớ, và không thể gán nhầm sang đơn khác.

**Tạo nhiều bản ghi một lúc — `createMany()`** (đây là code thật trong `OrderController::store`):
```php
$items = [
    ['ticket_type_id' => 5, 'quantity' => 2, 'unit_price' => 3000],
    ['ticket_type_id' => 6, 'quantity' => 1, 'unit_price' => 8000],
];
$order->items()->createMany($items);   // tạo 2 dòng OrderItem, cả hai tự có order_id = $order->id
```

**Các cách tạo/gắn khác** (chưa dùng trong dự án, nêu để biết):
```php
$item = new OrderItem([...]);
$order->items()->save($item);          // gắn 1 model đã tạo sẵn (chưa lưu) vào đơn rồi lưu
$order->items()->saveMany([$a, $b]);   // gắn nhiều model đã tạo sẵn

// Ở chiều ngược (belongsTo): gán/gỡ "cha" cho một bản ghi con
$comment->post()->associate($post);    // đặt post_id của comment = $post->id (chưa lưu, cần ->save())
$comment->post()->dissociate();        // xoá post_id (đặt về null)
```
Khác nhau: `create()` nhận **mảng dữ liệu** và tự tạo+lưu; `save()` nhận **một model đã dựng sẵn**; `associate()` chỉ **gán khoá ngoại** ở chiều belongsTo.

### 7.4. 🔹 Quan hệ nhiều-nhiều (belongsToMany) — attach / detach / sync

(Dự án không có, nhưng rất hay gặp — ví dụ: một bài viết có nhiều thẻ *tag*, một tag gắn nhiều bài.) Vì liên kết nằm ở **bảng trung gian** (§6.4), ta thao tác bằng bộ hàm riêng:

```php
$post->tags()->attach($tagId);          // THÊM một liên kết (thêm 1 dòng vào bảng trung gian)
$post->tags()->attach([1, 2]);          // thêm nhiều
$post->tags()->attach([1, 2], ['added_at' => now()]); // kèm dữ liệu cho cột phụ của bảng trung gian

$post->tags()->detach($tagId);          // GỠ một liên kết
$post->tags()->detach();                // gỡ TẤT CẢ

$post->tags()->sync([1, 2, 3]);         // ĐỒNG BỘ: sau lệnh này bài chỉ còn đúng tag 1,2,3
                                        //   (tự thêm cái thiếu, tự gỡ cái thừa)
$post->tags()->toggle([1, 2]);          // ĐẢO: tag nào đang có thì gỡ, chưa có thì thêm
```
Dễ nhớ: `attach`/`detach` = thêm/bớt **từng cái**; `sync` = ép danh sách về **đúng tập bạn đưa**.

### 7.5. 🔹 Tìm-hoặc-tạo qua quan hệ

Khi muốn "nếu chưa có thì tạo, có rồi thì thôi/hoặc cập nhật":
```php
// Tìm dòng hàng của hạng vé 5 trong đơn này; chưa có thì tạo mới với quantity = 2
$order->items()->firstOrCreate(
    ['ticket_type_id' => 5],   // điều kiện TÌM
    ['quantity' => 2],         // dữ liệu dùng khi phải TẠO
);

// Như trên, nhưng nếu đã có thì CẬP NHẬT quantity = 2
$order->items()->updateOrCreate(
    ['ticket_type_id' => 5],   // điều kiện tìm
    ['quantity' => 2],         // giá trị cập nhật hoặc tạo
);
```
Cả hai đều tự thêm `order_id` (vì đi qua `$order->items()`). Khác nhau: `firstOrCreate` **không** đụng bản ghi đã có; `updateOrCreate` thì **ghi đè** bản ghi đã có.

---

## 8. Eager Loading — nạp trước quan hệ để không chậm (chống "N+1")

### 8.1. Vấn đề "N+1" là gì — kể bằng ví dụ cụ thể

Giả sử bạn muốn in danh sách 100 đơn kèm tên người mua:
```php
$orders = Order::all();          // (1) một query lấy 100 đơn
foreach ($orders as $order) {
    echo $order->user->name;     // (2) MỖI vòng lặp lại chạm $order->user → thêm 1 query
}
```
Chuyện gì xảy ra với DB?
- Dòng (1): **1 query** lấy 100 đơn.
- Dòng (2): vì `$order->user` là property và Eloquent chưa có sẵn user, nó phải query để lấy — và điều này lặp **100 lần**, mỗi đơn một query.

Tổng: **1 + 100 = 101 query**. Đây gọi là bài toán **"N+1"** (1 query gốc + N query con). Với danh sách lớn, trang sẽ **rất chậm** dù logic trông vô hại.

### 8.2. Cách chữa: "eager loading" — bảo Eloquent nạp trước

*Eager loading* nghĩa là "nạp sẵn quan hệ ngay từ đầu" thay vì để nó query lắt nhắt sau. Chỉ cần thêm `with('user')`:

```php
$orders = Order::with('user')->get();   // lấy 100 đơn VÀ gom sẵn user của chúng
foreach ($orders as $order) {
    echo $order->user->name;            // KHÔNG query nữa — user đã có sẵn trong bộ nhớ
}
```
Giờ DB chỉ chạy **2 query**: một lấy đơn, một lấy tất cả user liên quan cùng lúc (`... WHERE id IN (danh sách user_id)`). Từ 101 xuống 2 — đó là toàn bộ ý nghĩa của eager loading.

> **🧪 Chạy thử** — tự đếm số query với bộ dữ liệu seed (3 sự kiện):
> ```
> >>> DB::enableQueryLog();                                  // bật ghi log query
> >>> foreach (App\Models\Event::all() as $e) { $e->ticketTypes->count(); }
> >>> count(DB::getQueryLog())
> => 4                          // LAZY: 1 query lấy sự kiện + 3 query (mỗi sự kiện 1) = N+1
>
> >>> DB::flushQueryLog();
> >>> foreach (App\Models\Event::with('ticketTypes')->get() as $e) { $e->ticketTypes->count(); }
> >>> count(DB::getQueryLog())
> => 2                          // EAGER: 1 query sự kiện + 1 query gom hết ticketTypes
> ```
> Dữ liệu nhỏ nên chỉ 4 vs 2; với 100 sự kiện sẽ là 101 vs 2 — khác biệt khổng lồ.

### 8.3. `with` và `load` — khác nhau ở thời điểm

Cùng làm một việc (nạp trước quan hệ), chỉ khác lúc gọi:

```php
// with(...) — dùng NGAY trong lúc truy vấn (khi bạn đang lấy danh sách)
$tickets = Ticket::with(['event', 'ticketType'])->get();   // TicketController::index (code thật)

// load(...) — dùng khi bạn ĐÃ CÓ sẵn object/danh sách rồi, giờ mới muốn nạp thêm quan hệ
$order = Order::find(10);                    // đã có $order
$order->load(['event', 'items', 'tickets']); // giờ nạp thêm các quan hệ cho nó
```
Trong dự án, `OrderController::show` dùng `load` vì `$order` đã được Laravel nạp sẵn từ URL (Route Model Binding), giờ chỉ bổ sung quan hệ để hiển thị.

### 8.4. Nạp nhiều tầng bằng dấu chấm `.`

Muốn nạp quan hệ **của quan hệ**, nối bằng dấu `.`:
```php
$order->load(['items.ticketType', 'tickets.ticketType']);   // code thật OrderController::show
```
Đọc là: nạp `items` (các dòng hàng của đơn), **và với mỗi item**, nạp tiếp `ticketType` (hạng vé của nó). Nhờ vậy trong view bạn viết `$item->ticketType->name` mà không sinh thêm query nào.

### 8.5. Chỉ cần **đếm**, đừng kéo cả danh sách: `withCount`

Nếu bạn chỉ muốn biết *"sự kiện này có bao nhiêu hạng vé"* chứ không cần chi tiết từng hạng, kéo cả danh sách ra là phí. Dùng `withCount`:
```php
$events = Event::withCount('ticketTypes')->get();   // EventController::index (code thật)
foreach ($events as $event) {
    echo $event->ticket_types_count;   // ← Eloquent tạo sẵn thuộc tính "<quan hệ>_count"
}
```
Nó chỉ chạy `SELECT COUNT(*)` cho quan hệ, nhanh và nhẹ hơn nhiều so với nạp toàn bộ bản ghi con.

> **🧪 Chạy thử**
> ```
> >>> App\Models\Event::withCount('ticketTypes')->get()->pluck('ticket_types_count', 'title')->all()
> => [
>      "Live Concert 2026" => 2,
>      "Tech Expo" => 1,
>      "Bí mật (chưa công bố)" => 0,
>    ]
> ```

### 8.6. Bảng tra các hàm cùng họ

| Hàm | Dùng khi | Kết quả nhận được |
|---|---|---|
| `with(['a', 'b.c'])` | đang truy vấn danh sách | quan hệ được nạp sẵn; `.` = nạp lồng nhiều tầng |
| `load([...])` | đã có object rồi | như `with` nhưng gọi sau |
| `loadMissing([...])` | đã có object | chỉ nạp quan hệ **chưa** được nạp (tránh nạp lại thừa) — dự án dùng ở webhook/check-in |
| `withCount('rel')` | cần con số khi truy vấn | thêm thuộc tính `rel_count` |
| `loadCount('rel')` | cần con số, object đã có | như trên nhưng gọi sau |
| `withSum('items','quantity')` | cần tổng/trung bình/max/min | thêm `items_sum_quantity` (tương tự `withAvg`/`withMax`/`withMin`) |
| `with(['rel' => fn ($q) => $q->where(...)])` | nạp nhưng lọc bớt con | chỉ nạp các bản ghi con thoả điều kiện |

### 8.7. 🔹 Mẹo: bắt lỗi N+1 tự động khi lập trình

Để không lỡ quên `with` và vô tình gây N+1, bật "cấm lazy loading" ở môi trường dev — khi đó quên `with` sẽ **báo lỗi ngay** thay vì âm thầm chạy chậm:
```php
// đặt trong AppServiceProvider::boot()
Model::preventLazyLoading(! app()->isProduction());   // chỉ bật ngoài production
```

---

## 9. Truy vấn theo quan hệ — lọc "cha" dựa trên "con"

Đôi khi bạn muốn lọc bản ghi cha **theo đặc điểm của con nó**, ví dụ: *"lấy các sự kiện CÓ ít nhất một hạng vé"*, hay *"các sự kiện KHÔNG có hạng vé nào"*. Đó là việc của `has` / `doesntHave` / `whereHas`.

**`has` / `doesntHave`** — lọc theo việc *có tồn tại* con hay không:
```php
Event::has('ticketTypes')->get();          // sự kiện có ≥ 1 hạng vé
Event::has('ticketTypes', '>=', 2)->get(); // sự kiện có ≥ 2 hạng vé
Event::doesntHave('ticketTypes')->get();   // sự kiện KHÔNG có hạng vé nào
```

> **🧪 Chạy thử** — 3 sự kiện, chỉ "Bí mật" là chưa có hạng vé:
> ```
> >>> App\Models\Event::has('ticketTypes')->count()
> => 2
> >>> App\Models\Event::doesntHave('ticketTypes')->count()
> => 1
> ```

**`whereHas`** — mạnh hơn: lọc theo con **thoả một điều kiện cụ thể**. Đây là lõi của quy tắc chống bán quá số (`TicketType::reservedQuantity`) — chỉ cộng những dòng hàng thuộc đơn "đã trả tiền hoặc đang giữ chỗ":
```php
$this->orderItems()
    ->whereHas('order', function (Builder $q): void {   // chỉ tính item mà ĐƠN của nó thoả điều kiện
        $q->where('status', Order::STATUS_PAID)
          ->orWhere(fn (Builder $q) => $q->where('status', Order::STATUS_PENDING)
                                          ->where('expires_at', '>', now()));
    })
    ->sum('quantity');
```

Bảng đầy đủ:
| Hàm | Nghĩa |
|---|---|
| `has('items')` | có ít nhất 1 item |
| `has('items', '>=', 3)` | có ≥ 3 item |
| `doesntHave('items')` | không có item nào |
| `whereHas('items', fn($q)=>...)` | 🟢 có item thoả điều kiện |
| `whereDoesntHave('items', ...)` | không có item nào thoả |
| `orWhereHas(...)` | nối OR |
| `withWhereHas('items', ...)` | vừa lọc vừa eager load luôn |

---

## 10. Scope — đặt tên cho một điều kiện lọc hay dùng

### 10.0. Vấn đề: cùng một điều kiện bị lặp khắp nơi

"Sự kiện đã công bố" nghĩa là: có `published_at` khác null **và** `published_at` đã tới (≤ hiện tại). Điều kiện này cần ở nhiều chỗ (trang danh sách, trang chi tiết…). Nếu chép đi chép lại:
```php
Event::whereNotNull('published_at')->where('published_at', '<=', now())->get();  // lặp ở mọi nơi 😫
```
thì sau này đổi định nghĩa "đã công bố" phải sửa hàng chục chỗ. **Scope** cho phép gói điều kiện đó lại, đặt một cái tên, rồi gọi tên ngắn gọn ở mọi nơi.

### 10.1. Local scope — viết một lần, gọi bằng tên

🟢 **Cách truyền thống (dự án dùng):** viết một method tên bắt đầu bằng `scope`, nhận `$query` và nối điều kiện vào đó. Khi gọi thì **bỏ chữ `scope`** và viết thường chữ đầu:
```php
// Event.php — ĐỊNH NGHĨA: tên method là scopePublished
public function scopePublished(Builder $query): void
{
    $query->whereNotNull('published_at')->where('published_at', '<=', now());
}
```
```php
// GỌI ở khắp nơi — chỉ cần published(), Laravel tự map về scopePublished:
Event::published()->get();               // EventController::index (code thật)
Event::published()->where('venue', 'Tokyo Dome')->get();   // vẫn nối thêm điều kiện khác được
```
Đổi định nghĩa "đã công bố" sau này? Chỉ sửa **một chỗ** trong `scopePublished`.

> **🧪 Chạy thử** — DB có 3 sự kiện nhưng 1 cái chưa công bố:
> ```
> >>> App\Models\Event::count()                    // tổng tất cả
> => 3
> >>> App\Models\Event::published()->count()       // chỉ cái đã công bố
> => 2
> >>> App\Models\Event::published()->pluck('title')->all()
> => ["Live Concert 2026", "Tech Expo"]            // "Bí mật (chưa công bố)" bị loại
> ```

🔹 **Lối attribute v13** — gắn `#[Scope]`, khỏi tiền tố `scope`:
```php
use Illuminate\Database\Eloquent\Attributes\Scope;

#[Scope]
protected function published(Builder $query): void
{
    $query->whereNotNull('published_at')->where('published_at', '<=', now());
}
// dùng y hệt: Event::published()->get();
```
Hai lối tương đương; dự án dùng lối `scopePublished` truyền thống.

**Scope có tham số:**
```php
public function scopeOfStatus(Builder $query, string $status): void { $query->where('status', $status); }
// dùng: Order::ofStatus('paid')->get();
```

### 10.2. 🔹 Global scope

Khác local scope một chữ quan trọng: local scope phải **gọi mới chạy** (`Event::published()`), còn global scope **tự áp vào MỌI truy vấn** của model — kể cả `Event::all()`, `Event::find()`, cả các quan hệ trỏ tới nó, mà không cần gọi gì.

Dùng khi có một điều kiện phải **đúng ở mọi nơi, quên là ra bug/lộ dữ liệu**: chỉ lấy bản ghi active, chỉ lấy dữ liệu của tenant đang đăng nhập… (Soft delete §14.1 chính là một global scope dựng sẵn: tự thêm `WHERE deleted_at IS NULL` khắp nơi.)

Mặt trái: điều kiện trở nên **vô hình** — người đọc `Event::all()` không thấy có lọc gì, dễ ngồi cả buổi thắc mắc "sao thiếu bản ghi?". Vì vậy chỉ dùng cho điều kiện thật sự phổ quát; còn lại cứ local scope cho tường minh.

Hai cách khai:
```php
// (a) class + gắn bằng attribute
#[ScopedBy([PublishedScope::class])]
class Event extends Model {}

// (b) closure trong booted()
protected static function booted(): void
{
    static::addGlobalScope('published', fn (Builder $q) => $q->whereNotNull('published_at'));
}
```
Bỏ qua khi cần: `Event::withoutGlobalScope('published')->get();`

---

## 11. Method, hằng số trong model — gói logic vào chính đối tượng

Model không chỉ chứa dữ liệu; bạn thêm được **method** (hàm) vào nó để trả lời câu hỏi hoặc tính toán về **chính bản ghi đó**. Bên trong method, `$this` là bản ghi hiện tại — `$this->price`, `$this->status`… là các cột của nó. Ở mức 1, các logic nhỏ đặt thẳng trong model (QĐ-1.2), thay vì rải rác ngoài controller.

🟢 **Method "hỏi trạng thái"** — trả về `true`/`false`, giúp code đọc như tiếng Anh:
```php
// Event.php — "sự kiện này đã công bố chưa?"
public function isPublished(): bool
{
    return $this->published_at !== null && $this->published_at->isPast();
}
// Order.php — "đơn này đã thanh toán chưa?"
public function isPaid(): bool { return $this->status === self::STATUS_PAID; }
// TicketType.php — "hạng vé này hết chưa?"
public function isSoldOut(): bool { return $this->remaining() <= 0; }
```
Nhờ vậy trong code viết `if ($order->isPaid())` — rõ nghĩa hơn `if ($order->status === 'paid')`.

🟢 **Method tính toán** — kết hợp cả truy vấn quan hệ:
```php
public function remaining(): int { return max(0, $this->quantity - $this->reservedQuantity()); }  // TicketType
public function subtotal(): int  { return $this->quantity * $this->unit_price; }                  // OrderItem
```

🟢 **Hằng số (`const`) cho tập giá trị cố định** — đây là cách level1 thay cho "enum". Đặt tên cho các chuỗi trạng thái để không gõ tay dễ sai:
```php
public const string STATUS_PENDING = 'pending';
public const string STATUS_PAID    = 'paid';
// dùng: $order->status === Order::STATUS_PAID   (gõ sai tên hằng số → lỗi ngay, an toàn hơn gõ 'paid')
```

> **🧪 Chạy thử** — gọi method ngay trên đối tượng lấy từ DB:
> ```
> >>> $t = App\Models\TicketType::first();
> >>> $t->name
> => "Vé thường"
> >>> $t->remaining()          // method tính toán: 100 vé, chưa ai mua
> => 100
> >>> $t->isSoldOut()          // method hỏi trạng thái
> => false
>
> >>> $e = App\Models\Event::first();
> >>> $e->isPublished()
> => true
> ```

---

## 12. Query & CRUD qua model

"CRUD" = **C**reate (tạo) / **R**ead (đọc) / **U**pdate (sửa) / **D**elete (xoá) — bốn thao tác cơ bản với dữ liệu. Đây là những lệnh bạn dùng hằng ngày.

### 12.1. Đọc dữ liệu (Read)

Hai nhóm cần phân biệt:
- Hàm trả về **một** bản ghi: `find`, `first`, `firstWhere`… (hoặc `null` nếu không có).
- Hàm trả về **danh sách** (Collection): `all`, `get`, `pluck`…

| Gọi | Trả về |
|---|---|
| `Event::all()` | tất cả (Collection) |
| `Event::find(5)` / `find([1,2])` | 1 model / Collection / `null` |
| `Event::findOrFail(5)` | model, hoặc ném lỗi 404 nếu không có |
| `Event::first()` / `firstOrFail()` | model đầu tiên |
| `Event::where('venue', 'X')->get()` | Collection các bản ghi khớp |
| `Event::where(...)->first()` | 1 model / null |
| `Event::firstWhere('venue', 'X')` | gọn cho `where(...)->first()` |
| `TicketType::value('name')` | 1 giá trị của 1 cột |
| `Event::pluck('title', 'id')` | Collection `[id => title]` |
| `Order::count()` / `sum('total_amount')` / `max(...)` | một con số |
| `Event::exists()` / `doesntExist()` | bool |

> **🧪 Chạy thử**
> ```
> >>> App\Models\Event::count()
> => 3
> >>> App\Models\Event::find(1)->title            // tìm theo id
> => "Live Concert 2026"
> >>> App\Models\Event::where('venue', 'Tokyo Dome')->pluck('title')->all()
> => ["Live Concert 2026"]
> >>> App\Models\TicketType::sum('quantity')       // tổng số vé của mọi hạng
> => 170                                           // 100 + 20 + 50
> >>> App\Models\Event::find(999)                  // không có → null (không lỗi)
> => null
> ```

### 12.2. Lọc & sắp xếp (Query Builder)

Các điều kiện **nối chuỗi** được thoải mái — mỗi `->where...` thêm một mệnh đề `AND` vào câu SQL, và chưa có gì chạy cho tới `->get()` (cùng nguyên lý "câu truy vấn xây dở" ở §7.1):
```php
Order::query()                                    // mở một câu truy vấn (viết Order::where(...) thẳng cũng được)
    ->where('status', 'pending')                  // status = 'pending'  (2 tham số → ngầm hiểu dấu =)
    ->whereIn('event_id', [1, 2, 3])              // event_id nằm trong danh sách
    ->whereNull('paid_at')                        // chưa thanh toán (cột IS NULL — chú ý: whereNull,
                                                  //   KHÔNG viết where('paid_at', null) — SQL so null kiểu riêng)
    ->whereBetween('total_amount', [1000, 5000])  // trong khoảng (gồm cả 2 đầu)
    ->where('expires_at', '<=', now())            // 3 tham số → tự chọn phép so sánh
    ->orderBy('created_at', 'desc')               // mới nhất trước; viết gọn: ->latest() (ngược lại: ->oldest())
    ->limit(10)
    ->get();                                      // ← đến đây SQL mới chạy
```
Muốn `OR` thay vì `AND`: `->orWhere(...)`; nhóm điều kiện trong ngoặc: truyền closure `->where(fn ($q) => $q->where(...)->orWhere(...))` — thấy dùng thật trong `TicketType::reservedQuantity` (§9).

🟢 `whereKey([...])` (lọc theo khoá chính — gọn cho `whereIn('id', ...)`), `latest()`, `paginate(12)` (chia trang: trả 12 bản ghi + tự đọc `?page=` từ URL) đều dùng trong dự án.

### 12.3. Duyệt dữ liệu lớn (tiết kiệm bộ nhớ)

**Vấn đề:** `Order::all()` kéo **toàn bộ** bảng vào RAM cùng lúc. 1 triệu đơn × vài KB mỗi object = script chết vì hết bộ nhớ. Ba cách xử lý từng phần thay vì ôm hết:

```php
// chunk — lấy TỪNG LÔ: query 200 dòng, xử lý, bỏ đi, query 200 dòng tiếp...
Order::chunk(200, function ($orders) {
    foreach ($orders as $order) { /* ... */ }
});   // RAM chỉ giữ tối đa 200 object một lúc; nhiều query nhỏ

// cursor — MỘT query, nhưng đọc từng dòng một từ kết quả (PHP generator)
Order::cursor()->each(fn ($o) => ...);   // RAM giữ đúng 1 object một lúc — tiết kiệm nhất

// lazy — lai giữa hai cái trên: bên trong vẫn chunk từng lô, nhưng bạn viết
// vòng lặp phẳng như duyệt collection thường (không phải viết callback lồng nhau)
foreach (Order::lazy() as $order) { /* ... */ }
```

Chọn thế nào: dữ liệu vừa phải → `all()`/`get()` cho đơn giản. Hàng chục nghìn dòng trở lên → `lazy()` (dễ viết nhất). Cần **vừa duyệt vừa update** chính các dòng đó → `chunkById(200, ...)` (an toàn hơn `chunk` vì phân trang theo id, không bị lệch trang khi dữ liệu đổi giữa chừng).

### 12.4. Tạo / sửa / xoá
```php
$order = Order::create([...]);            // 🟢 tạo (qua Fillable)
$order->update(['status' => 'paid']);     // 🟢 sửa nhiều cột
$order->status = 'paid'; $order->save();  // 🟢 sửa từng thuộc tính rồi lưu
$order->delete();                         // xoá
Order::destroy([1, 2, 3]);                // xoá theo id
Order::where('status','expired')->delete(); // xoá theo điều kiện
```

> **🧪 Chạy thử** — tạo → sửa → xoá một sự kiện (cuối cùng DB sạch như cũ):
> ```
> >>> $e = App\Models\Event::create([
> ...     'title' => 'Demo tạm', 'description' => 'thử', 'venue' => 'X',
> ...     'starts_at' => now()->addDay(), 'published_at' => now(),
> ... ]);
> >>> $e->id
> => 4                              // vừa tạo, có id mới
> >>> App\Models\Event::count()
> => 4
> >>> $e->update(['title' => 'Demo đã sửa']);
> >>> $e->fresh()->title            // fresh() = đọc lại từ DB
> => "Demo đã sửa"
> >>> $e->delete();
> >>> App\Models\Event::count()
> => 3                              // đã xoá, về lại như cũ
> ```
> Lưu ý: `create([...])` chỉ nhận các cột có trong `$fillable` (§2); cột lạ bị bỏ qua.

**Họ "tìm-hoặc-tạo"** — cho tình huống rất hay gặp: *"bản ghi này có thể đã tồn tại (vd webhook Stripe gọi trùng 2 lần), đừng tạo đôi"*. Cả họ đều nhận 2 mảng: **mảng 1 = điều kiện TÌM**, **mảng 2 = dữ liệu bổ sung khi phải TẠO/SỬA**:

```php
Order::firstOrCreate(
    ['stripe_session_id' => $id],   // TÌM theo cái này
    ['status' => 'pending'],        // không thấy → TẠO mới với cả 2 mảng gộp lại
);                                  // thấy rồi → trả bản ghi cũ NGUYÊN VẸN, mảng 2 bị bỏ qua

Order::firstOrNew([...]);           // như trên nhưng khi phải tạo thì CHƯA lưu DB
                                    //   (bạn chỉnh tiếp rồi tự ->save())
Order::updateOrCreate(
    ['stripe_session_id' => $id],   // tìm theo cái này
    ['status' => 'paid'],           // thấy → GHI ĐÈ các cột này; không thấy → tạo mới
);
```

| Hàm | Đã tồn tại thì… | Chưa tồn tại thì… |
|---|---|---|
| `firstOrCreate` | trả bản cũ, **không sửa gì** | tạo + lưu ngay |
| `firstOrNew` | trả bản cũ | dựng object, **chưa lưu** |
| `updateOrCreate` | **cập nhật** mảng 2 vào | tạo + lưu ngay |

```php
// upsert — bản "hàng loạt" của updateOrCreate: chèn/cập nhật nhiều dòng bằng MỘT query
Order::upsert(
    [['id' => 1, 'status' => 'paid'], ['id' => 2, 'status' => 'expired']],  // dữ liệu
    uniqueBy: ['id'],        // cột nhận diện "đã tồn tại"
    update: ['status'],      // tồn tại rồi thì chỉ ghi đè các cột này
);
// ⚠️ upsert đi thẳng xuống SQL: KHÔNG chạy model events (§13) và không đụng casts phức tạp
```

🟢 `update([...])` hàng loạt trong `ExpireStaleOrders`:
```php
Order::query()->where('status', Order::STATUS_PENDING)->where('expires_at','<=',now())
    ->update(['status' => Order::STATUS_EXPIRED, 'updated_at' => now()]);
```
🔹 Tăng/giảm nhanh:
```php
TicketType::where('id', 5)->increment('quantity', 10);
$order->decrement('total_amount', 500);
```

### 12.5. 🟢 Khoá bản ghi (concurrency) — dùng khắp dự án

**Vấn đề nó giải quyết — bán quá số vé.** Còn đúng **1 vé** cuối. Hai người bấm "Mua" cùng lúc, hai request chạy **song song**:

```text
Request A: đọc remaining() → 1  ✓ còn vé          Request B: đọc remaining() → 1  ✓ còn vé
Request A: tạo đơn, trừ vé                        Request B: tạo đơn, trừ vé
                        → BÁN 2 VÉ trong khi chỉ còn 1 😱
```
Cả hai đều "kiểm tra rồi mới mua", nhưng vì kiểm tra **cùng lúc** nên cùng thấy còn vé. Kiểm tra suông không đủ — phải **xếp hàng**.

**`lockForUpdate()`** làm đúng việc đó: *"tôi sắp sửa dòng này — ai khác muốn đụng vào thì ĐỢI tôi xong đã"*:
```php
DB::transaction(function () use ($ids) {
    $types = TicketType::query()->whereKey($ids)->lockForUpdate()->get();
    // ← Request B chạy đến đúng dòng này sẽ BỊ CHẶN ĐỨNG, đứng đợi...
    //   ...kiểm tra remaining(), tạo đơn...
});  // transaction kết thúc → nhả khoá → B mới được đọc, và giờ B thấy remaining() = 0 → từ chối ✓
```
Code thật trong dự án — cả 3 chỗ có nguy cơ tranh chấp:
```php
TicketType::query()->whereKey($ids)->lockForUpdate()->get();   // OrderController — chống bán quá vé
Order::query()->where(...)->lockForUpdate()->first();          // StripeWebhookController — chống webhook gọi trùng
Ticket::query()->where('token', $t)->lockForUpdate()->first(); // CheckInController — chống soát 1 vé 2 lần
// 🔹 ->sharedLock() — khoá "đọc chia sẻ": nhiều người cùng đọc được, nhưng cấm ai SỬA cho tới khi xong
```
Hai điều phải nhớ:
1. **Luôn đặt trong `DB::transaction(...)`** — khoá chỉ sống trong transaction; nằm ngoài thì được nhả ngay lập tức, coi như không khoá (chi tiết: `laravel-explained-level1.md` §11).
2. Khoá xong phải **đọc lại số liệu bên trong khoá** rồi mới quyết định — khoá mà vẫn dùng con số đọc từ trước khi khoá thì công cốc.

---

## 13. Model Events & Observer

🔹 (Chưa dùng trong dự án — level1 đẩy việc phụ qua controller; từ mức 2 sẽ dùng Event/Listener. Nhưng model events là thứ nên biết.)

**Ý tưởng:** mỗi khi một bản ghi được tạo/sửa/xoá, model phát ra "tín hiệu" — bạn đăng ký chạy code mỗi khi tín hiệu đó nổ, **bất kể** thao tác xuất phát từ controller nào. Dùng cho việc kiểu: tự sinh mã đơn trước khi lưu, ghi log mỗi lần xoá, xoá cache khi dữ liệu đổi.

**Dòng thời gian của một lần `save()`** — chú ý cặp `-ing` (TRƯỚC khi ghi DB) và `-ed` (SAU khi ghi):

```text
$order->save()  (bản ghi mới)
   │
   ├─ saving    ─┐ TRƯỚC khi ghi DB — sửa $order ở đây vẫn KỊP đưa vào DB,
   ├─ creating  ─┘ return false ở đây là HUỶ luôn thao tác
   │
   ├─── [INSERT vào DB] ───
   │
   ├─ created   ─┐ SAU khi ghi — bản ghi đã nằm trong DB, có id;
   └─ saved     ─┘ dùng cho việc ăn theo (gửi mail, ghi log...)
```
Bản ghi **cũ** đem sửa thì thay `creating/created` bằng `updating/updated`. Ngoài ra còn: `deleting/deleted`, `retrieved` (vừa đọc từ DB lên), `restoring/restored` (soft delete §14.1). `saving/saved` nổ cho **cả** tạo lẫn sửa.

**Cách 1 — closure trong `booted()`** (gọn, khi chỉ 1–2 việc nhỏ):
```php
class Order extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->reference ??= (string) Str::ulid();   // tự sinh mã TRƯỚC khi lưu
        });   // giờ mọi Order::create() ở bất cứ đâu đều tự có mã — không ai quên được
    }
}
```

**Cách 2 — Observer class** (gọn khi nghe nhiều sự kiện — gom hết ra một file riêng):
```bash
php artisan make:observer OrderObserver --model=Order
```
```php
class OrderObserver
{
    // tên method = tên sự kiện; Laravel tự gọi đúng lúc
    public function created(Order $order): void { /* vd: ghi log đơn mới */ }
    public function updated(Order $order): void { /* vd: xoá cache */ }
    public function deleted(Order $order): void { /* ... */ }
}
```
```php
// Gắn observer vào model — attribute v13 (hoặc kiểu cũ: Order::observe(OrderObserver::class) trong AppServiceProvider::boot())
#[ObservedBy([OrderObserver::class])]
class Order extends Model {}
```

⚠️ **Bẫy cần biết:** các lệnh đi thẳng xuống SQL **không** phát sự kiện — `Order::where(...)->update([...])` (update hàng loạt, như trong `ExpireStaleOrders`), `upsert()`, `Order::destroy()` theo điều kiện. Sự kiện chỉ nổ khi thao tác **qua một instance model** (`$order->save()`, `$order->update()`, `$order->delete()`).

---

## 14. Soft Deletes, Timestamps, và các trait khác

### 14.1. 🔹 Soft Deletes — xoá mềm (giữ lại, chỉ đánh dấu)

**Vì sao cần:** `delete()` thường là xoá **vĩnh viễn** — user bấm nhầm, hoặc kế toán cần tra lại đơn cũ, là chịu. Xoá mềm = thay vì xoá dòng, chỉ **đóng dấu thời điểm xoá** vào cột `deleted_at`; dữ liệu vẫn nằm nguyên trong DB nhưng mọi truy vấn bình thường coi như nó không tồn tại.

Bật lên bằng một trait + một cột:
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model { use SoftDeletes; }
// + trong migration: $table->softDeletes();   → tạo cột deleted_at (nullable)
```
Từ đây hành vi đổi như sau:
```php
$order->delete();            // KHÔNG xoá dòng — chỉ set deleted_at = now()
Order::all();                // KHÔNG thấy đơn đã xoá mềm nữa (Eloquent tự thêm
                             //   WHERE deleted_at IS NULL vào MỌI truy vấn — một global scope §10.2)
Order::find($id);            // cũng không thấy → null
```
Cơ chế: `deleted_at = null` nghĩa là "đang sống"; có giá trị nghĩa là "đã xoá lúc đó". Khi cần nhìn xuyên qua tấm màn này:
```php
Order::withTrashed()->get();    // tất cả, gồm cả đã xoá mềm (vd trang admin "thùng rác")
Order::onlyTrashed()->get();    // CHỈ những cái đã xoá mềm
$order->trashed();              // bản ghi này đang trong thùng rác? → bool
$order->restore();              // khôi phục (set deleted_at về null) — "hoàn tác xoá"
$order->forceDelete();          // xoá THẬT, vĩnh viễn — vd job dọn rác sau 30 ngày
```
Đánh đổi cần biết trước khi dùng: dữ liệu "đã xoá" vẫn chiếm chỗ trong bảng; các ràng buộc unique phải tính cả bản ghi mềm (email đã "xoá" vẫn chặn người đăng ký lại); và quan hệ tới bản ghi đã xoá mềm trả `null` như xoá thật.

### 14.2. 🟢 Timestamps
`created_at`/`updated_at` tự điền. Tinh chỉnh:
```php
$order->touch();                          // chỉ cập nhật updated_at
Order::withoutTimestamps(fn () => $order->update([...]));  // 🔹 cập nhật KHÔNG đụng updated_at
#[WithoutTimestamps] class Log extends Model {}           // 🔹 tắt hẳn
```

### 14.3. 🟢 HasFactory
```php
use HasFactory;   // mọi model dự án — cho phép Event::factory()->create()
// 🔹 hoặc gắn factory tường minh: #[UseFactory(EventFactory::class)]
```

### 14.4. 🔹 Các trait/tính năng khác thường gặp
- `Notifiable` (🟢 ở User) — cho phép `$user->notify(...)`.
- `Prunable` / `MassPrunable` — tự dọn bản ghi cũ theo lịch.
- `HasUuids` / `HasUlids` — khoá chính dạng UUID/ULID (thay auto-increment).

---

## 15. Route Model Binding

**Không có binding**, controller nào nhận id từ URL cũng phải mở đầu bằng đoạn lặp đi lặp lại này:
```php
// route: /events/{id}
public function show(int $id): View
{
    $event = Event::findOrFail($id);   // tự query, tự lo 404 — lặp ở MỌI controller
    ...
}
```

**Với binding**, chỉ cần khai kiểu tham số là model — Laravel tự làm hộ toàn bộ:
```php
// routes/web.php:  Route::get('/events/{event}', [EventController::class, 'show']);
public function show(Event $event): View   // ← nhận thẳng Event, không phải int
{
    $event->title;   // dùng luôn — đã là bản ghi thật lấy từ DB
}
```
Điều kiện để phép màu chạy: **tên tham số route `{event}` phải trùng tên biến `$event`**, và biến khai kiểu là một model. Khi khớp, với URL `/events/5` Laravel tự chạy `Event::findOrFail(5)`:
- Tìm thấy → truyền object vào method.
- Không thấy → trả trang **404** luôn, code trong controller không chạy — bạn khỏi viết if/else kiểm tra.

🟢 Dự án dùng ở mọi controller nhận `Event`/`Order`/`Ticket`. Trong `OrderController::show`, `$order` đến tay đã là bản ghi thật — nên chỉ cần `->load(...)` bổ sung quan hệ (§8.3).

🔹 **Bind theo cột khác `id`** — muốn URL đẹp kiểu `/events/live-concert-2026` thay vì `/events/5`:
```php
// Cách 1 — đổi cho riêng một route:
Route::get('/events/{event:slug}', ...);          // tìm theo cột slug thay vì id

// Cách 2 — đổi mặc định cho model (mọi route đều theo):
public function getRouteKeyName(): string { return 'slug'; }
```
🔹 **Scoped binding** — route lồng 2 model `/orders/{order}/tickets/{ticket}`: thêm `->scopeBindings()` để Laravel kiểm luôn *vé này có thuộc đơn này không*, khác đơn là 404 (chặn đoán id xem vé của người khác).

Chi tiết ở `laravel-explained-level1.md` §4.

---

## 16. Các method tiện ích trên một instance

| Gọi | Ý nghĩa |
|---|---|
| `$order->fresh()` | nạp lại bản mới từ DB (object mới) |
| `$order->refresh()` | làm mới chính object hiện tại |
| `$order->replicate()` | nhân bản (chưa lưu) |
| `$order->is($other)` / `isNot(...)` | so sánh cùng bản ghi? |
| `$order->wasChanged('status')` | vừa `save()`, cột này có đổi? |
| `$order->isDirty('status')` | có thay đổi chưa lưu? |
| `$order->getOriginal('status')` | giá trị trước khi sửa |
| `$order->getChanges()` | các cột vừa đổi |
| `$order->only(['id','status'])` / `except([...])` | trích mảng thuộc tính |
| `$order->toArray()` / `toJson()` | serialize |

> **🧪 Chạy thử** — theo dõi thay đổi CHƯA lưu ("dirty tracking"):
> ```
> >>> $e = App\Models\Event::first();
> >>> $e->title = 'Đổi tạm';           // đổi trong bộ nhớ, CHƯA save()
> >>> $e->isDirty('title')              // "có thay đổi chưa lưu không?"
> => true
> >>> $e->getOriginal('title')          // giá trị gốc (trong DB) trước khi đổi
> => "Live Concert 2026"
> >>> $e->title                          // giá trị hiện tại trong bộ nhớ
> => "Đổi tạm"
> ```
> Vì chưa gọi `$e->save()`, DB vẫn nguyên. Đây là cách kiểm tra "người dùng có thực sự sửa gì không" trước khi quyết định lưu.

---

## 17. Bảng tra nhanh Attribute v13

Các attribute có sẵn trong `Illuminate\Database\Eloquent\Attributes` (đã kiểm trong vendor 13.19). **Dự án KHÔNG dùng attribute** — bảng này chỉ để tra khi đọc code người khác. Mỗi attribute thay cho một property truyền thống mà dự án dùng:

| Attribute (🔹 không dùng) | Property dự án dùng | Công dụng |
|---|---|---|
| `#[Fillable([...])]` | 🟢 `$fillable` | danh sách trắng mass-assignment |
| `#[Guarded([...])]` | `$guarded` | danh sách đen |
| `#[Unguarded]` | `$guarded = []` | tắt kiểm soát |
| `#[Hidden([...])]` | 🟢 `$hidden` | ẩn khỏi JSON |
| `#[Visible([...])]` | `$visible` | chỉ hiện các cột này |
| `#[Appends([...])]` | `$appends` | thêm accessor vào JSON |
| `#[Table('...')]` | `$table` | tên bảng |
| `#[Connection('...')]` | `$connection` | kết nối DB |
| `#[DateFormat('...')]` | `$dateFormat` | định dạng ngày lưu |
| `#[WithoutTimestamps]` | `$timestamps=false` | tắt timestamps |
| `#[WithoutIncrementing]` | `$incrementing=false` | khoá không tự tăng |
| `#[Scope]` | `scopeXxx` | đánh dấu method là local scope |
| `#[ScopedBy([...])]` | `addGlobalScope` | gắn global scope |
| `#[ObservedBy([...])]` | `Model::observe()` | gắn observer |
| `#[UseFactory(...)]` | `newFactory()` | chỉ định factory |
| `#[UsePolicy(...)]` | auto-discovery | chỉ định policy |
| `#[CollectedBy(...)]` | `newCollection()` | custom Collection |
| `#[Touches([...])]` | `$touches` | quan hệ cần cập nhật updated_at |
| `#[UseResource(...)]` | — | API Resource mặc định |

> Attribute và property **tương đương** — chọn một lối và nhất quán. **Dự án dùng property (kiểu cũ) xuyên suốt.**

---

## 18. Đối chiếu

Tính năng ↔ nơi dùng thật trong `level1/`:

| Tính năng | File | Dòng ý |
|---|---|---|
| `protected $fillable` | mọi model | danh sách cột |
| `protected $hidden` | `User.php` | ẩn password |
| `casts()` datetime/integer/hashed | mọi model | `Order`, `User` |
| `hasMany` / `belongsTo` | `Event`, `Order`, `TicketType`, `Ticket` | các method quan hệ |
| Local scope `scopePublished` | `Event.php` | `EventController::index` gọi `->published()` |
| Method nghiệp vụ (`reservedQuantity`, `remaining`, `isPaid`…) | `TicketType`, `Order`, `Ticket` | §11 |
| Hằng số trạng thái | `Order`, `Ticket`, `User` | `STATUS_*`, `ROLE_*` |
| `with` / `load` / `loadMissing` / `withCount` | các controller | §8 |
| `whereHas` | `TicketType::reservedQuantity` | §9 |
| `create` / `createMany` / `update` | `OrderController`, `StripeWebhookController` | §12.4 |
| `lockForUpdate` | 3 controller | §12.5 |
| `HasFactory` | mọi model | test |
| Route Model Binding | mọi controller nhận `Event`/`Order`/`Ticket` | §15 |

**Chưa dùng (biết để khi cần):** Accessor/Mutator (§4), Enum cast (§3.2), belongsToMany (§6), Soft Deletes (§14.1), Model Events/Observer (§13), Global scope (§10.2), `#[Scope]` attribute (§10.1). Nhiều thứ trong số này sẽ xuất hiện khi lên `level2/`+.
