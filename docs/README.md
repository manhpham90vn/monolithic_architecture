# Tài liệu dự án

Bộ tài liệu cho quy chuẩn kiến trúc monolith Laravel (ARCH-MONO-01) và bốn ứng
dụng mẫu `level1`–`level4` cùng hiện thực một nghiệp vụ bán vé sự kiện.

Quy chuẩn gốc nằm ở [../README.md](../README.md) — bốn mức kiến trúc và các quy
định đánh số (QĐ-x.y). Các tài liệu trong thư mục này bổ trợ và diễn giải nó.

## Đọc theo thứ tự nào

1. **[../README.md](../README.md)** — quy chuẩn: bốn mức, quy định, checklist review. Đọc trước tiên.
2. **[ticket-sales-spec.md](ticket-sales-spec.md)** — spec nghiệp vụ bán vé (các yêu cầu YC-x.y mà mọi tài liệu khác trích dẫn).
3. **[code-guide.md](code-guide.md)** — mục lục bộ "giải thích code": so sánh bốn mức, quy tắc gọi nhau tăng dần. Từ đây rẽ vào từng mức.
4. **[database-design.md](database-design.md)** — thiết kế DB và schema thay đổi thế nào qua bốn mức (sơ đồ ER, bảng quy tắc DB).
5. **[testing-guide.md](testing-guide.md)** — chiến lược test qua bốn mức (feature vs domain unit vs arch test).

## Bản đồ tài liệu

| Tài liệu | Nội dung | Trích dẫn chính |
| --- | --- | --- |
| [../README.md](../README.md) | Quy chuẩn kiến trúc: 4 mức, quy định QĐ, tín hiệu nâng/hạ mức, checklist. | — |
| [ticket-sales-spec.md](ticket-sales-spec.md) | Spec nghiệp vụ bán vé: yêu cầu YC-x.y. | — |
| [framework-mapping.md](framework-mapping.md) | Ánh xạ khái niệm sang Laravel / NestJS / Spring Boot. | — |
| [code-guide.md](code-guide.md) | **Index** bộ giải thích code; so sánh 4 mức, ma trận được/cấm. | mọi QĐ |
| [level1-code-guide.md](level1-code-guide.md) | Nhiệm vụ từng file + quy tắc mức 1 (CRUD thuần). | QĐ-1.x |
| [level2-code-guide.md](level2-code-guide.md) | Mức 2 (Action + DTO + Event). | QĐ-2.x |
| [level3-code-guide.md](level3-code-guide.md) | Mức 3 (modular monolith); ma trận phụ thuộc, cấm chéo module. | QĐ-3.x |
| [level4-code-guide.md](level4-code-guide.md) | Mức 4 (DDD); Domain/Application/Infrastructure của Ticketing. | QĐ-4.x |
| [database-design.md](database-design.md) | Schema, ranh giới dữ liệu, bảng quy tắc DB, sơ đồ ER. | QĐ-3.7, QĐ-3.9, QĐ-4.x |
| [testing-guide.md](testing-guide.md) | Feature vs domain unit vs arch test; CI. | QĐ-8.4, QĐ-4.4, QĐ-3.6 |

## Quy ước ký hiệu

| Ký hiệu | Nghĩa | Ở đâu |
| --- | --- | --- |
| `QĐ-x.y` | Một quy định trong quy chuẩn kiến trúc. | [../README.md](../README.md) |
| `YC-x.y` | Một yêu cầu nghiệp vụ bán vé. | [ticket-sales-spec.md](ticket-sales-spec.md) |
| `§n` | Mục số n của tài liệu đang đọc. | mỗi tài liệu |

## Lưu ý về phạm vi

Bốn ứng dụng mẫu tồn tại để **so sánh** bốn mức trên cùng một nghiệp vụ, không
phải để mọi dự án chạy tới mức 4. Nguyên tắc xuyên suốt (QĐ-0.1): *mức đúng là
mức thấp nhất chưa phát sinh tín hiệu nâng cấp* — mức cao hơn không "chuẩn hơn".
