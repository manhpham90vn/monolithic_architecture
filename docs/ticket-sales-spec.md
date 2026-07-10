# Spec — Trang bán vé sự kiện online

| | |
| --- | --- |
| **Mã tài liệu** | SPEC-TICKET-01 |
| **Phiên bản** | 0.2 |
| **Ngày** | 2026-07-08 |
| **Phạm vi** | Bán vé sự kiện — đăng nhập email/mật khẩu, thanh toán Stripe (JPY), soát vé |

> Tài liệu này mô tả **hệ thống phải làm gì** (yêu cầu và quy tắc nghiệp vụ), không bao gồm thiết kế database, route hay chi tiết kỹ thuật.
>
> Spec được cỡ "vừa" một cách có chủ đích. Cùng một bộ yêu cầu này được hiện thực thành **bốn ứng dụng độc lập** (`level1/ … level4/`), mỗi ứng dụng: (a) tự chạy được đầy đủ, (b) tách nhau hoàn toàn — không share code, mỗi app một composer/DB/`.env` riêng, (c) hiện thực **trọn vẹn** spec §2–§13. Bốn app **chỉ khác nhau ở cách tổ chức code**, không khác hành vi. Xem Phụ lục A (phi quy phạm) để biết mỗi mức tổ chức thế nào.

---

## 1. Từ khóa quy phạm

Các từ khóa được dùng theo tinh thần RFC 2119:

| Từ khóa | Ý nghĩa |
| --- | --- |
| **BẮT BUỘC** | Yêu cầu tuyệt đối, không có ngoại lệ. |
| **KHÔNG ĐƯỢC** | Cấm tuyệt đối. |
| **NÊN / KHÔNG NÊN** | Mặc định phải theo; làm khác được nếu có lý do chính đáng, ghi rõ. |
| **CÓ THỂ** | Tùy chọn. |

Mỗi yêu cầu được đánh số dạng `YC-<nhóm>.<số>` để trích dẫn.

## 2. Mục tiêu và phạm vi

Hệ thống cho phép người dùng duyệt sự kiện, mua vé, thanh toán online qua Stripe, nhận vé điện tử, và cho ban tổ chức soát vé tại cửa. Giữ tối giản: một loại người mua, một cổng thanh toán, tiền tệ JPY, không sơ đồ ghế.

**YC-2.1** — Hệ thống **BẮT BUỘC** hỗ trợ: đăng ký/đăng nhập email + mật khẩu, xem sự kiện, mua vé và thanh toán qua Stripe, xem lại vé đã mua, soát vé bằng mã QR.

**YC-2.2** — Tiền tệ giao dịch **BẮT BUỘC** là JPY (yên).

**YC-2.3** — Các tính năng sau **KHÔNG** thuộc phạm vi: chọn ghế, hoàn/hủy vé tự phục vụ, mã giảm giá, cổng thanh toán khác, đăng nhập mạng xã hội.

## 3. Các mảng nghiệp vụ

Hệ thống gồm bốn mảng nghiệp vụ, đặt tên theo ngôn ngữ người dùng. Đây là ranh giới **nghiệp vụ**, không phải chỉ định kỹ thuật:

| Mảng | Trách nhiệm |
| --- | --- |
| **Catalog** | Sự kiện, hạng vé, giá, số lượng còn bán. |
| **Ticketing** | Đơn mua, giữ/trả vé, trạng thái đơn, phát hành vé điện tử. |
| **Payment** | Thanh toán Stripe và xác nhận đã trả tiền. |
| **Check-in** | Soát vé bằng mã QR tại cửa sự kiện. |

**YC-3.1** — Mỗi mảng **BẮT BUỘC** là nguồn sự thật cho dữ liệu của mình; một mảng cần dữ liệu của mảng khác thì lấy qua năng lực (capability) mà mảng kia cung cấp, **KHÔNG ĐƯỢC** giả định cấu trúc lưu trữ nội bộ của mảng kia.

> YC-3.1 là ràng buộc nghiệp vụ ở mức khái niệm (ai sở hữu dữ liệu gì). Cách *ép* ranh giới này bằng code chỉ có ý nghĩa từ mức 3 trở lên — xem Phụ lục A.

