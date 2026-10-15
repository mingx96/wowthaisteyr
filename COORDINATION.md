# COORDINATION & HANDOVER PROTOCOL (WOW THAI STEYR)

## 📌 LƯỢT CẬP NHẬT HIỆN TẠI (CURRENT TURN)
- **Active Agent in Charge**: **Gemini (Antigravity)** - *Tiếp quản sau khi Codex đạt giới hạn lượt (usage limit)*
- **Handover Timestamp**: 2026-10-16 00:45 (Europe/Vienna)
- **Current Theme Version**: `v1.2.3`
- **Latest Commit**: `14787f4` (pushed to `origin/main`)
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

### 3. Phiên bản v1.2.2 (Commit `d154901`)
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

### 4. Phiên bản v1.2.3 (Phiên bản Hiện tại) — Tích hợp & Nâng cấp Bảo mật Codex & Gemini
- **Bảo mật Tính giá Server-Side (Price Tampering Protection)**:
  - Giá món biến thể và topping được truy vấn trực tiếp từ metadata sản phẩm (`_wowthai_variants`), loại bỏ hoàn toàn việc nhận giá từ POST client.
- **Tùy chọn Bắt buộc / Topping (Modifier Groups)**:
  - Hỗ trợ chọn loại mì (ví dụ: Reisnudeln, Glasnudeln) và topping cho Pad Thai và các món đặc sản; validate bắt buộc trên cả front-end và WooCommerce backend.
- **Mô đun `inc/pickup.php` Chuyên dụng**:
  - Chuẩn hóa toàn bộ thời gian theo timezone nhà hàng `Europe/Vienna`.
  - Kết nối trực tiếp nhịp độ bếp ("Küchen-Taktung") với bộ đệm tính giờ lấy món.
- **Client Heartbeat Refresh (30s)**:
  - Hàm `refreshPickupAvailability` tự động thăm dò cập nhật giờ lấy món khi bếp đổi thời gian chuẩn bị hoặc khi vừa bước qua giờ đóng cửa.
- **Deep-linking Món ăn**:
  - Hỗ trợ tham số URL `?dish=<product_id>` tự động chuyển tab và mở modal món ăn.
- **Xử lý An toàn Món Upsell**:
  - Kiểm tra sản phẩm có sẵn thực tế trong WooCommerce database trước khi hiển thị gợi ý bán kèm.

---

## 📂 DANH MỤC TỆP TIN THUỘC THEME (19 PHP Files — 100% Lint Passed)
- `style.css` (v1.2.3)
- `functions.php` (Core hooks, AJAX, validation, price protection)
- `header.php`, `footer.php`, `front-page.php`, `index.php`, `page.php`, `woocommerce.php`
- `page-menu.php`, `page-reservations.php`, `page-impressum.php`, `page-datenschutz.php`
- `inc/pickup.php` (TÍNH NĂNG MỚI: Quản lý giờ đón, timezone, slot generation)
- `inc/restaurant-info.php` (Giờ mở cửa, Schema.org)
- `inc/restaurant-experience.php` (Độ cay, Dị ứng, Nhịp độ bếp, Upsells bảo vệ)
- `inc/admin-orders.php` (Bảng điều phối bếp, in Bon 80mm, chuông âm thanh, nhịp độ bếp)
- `inc/admin-reservations.php` (Quản lý đặt bàn chuẩn Resmio)
- `inc/email-templates.php` (Mẫu email HTML viền vàng luxury)
- `woocommerce/checkout/form-checkout.php` (Kasse Takeaway, điều phối giờ lấy)
- `woocommerce/checkout/thankyou.php` (Hóa đơn Vàng, Review booster)
- `assets/js/wowthai.js` & `assets/css/styles.css` & `assets/css/checkout.css`
- `assets/pwa/manifest.json`

---

## 🎯 TRẠNG THÁI TRIỂN KHAI (DEPLOYMENT STATUS - HOÀN TẤT)
1. **Theme v1.2.3 Đã Online**:
   - Trực tiếp trên **WordPress.com Staging** (`https://wowthaisteyr.wpcomstaging.com`, Site ID `257980111`).
   - Theme `wowthai` hiện là active theme với phiên bản `1.2.3`.
   - Toàn bộ cơ sở dữ liệu sản phẩm, đơn hàng WooCommerce được giữ nguyên an toàn 100% (không đồng bộ database).
2. **Kho mã nguồn & Bản dựng**:
   - Commit HEAD: `7e85a41` (đã push lên `origin/main`).
   - Gói theme cài đặt chuẩn: `wowthai.zip` tại thư mục themes.
   - Thư mục Studio `C:\Users\mnguy\Studio\wow-thai-steyr\wp-content\themes\wowthai` đồng bộ 100%.
3. **Bàn giao phiên**:
   - Mọi cải tiến đã được tích hợp, lint 19/19 tệp PHP passed, JavaScript / CSS tối ưu hóa theo tiêu chuẩn hiện đại (`modern-web-guidance`).
   - Khi chuyển giao cho ChatGPT / Codex, agent tiếp theo có thể pull branch `main` và tiếp tục theo lộ trình Master Plan.
