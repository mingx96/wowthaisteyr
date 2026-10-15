# COORDINATION & HANDOVER PROTOCOL (WOW THAI STEYR)

## 📌 LƯỢT CẬP NHẬT HIỆN TẠI (CURRENT TURN)
- **Active Agent in Charge**: **Codex / ChatGPT**
- **Handed Over By**: **Gemini (Antigravity)**
- **Handover Timestamp**: 2026-10-16 00:30 (Europe/Vienna)
- **Current Theme Version**: `v1.2.2`
- **Latest Commit**: `d154901` (pushed to `origin/main`)
- **Git Repository**: `https://github.com/mingx96/wowthaisteyr.git`
- **Primary Branch**: `main`

---

## 🔒 QUY TẮC PHỐI HỢP (RULES OF ENGAGEMENT)
1. **Chỉ Agent giữ lượt được ghi vào site chung**: Hiện quyền triển khai lên WordPress Studio / WordPress Online (`wpcomstaging.com`) thuộc về **Codex / ChatGPT**.
2. **Triển khai Theme — KHÔNG đồng bộ Database**: Mọi thay đổi nằm hoàn toàn trong mã nguồn theme `wp-content/themes/wowthai`. Tuyệt đối không import/overwrite database để bảo toàn dữ liệu đơn hàng và cấu hình thực tế.
3. **Đổi lượt**: Khi Codex hoàn tất tích hợp và kiểm thử trực tuyến, cập nhật mục này và chuyển quyền lại cho Gemini kèm commit hash mới nhất.

---

## 🚀 TÓM TẮT THAY ĐỔI TỪ v1.1.4 ĐẾN v1.2.2 (CHANGELOG FOR CODEX)

### 1. Phiên bản v1.2.0 (Commit `543590b`)
- **Live Search & Filter**: Tìm món ăn tức thì không reload (`#dish-search-input`) và bộ lọc chip thông minh (`Alle`, `🌱 Veggie`, `🌶️ Scharf`, `⭐ Beliebt`).
- **In Phiếu Bếp 80mm (`[🖨️ Bon]`)**: Cửa sổ pop-up in nhiệt chuẩn khổ giấy 80mm cho đầu bếp với 1 click từ danh sách đơn hàng.
- **Chuông báo Web Audio Ding-Dong**: Trình duyệt tự phát âm thanh hai tone (587Hz & 880Hz) khi có đơn mới, kèm nút bật/tắt chuông `🔔 Signal: AN/AUS`.
- **Hệ thống Email HTML Branded Luxury**: Giao diện email đen than chì viền vàng kim (`#d4a359`) cho cả đơn món và đặt bàn.
- **Pháp lý Áo**: Trang `/impressum/` (chuẩn WKO, ECG §5) và `/datenschutz/` (DSGVO).
- **Google Schema.org JSON-LD & PWA Tablet Manifest**.

### 2. Phiên bản v1.2.1 (Commit `e8d162f`)
- **Thang độ cay trực quan (`🌶️`)**: Tự động nhận diện mức cay 0–3 (`Leicht pikant`, `Scharf`, `Sehr scharf`) hiển thị trên menu và modal biến thể.
- **Bảng tra dị ứng Codex EU (A–R)**: Modal tra cứu 14 nhóm dị ứng thực phẩm theo luật bảo vệ người tiêu dùng Áo.
- **Slide-Cart Upsell 1-Click**: Thanh gợi ý món bán kèm (Trà Thái Cha Yen, Trà chanh, Bánh phồng tôm Krupuk, Mango Lassi) với nút `+ Hinzufügen` bằng AJAX.
- **Điều tiết nhịp độ Bếp ("Küchen-Taktung")**: Nút chuyển 4 mức phút (`15-20`, `25-35`, `40-50`, `60+`) trong WP-Admin, cập nhật thời gian thực sang giỏ hàng và Kasse của khách.
- **Chỉ dẫn đỗ xe City Point & Google 5-Star Review Booster**: Tích hợp trên Hóa đơn Vàng (`thankyou.php`) và Email.
- **1-Click Add to Google Calendar**: Cho khách đặt bàn thành công.

### 3. Phiên bản v1.2.2 (Commit `d154901`) — PHIÊN BẢN HIỆN TẠI
- **Đồng bộ Chuẩn xác Giờ Bếp toàn hệ thống**:
  - **Thứ 2 đến Thứ 7**: Trưa `11:30 – 14:30` & Chiều/Tối `17:00 – 21:00`.
  - **Chủ Nhật**: `11:30 – 19:00` (thông suốt).
- **Khắc phục triệt để Bug Kasse khi Quán đóng cửa**:
  - Ngoài giờ mở cửa: Nút `⚡ Schnellstmöglich` tự động **Disabled** (hiện nhãn `Geschlossen`).
  - Tự động chuyển sang `🕒 Später abholen (Vorbestellung)` với khung giờ hợp lệ, lọc bỏ giờ trong quá khứ (không còn tình trạng hiện `00:16 Uhr` lúc nửa đêm).
- **Quy tắc Sushi Ruhetag ngày Thứ 2 (Montags kein Sushi)**:
  - Tab Sushi gắn nhãn cảnh báo `Montags Ruhetag`.
  - Hiển thị banner thông báo thân thiện.
  - Vô hiệu hóa nút thêm giỏ hàng đối với các món Sushi vào Thứ 2.
  - Backend validation ngăn chặn triệt để nếu khách đặt Sushi lấy vào Thứ 2.

---

## 📂 DANH MỤC TỆP TIN THUỘC THEME (18 PHP Files — 100% Lint Passed)
- `style.css` (v1.2.2)
- `functions.php` (Core hooks, AJAX, validation)
- `header.php`, `footer.php`, `front-page.php`, `index.php`, `page.php`, `woocommerce.php`
- `page-menu.php`, `page-reservations.php`, `page-impressum.php`, `page-datenschutz.php`
- `inc/restaurant-info.php` (Giờ mở cửa, Schema.org, Slots generator, Sushi check)
- `inc/restaurant-experience.php` (Độ cay, Dị ứng, Nhịp độ bếp, Upsells)
- `inc/admin-orders.php` (Bảng điều phối bếp, in Bon 80mm, chuông âm thanh, nhịp độ bếp)
- `inc/admin-reservations.php` (Quản lý đặt bàn chuẩn Resmio)
- `inc/email-templates.php` (Mẫu email HTML viền vàng luxury)
- `woocommerce/checkout/form-checkout.php` (Kasse Takeaway, điều phối giờ lấy)
- `woocommerce/checkout/thankyou.php` (Hóa đơn Vàng, Review booster)
- `assets/js/wowthai.js` & `assets/css/styles.css`
- `assets/pwa/manifest.json`

---

## 🎯 NHIỆM VỤ TIẾP THEO CỦA CODEX / CHATGPT
1. Pull commit `d154901` từ GitHub branch `main`.
2. Deploy theme lên WordPress Studio / Staging (`https://wowthaisteyr.wpcomstaging.com/`) bằng tệp `wowthai.zip` hoặc git push (Theme only, KHÔNG sync database).
3. Kiểm tra live các tính năng:
   - Menu tìm kiếm, độ cay, modal dị ứng.
   - Thử vào Kasse ban đêm hoặc chọn Thứ 2 để xác nhận logic hoạt động chuẩn.
4. Bàn giao lại lượt cho Gemini khi hoàn tất.
