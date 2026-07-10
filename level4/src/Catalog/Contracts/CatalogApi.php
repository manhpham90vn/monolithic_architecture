<?php

namespace Catalog\Contracts;

/**
 * Cửa ngõ DUY NHẤT của module Catalog cho các module khác (QĐ-3.3).
 * Catalog là nguồn sự thật về sự kiện, hạng vé và số vé còn bán được
 * (YC-3.1): giữ/trả/chốt vé đều đi qua đây, không module nào được đụng
 * bảng events/ticket_types trực tiếp (QĐ-3.4, QĐ-3.7).
 *
 * Các method ghi dữ liệu an toàn khi chạy trong transaction của module gọi
 * (QĐ-3.11): không tự commit, không side-effect ngoài DB.
 */
interface CatalogApi
{
    public function eventInfo(int $eventId): ?EventInfo;

    /**
     * Bản batch: cần N sự kiện thì gọi 1 lần, không lặp N lần (QĐ-3.3).
     *
     * @param  int[]  $eventIds
     * @return array<int, EventInfo> map theo id sự kiện
     */
    public function eventInfos(array $eventIds): array;

    /**
     * @param  int[]  $ticketTypeIds
     * @return array<int, TicketTypeInfo> map theo id hạng vé
     */
    public function ticketTypeInfos(array $ticketTypeIds): array;

    /**
     * Tạm giữ vé cho một đơn chờ thanh toán (YC-7.2, YC-8.2). Khoá tồn kho
     * để hai người mua đồng thời không thể cùng giữ vé cuối (YC-8.3).
     * Trả về thông tin hạng vé chụp tại thời điểm khoá — dùng giá trong đó
     * để chốt giá đơn (YC-8.5).
     *
     * @param  array<int, int>  $quantities  [ticket_type_id => số lượng > 0]
     * @return array<int, TicketTypeInfo> map theo id hạng vé
     *
     * @throws NotEnoughTickets khi một hạng không đủ vé
     */
    public function reserveTickets(array $quantities): array;

    /**
     * Trả lại vé đã giữ khi đơn hết hạn hoặc bị hủy (YC-8.4).
     *
     * @param  array<int, int>  $quantities  [ticket_type_id => số lượng]
     */
    public function releaseTickets(array $quantities): void;

    /**
     * Trừ vĩnh viễn khỏi kho số vé đã giữ của một đơn vừa thanh toán
     * (YC-8.4): chuyển từ "đang giữ" sang "đã bán".
     *
     * @param  array<int, int>  $quantities  [ticket_type_id => số lượng]
     */
    public function commitTicketSales(array $quantities): void;
}
