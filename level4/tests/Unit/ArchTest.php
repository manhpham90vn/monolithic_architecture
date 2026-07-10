<?php

/*
| Ép bất biến kiến trúc mức 4 bằng công cụ (QĐ-4.1): Domain layer của
| Ticketing KHÔNG được biết Laravel/Eloquent hay bất kỳ module nào tồn tại.
| Đây là phần deptrac không bắt được (nó không coi Illuminate là layer) nên
| kiểm bằng arch test của Pest.
*/

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
