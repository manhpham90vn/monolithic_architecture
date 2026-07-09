<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Số vé tối đa mỗi đơn (YC-8.1).
     */
    public const int MAX_TICKETS_PER_ORDER = 10;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * quantities = [ticket_type_id => số lượng]. Người dùng chọn hạng vé và
     * số lượng trong MỘT sự kiện (YC-7.1).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:'.self::MAX_TICKETS_PER_ORDER],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var array<int|string, int|string> $quantities */
            $quantities = $this->input('quantities', []);
            $total = array_sum(array_map('intval', $quantities));

            if ($total < 1) {
                $validator->errors()->add('quantities', 'Vui lòng chọn ít nhất một vé.');

                return;
            }

            // Mỗi đơn không được quá 10 vé (YC-8.1).
            if ($total > self::MAX_TICKETS_PER_ORDER) {
                $validator->errors()->add(
                    'quantities',
                    'Mỗi đơn không được quá '.self::MAX_TICKETS_PER_ORDER.' vé.'
                );
            }

            // Mọi hạng vé được chọn phải thuộc đúng sự kiện này (YC-7.1).
            /** @var Event $event */
            $event = $this->route('event');
            $validIds = $event->ticketTypes()->pluck('id')->all();

            foreach ($quantities as $ticketTypeId => $quantity) {
                if ((int) $quantity > 0 && ! in_array((int) $ticketTypeId, $validIds, true)) {
                    $validator->errors()->add('quantities', 'Hạng vé không hợp lệ.');
                    break;
                }
            }
        });
    }

    /**
     * Chỉ giữ các hạng vé có số lượng > 0.
     *
     * @return array<int, int>
     */
    public function selectedQuantities(): array
    {
        return collect($this->input('quantities', []))
            ->map(fn ($quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->mapWithKeys(fn (int $quantity, $id): array => [(int) $id => $quantity])
            ->all();
    }
}