## 4. Vai trò người dùng

| Vai trò | Được làm gì |
| --- | --- |
| **Khách** | Xem danh sách và chi tiết sự kiện. |
| **Người dùng** | Như khách + mua vé, xem "Vé của tôi". |
| **Nhân viên soát vé** | Quét mã QR để xác nhận vé hợp lệ và đánh dấu đã dùng. |

**YC-4.1** — Khách chưa đăng nhập **CÓ THỂ** xem sự kiện nhưng **KHÔNG ĐƯỢC** mua vé; khi thao tác mua, hệ thống **BẮT BUỘC** yêu cầu đăng nhập rồi đưa trở lại đúng chỗ.

**YC-4.2** — Chức năng soát vé **BẮT BUỘC** chỉ dành cho nhân viên soát vé, **KHÔNG ĐƯỢC** để lộ cho người mua.

## 5. Xác thực

**YC-5.1** — Đăng ký **BẮT BUỘC** gồm họ tên, email và mật khẩu tối thiểu 8 ký tự.

**YC-5.2** — Mỗi email **KHÔNG ĐƯỢC** đăng ký nhiều hơn một tài khoản.

**YC-5.3** — Hệ thống **BẮT BUỘC** cho phép đăng nhập email + mật khẩu và đăng xuất. **CÓ THỂ** có "ghi nhớ đăng nhập".

**YC-5.4** — Hệ thống **BẮT BUỘC** cho phép đặt lại mật khẩu qua email khi quên.

**YC-5.5** — v1 **KHÔNG NÊN** yêu cầu xác minh email.

## 6. Catalog — sự kiện và hạng vé

**YC-6.1** — Mỗi sự kiện **BẮT BUỘC** có tiêu đề, mô tả, địa điểm và thời gian diễn ra.

**YC-6.2** — Chỉ sự kiện đã công bố **BẮT BUỘC** hiển thị cho người mua; sự kiện chưa công bố **KHÔNG ĐƯỢC** hiển thị.

**YC-6.3** — Mỗi sự kiện **BẮT BUỘC** có ít nhất một hạng vé; mỗi hạng vé **BẮT BUỘC** có tên, giá (yên) và số lượng bán ra giới hạn.

**YC-6.4** — Trang chi tiết sự kiện **BẮT BUỘC** hiển thị số vé còn lại của từng hạng vé.

**YC-6.5** — Khi một hạng vé đã bán hết, hệ thống **BẮT BUỘC** thể hiện rõ và **KHÔNG ĐƯỢC** cho mua thêm hạng đó.

## 7. Ticketing — luồng mua vé

**YC-7.1** — Người dùng **BẮT BUỘC** chọn được hạng vé và số lượng trong **một** sự kiện rồi tạo đơn để thanh toán.

**YC-7.2** — Khi tạo đơn, hệ thống **BẮT BUỘC** đặt đơn ở trạng thái *chờ thanh toán* và tạm giữ số vé đã chọn (§8).

**YC-7.3** — Hệ thống **BẮT BUỘC** chuyển người dùng sang thanh toán Stripe với số tiền bằng đúng tổng đơn.

**YC-7.4** — Sau khi có xác nhận thanh toán (§9), hệ thống **BẮT BUỘC**: đánh dấu đơn *đã thanh toán*, phát hành vé điện tử, và gửi email xác nhận kèm vé.

**YC-7.5** — Nếu người dùng hủy hoặc rời trang thanh toán, đơn **BẮT BUỘC** giữ nguyên trạng thái *chờ thanh toán*.

## 8. Quy tắc bán vé

**YC-8.1** — Mỗi đơn **KHÔNG ĐƯỢC** vượt quá 10 vé (tổng qua các hạng vé); vượt thì hệ thống **BẮT BUỘC** từ chối và báo lỗi.

**YC-8.2** — Hệ thống **KHÔNG ĐƯỢC** bán quá số lượng hạng vé. Số vé đang giữ cho các đơn *chờ thanh toán* chưa hết hạn **BẮT BUỘC** được tính là không còn bán được.

