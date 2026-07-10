<?php

namespace Ticketing\Domain\Order;

use DateTimeImmutable;

/**
 * Ranh giới thật giữa Domain thuần và persistence (QĐ-4.2). Interface nằm
 * trong Domain/, implementation nằm ở Infrastructure/. Đây KHÔNG phải vỏ
 * bọc quanh Eloquent để đổi tên `find()` — nó là chỗ dịch giữa aggregate
 * POPO và bảng DB.
 */
interface OrderRepository
{
    /**
     * Lưu aggregate; trả về Order đã gắn định danh (OrderId) khi là đơn mới.
     */
    public function save(Order $order): Order;

    public function find(OrderId $id): ?Order;

    /**
     * Nạp aggregate kèm khoá bi quan để chống race giữa xác nhận thanh toán
     * và cho hết hạn chạy song song (YC-8.3, YC-9.1). BẮT BUỘC gọi trong
     * một transaction.
     */
    public function findForUpdate(OrderId $id): ?Order;

    /**
     * Định danh các đơn còn chờ thanh toán đã quá hạn giữ vé tính đến $now
     * (YC-9.1).
     *
     * @return OrderId[]
     */
    public function pendingExpiredIds(DateTimeImmutable $now): array;
}
