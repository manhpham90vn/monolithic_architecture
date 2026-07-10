<?php

/*
| Ép bất biến kiến trúc mức 4 bằng công cụ (QĐ-4.1). Đây là phần deptrac
| KHÔNG bắt được — nó chỉ ép chiều phụ thuộc giữa các thư mục/layer, không
| coi Illuminate là một layer và không nhìn thấy `readonly`/`final` hay lời
| gọi global helper. Nên bù bằng arch test của Pest.
*/

// --- 1) Domain thuần: không biết framework hay module nào tồn tại ---

arch('Domain của Ticketing không phụ thuộc framework hay module khác')
    ->expect('Ticketing\Domain')
    ->not->toUse([
        'Illuminate',
        'Carbon',
        'Catalog',
        'Payment',
        'CheckIn',
        'Spatie',
        'App',
    ]);

arch('Domain chỉ chứa POPO — không đụng Eloquent Model')
    ->expect('Ticketing\Domain')
    ->not->toUse('Illuminate\Database\Eloquent\Model');

// deptrac/`toUse` chỉ bắt class & namespace, KHÔNG bắt global helper. Một
// Domain class lỡ gọi now()/event() vẫn lọt lưới ở trên — chặn tường minh.
arch('Domain không gọi global helper của Laravel (đồng hồ phải được inject)')
    ->expect('Ticketing\Domain')
    ->not->toUse([
        'now', 'today', 'app', 'resolve', 'event', 'dispatch',
        'config', 'cache', 'logger', 'report', 'abort', 'request',
    ]);

// --- 2) Immutability & finality của Domain ---

arch('Value Object của Ticketing bất biến (final + readonly)')
    ->expect([
        'Ticketing\Domain\Shared\Money',
        'Ticketing\Domain\Order\OrderId',
        'Ticketing\Domain\Order\LineItem',
        'Ticketing\Domain\Order\IssuedTicket',
        'Ticketing\Domain\Ticket\TicketId',
    ])
    ->toBeFinal()
    ->toBeReadonly();

arch('Aggregate root của Ticketing là final (không cho kế thừa)')
    ->expect([
        'Ticketing\Domain\Order\Order',
        'Ticketing\Domain\Ticket\Ticket',
    ])
    ->toBeFinal();

arch('Exception của Domain là final')
    ->expect('Ticketing\Domain\Exception')
    ->toBeFinal()
    ->toExtend('DomainException');

// --- 3) Cổng ra khỏi Domain là interface (dependency inversion, QĐ-4.2) ---

arch('Repository & TokenGenerator của Domain là interface')
    ->expect([
        'Ticketing\Domain\Order\OrderRepository',
        'Ticketing\Domain\Ticket\TicketRepository',
        'Ticketing\Domain\Order\TokenGenerator',
    ])
    ->toBeInterfaces();

// --- 4) Application điều phối qua Domain, không chạm thẳng ORM ---
// (Được phép dùng DB facade cho ranh giới transaction & ValidationException,
//  nhưng KHÔNG được đọc/ghi Eloquent Model trực tiếp — phải qua Repository.)

arch('Application của Ticketing không chạm Eloquent trực tiếp — đi qua Repository')
    ->expect('Ticketing\Application')
    ->not->toUse('Illuminate\Database\Eloquent');