**YC-8.3** — Khi hai người dùng cùng mua vé cuối cùng đồng thời, hệ thống **BẮT BUỘC** chỉ chấp nhận một đơn; đơn còn lại **BẮT BUỘC** bị từ chối vì hết vé.

**YC-8.4** — Vé **BẮT BUỘC** chỉ bị trừ vĩnh viễn khỏi kho khi đơn *đã thanh toán*. Đơn *hết hạn* hoặc *đã hủy* **BẮT BUỘC** trả lại số vé đã giữ.

**YC-8.5** — Giá vé **BẮT BUỘC** được chốt tại thời điểm tạo đơn; thay đổi giá sau đó **KHÔNG ĐƯỢC** ảnh hưởng đơn đã tạo.

## 9. Trạng thái đơn và thanh toán

| Trạng thái | Ý nghĩa |
| --- | --- |
| **Chờ thanh toán** | Vừa tạo, đang giữ vé, chờ trả tiền. |
| **Đã thanh toán** | Stripe xác nhận đã trả; vé được phát hành. Trạng thái cuối. |
| **Hết hạn** | Quá 15 phút chưa trả; vé đã giữ được trả lại. Trạng thái cuối. |
| **Đã hủy** | Bị hủy khi chưa thanh toán. Trạng thái cuối. |

**YC-9.1** — Đơn *chờ thanh toán* quá **15 phút** chưa trả tiền **BẮT BUỘC** chuyển sang *hết hạn* và trả lại số vé đã giữ.

**YC-9.2** — Đơn **BẮT BUỘC** chỉ được chuyển sang *đã thanh toán* khi có xác nhận từ Stripe về phía máy chủ. Hệ thống **KHÔNG ĐƯỢC** coi đơn là đã thanh toán chỉ vì trình duyệt người dùng quay về trang thành công.

**YC-9.3** — Việc xử lý xác nhận thanh toán **BẮT BUỘC** chịu được gọi lặp lại mà **KHÔNG ĐƯỢC** phát hành vé hoặc trừ kho nhiều hơn một lần cho cùng một đơn.

## 10. Vé điện tử

**YC-10.1** — Sau khi đơn được thanh toán, mỗi vé **BẮT BUỘC** được phát hành thành một mã QR riêng biệt.

**YC-10.2** — Người dùng **BẮT BUỘC** xem lại được toàn bộ vé đã mua trong trang "Vé của tôi".

## 11. Check-in — soát vé

**YC-11.1** — Nhân viên soát vé **BẮT BUỘC** quét được mã QR của vé và nhận kết quả: *hợp lệ*, *đã dùng*, hoặc *không tồn tại*.

**YC-11.2** — Vé hợp lệ khi được soát **BẮT BUỘC** chuyển sang trạng thái *đã dùng*.

**YC-11.3** — Một vé **KHÔNG ĐƯỢC** soát vào cửa quá một lần; lần quét thứ hai **BẮT BUỘC** báo *đã dùng*.

## 12. Email

**YC-12.1** — Hệ thống **BẮT BUỘC** gửi email xác nhận sau khi thanh toán thành công, gồm tên sự kiện, thời gian, địa điểm và các vé kèm mã QR.

**YC-12.2** — Hệ thống **BẮT BUỘC** gửi email đặt lại mật khẩu khi người dùng yêu cầu.

## 13. Tiêu chí hoàn thành (Definition of Done)

- [ ] Khách xem được danh sách và chi tiết sự kiện đã công bố. (YC-4.1, YC-6.2)
- [ ] Đăng ký / đăng nhập / đăng xuất / đặt lại mật khẩu hoạt động. (§5)
- [ ] Mua vé qua Stripe (test mode) trọn vẹn: đặt đơn → thanh toán → *đã thanh toán* → phát hành vé → email. (§7, YC-9.2)
- [ ] Không bán quá số lượng kể cả khi mua đồng thời. (YC-8.2, YC-8.3)
- [ ] Đơn quá 15 phút tự hết hạn và trả lại vé. (YC-9.1)
- [ ] Không cho đặt quá 10 vé mỗi đơn. (YC-8.1)
- [ ] Xử lý xác nhận thanh toán idempotent. (YC-9.3)
- [ ] "Vé của tôi" hiển thị đúng vé đã mua. (YC-10.2)
- [ ] Soát vé QR trả đúng kết quả và chặn soát trùng. (§11)

