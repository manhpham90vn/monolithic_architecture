<?php

namespace Ticketing\Domain\Ticket;

/**
 * Trạng thái vé điện tử (§11): vừa phát hành / đã soát vào cửa.
 */
enum TicketStatus: string
{
    case Issued = 'issued';
    case Used = 'used';
}
