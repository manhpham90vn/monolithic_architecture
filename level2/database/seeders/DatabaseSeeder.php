<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Người mua thử.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Nhân viên soát vé (§4).
        User::factory()->scanner()->create([
            'name' => 'Scanner',
            'email' => 'scanner@example.com',
        ]);

        // Sự kiện đã công bố kèm hai hạng vé.
        $concert = Event::factory()->create([
            'title' => 'Live Concert 2026',
            'venue' => 'Tokyo Dome',
            'starts_at' => now()->addMonth(),
        ]);
        TicketType::factory()->for($concert)->create(['name' => 'Vé thường', 'price' => 5000, 'quantity' => 100]);
        TicketType::factory()->for($concert)->create(['name' => 'Vé VIP', 'price' => 15000, 'quantity' => 20]);

        // Sự kiện thứ hai, một hạng gần hết vé để minh hoạ sold-out.
        $expo = Event::factory()->create([
            'title' => 'Tech Expo',
            'venue' => 'Osaka Hall',
            'starts_at' => now()->addWeeks(3),
        ]);
        TicketType::factory()->for($expo)->create(['name' => 'Vé vào cửa', 'price' => 2000, 'quantity' => 50]);

        // Sự kiện chưa công bố — KHÔNG được hiển thị (YC-6.2).
        Event::factory()->unpublished()->create(['title' => 'Bí mật (chưa công bố)']);
    }
}