---

## Phụ lục A — Triển khai minh hoạ theo 4 mức (phi quy phạm)

Phụ lục này **không phải yêu cầu**. Bốn ứng dụng `level1/ … level4/` đều hiện thực **toàn bộ** spec §2–§13 (cùng hành vi, cùng tiêu chí §13), chỉ khác **cách tổ chức code**. Mục đích giáo cụ: đặt cạnh nhau để thấy cùng một bài toán trông thế nào ở bốn độ trưởng thành kiến trúc — chiếu theo `README.md`.

| Mức | Tổ chức code (cùng một spec đầy đủ) | Điểm cần quan sát |
| --- | --- | --- |
| **1 — CRUD thuần** | Controller gọi thẳng Eloquent; validation trong Form Request; phân quyền trong Policy. Toàn bộ nghiệp vụ (đặt đơn, xác nhận thanh toán, phát hành vé, hết hạn đơn, soát vé) nằm ngay trong Controller/Command. Không Action/Service/Repository (QĐ-1.1, QĐ-1.3). | Controller phình to, logic trùng lặp — chính là các tín hiệu nâng mức ở §4.4. Đây là "phản diện" cố ý: cho thấy **vì sao** cần mức 2. |
| **2 — Có nghiệp vụ** | Một app phẳng. Mỗi nghiệp vụ là một Action (`PlaceOrder`, `ConfirmPayment`, `CheckInTicket`, `ExpireStaleOrders`) với một method `handle()`; dữ liệu qua DTO; việc phụ (gửi mail, phát hành vé) đẩy qua Event `OrderPaid` → Listener (QĐ-2.1, QĐ-2.3, QĐ-2.5). Chưa chia module. | Nghiệp vụ đã tách khỏi HTTP, nhưng bốn mảng §3 vẫn trộn chung thư mục kỹ thuật — tín hiệu §5.4 để lên mức 3. |
| **3 — Modular monolith** | Tách **Catalog / Ticketing / Payment / Check-in** thành module theo §3. Giao tiếp qua Contracts: Ticketing gọi Catalog để giữ/trả vé, Check-in gọi Public API của Ticketing; `OrderPaid` là event chéo module; không JOIN/khóa ngoại chéo module; ranh giới ép bằng deptrac trong CI (QĐ-3.3, QĐ-3.4, QĐ-3.7, QĐ-3.6). | YC-3.1 (mỗi mảng sở hữu dữ liệu của mình) giờ được **ép bằng công cụ**, không còn chỉ là quy ước. |
| **4 — DDD chiến thuật** | Như mức 3, nhưng nâng **riêng module Ticketing** lên DDD: `Order` là aggregate POPO giữ các bất biến §8 (tối đa 10 vé, chống bán quá số, chốt giá, máy trạng thái §9); Domain layer không biết Laravel; Repository là ranh giới thật với lưu trữ. Catalog/Payment/Check-in giữ nguyên mức 3 (QĐ-0.3, QĐ-4.1, QĐ-4.2). | Bất biến được bảo vệ trong entity thay vì rải trong Action; test domain chạy không cần boot Laravel (QĐ-8.4). |

Lưu ý về tính trung thực với README:

- Ép **toàn bộ** spec vào mức 1 là cố ý đi ngược tinh thần "mua kiến trúc đúng lúc" — nhưng đó là **mục đích giáo cụ**: bản mức 1 để người đọc *cảm nhận* cơn đau mà mức 2 giải quyết. Trong một dự án thật, spec này **NÊN** khởi điểm ở mức 2 (xem phần thảo luận trước đó / §4.4).
- Chỉ **Ticketing** được đẩy lên mức 4 vì đây là mảng có bất biến thật (YC-8.x, YC-9.x) đáng bảo vệ. Ép Catalog (gần như CRUD) lên mức 4 sẽ là ví dụ over-engineering.
- **Auth** (§5) ở mọi mức **NÊN** nằm ở tầng hạ tầng của app, không thuộc mảng nghiệp vụ nào (QĐ-3.9).
