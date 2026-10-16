# COORDINATION & HANDOVER PROTOCOL (WOW THAI STEYR)

## 📌 LƯỢT CẬP NHẬT HIỆN TẠI (CURRENT TURN)
- **Active Agent in Charge**: **Codex / ChatGPT — Operations & CRM implementation authorized by owner**
- **Current scope**: `plugins/wowthai-operations`, plugin packaging, tests, and compatibility adapters. Theme redesign remains outside this task. Do not deploy a second agent's checkout over these files.
- **Data scope**: Selective import of Wix contact profiles only from `a1e58c2c-1fca-4e4b-a015-e14b156d3a32`. No historical orders/reservations, no account creation, no marketing sends, no full database sync. Customer files stay outside Git and public uploads.
- **Deployment status**: Implementation started; online installation awaits an authenticated wp-admin browser session. Record actual import counts and release revision here before handover.
- **Handover Timestamp**: 2026-10-16 11:35 (Europe/Vienna)
- **Current Theme Version**: `v1.3.0`
- **Git Repository**: `https://github.com/mingx96/wowthaisteyr.git`
- **Primary Branch**: `main`

---

## 🔒 QUY TẮC PHỐI HỢP (RULES OF ENGAGEMENT)
1. **Chỉ Agent giữ lượt được ghi vào site chung**: Trước khi ghi vào Studio hoặc push lên Live (`wpcomstaging.com`), kiểm tra và cập nhật `COORDINATION.md`.
2. **Triển khai Theme — KHÔNG đồng bộ Database**: Mọi thay đổi nằm hoàn toàn trong mã nguồn theme `wp-content/themes/wowthai`. Tuyệt đối không import/overwrite database (`--options "themes"` duy nhất) để bảo toàn dữ liệu đơn hàng và cấu hình thực tế.
3. **Kiểm định Tự động Bắt buộc**: Luôn chạy `node dev/verify.mjs` (10/10 cổng G1–G10 PASS + 62 unit assertions trong `dev/tests/hours.test.php`) trước khi đóng gói `node dev/build.mjs` và đẩy lên Live.

---

## 🚀 TÓM TẮT THAY ĐỔI TỪ v1.1.4 ĐẾN v1.3.0 (CHANGELOG)

### 1. Phiên bản v1.2.0 – v1.2.3
- **Live Search & Filter**, **In Phiếu Bếp 80mm (`[🖨️ Bon]`)**, **Chuông báo Web Audio Ding-Dong**, **Email HTML Branded Luxury**, **Trang pháp lý `/impressum/` & `/datenschutz/`**, **Schema.org JSON-LD & PWA Manifest**.
- **Thang độ cay (`🌶️`)**, **Bảng tra dị ứng Codex EU (A–R)**, **Slide-Cart Upsell 1-Click**, **Điều tiết nhịp độ Bếp ("Küchen-Taktung")**, **Bảo mật tính giá Server-side**, **Modifier Groups**, **`inc/pickup.php`**.

### 2. Phiên bản v1.3.0 (Phiên bản Hiện tại — Master Architecture, Legal, Kitchen & Modern Web Upgrade)
- **Phase 0 — Hệ thống Kiểm định Tự động (`dev/verify.mjs`, `dev/build.mjs`, `dev/tests/hours.test.php`)**:
  - 10 cổng kiểm định tự động **G1–G10** (kiểm tra Single Source of Truth giờ mở cửa, không Google Fonts ngoài, không lỗi UTF-8 mojibake, LF line endings, `node --check` + `php -l` toàn bộ 22 tệp PHP, đồng bộ version, chống truy cập superglobal thô, chống hàm UTC `date()`/`time()`/`strtotime()`, số điện thoại chuẩn `+43 7252 74973`, hiển thị đầy đủ biến thể/modifier trên phiếu bếp 80mm).
  - **62 unit assertions** trong `dev/tests/hours.test.php` kiểm thử đầy đủ biên giờ mở cửa, Thứ 2 nghỉ Sushi, khung giờ `Mittagsmenü` (Thứ 2–Thứ 7 `11:30–14:30`), hạn đặt bàn trước 30 phút đóng bếp, và chuyển đổi giờ mùa hè/mùa đông (`Europe/Vienna` DST).
- **Phase 1 — Hotfix Vận hành Bếp, Hóa đơn, Email & Pháp lý**:
  - **Phiếu bếp 80mm & Bảng Admin Orders (`inc/admin-orders.php`)**: Bổ sung `wowthai_get_order_item_customizations($item)` hiển thị đầy đủ `Variante` và `Modifiers` (loại mì, topping...) trên cả bảng đơn hàng và phiếu in nhiệt 80mm; đọc đúng khóa `'Abholzeit'` (kèm fallback tương thích ngược).
  - **Hóa đơn Vàng (`woocommerce/checkout/thankyou.php`)**: Sửa số điện thoại sai `+43 7252 45866` thành động `+43 7252 74973` và hiển thị chi tiết biến thể/modifier từng món.
  - **Email Hệ thống (`inc/email-templates.php`)**: Loại bỏ hoàn toàn fallback `dev-email@wpengine.local`, dùng `wowthai_get_notification_email()`.
  - **Chống 404 Trang Pháp lý (`functions.php`)**: Tự động khởi tạo trang `impressum` & `datenschutz` (`wowthai_ensure_legal_pages`) kết hợp virtual template routing đảm bảo `/impressum/` và `/datenschutz/` luôn trả về HTTP 200 mà không cần đồng bộ DB.
- **Phase 2 — Single Source of Truth Giờ Mở Cửa & Timezone (`inc/hours.php`)**:
  - Tập trung duy nhất toàn bộ cấu hình giờ mở cửa (`Mo–Sa 11:30–14:30 & 17:00–21:00`, `So 11:30–19:00`, Thứ 2 nghỉ Sushi, `Mittagsmenü` `Mo–Sa 11:30–14:30`) vào `inc/hours.php`.
  - Đồng bộ `inc/pickup.php`, `inc/restaurant-info.php`, `footer.php`, `front-page.php`, `inc/admin-reservations.php`; khóa đặt món `Mittagsmenü` ngoài khung giờ trưa Thứ 2–Thứ 7.
- **Phase 3 — Bảo mật Đặt bàn, DSGVO, `.ics` & Tự Hủy 1-Click (`inc/reservations-front.php`, `page-reservations.php`)**:
  - Chuyển sang luồng `admin-post.php` (`wowthai_reserve`) + Post-Redirect-Get với HMAC token, honeypot chống bot, giới hạn tần suất IP (ẩn danh SHA-256), kiểm tra tick đồng ý DSGVO, kiểm tra khung giờ hợp lệ từ `wowthai_reservation_slots()`, chống đặt trùng, tải lịch `.ics` (`wowthai_res_ics`), link khách tự hủy đặt bàn 1-click (`wowthai_res_cancel`), và cron tự động dọn dữ liệu quá 90 ngày theo DSGVO.
- **Phase 4 — Chuẩn hóa Thực đơn, Gỡ Google Fonts, Logo Retina & SEO/OG**:
  - **`inc/menu-normalizer.php`**: Tự động sửa lỗi chính tả danh mục `Vorpseise Sushi` → `Vorspeise Sushi`, khôi phục mô tả món `V10. Tod Man Gung`, và chuẩn hóa chính tả tiếng Đức cho toàn bộ 94 món mà không cần ghi đè DB.
  - **DSGVO Fonts (`assets/css/styles.css`)**: Gỡ bỏ `@import` Google Fonts bên ngoài, dùng 100% font tự host WOFF2 (`Playfair Display` & `DM Sans`).
  - **Tối ưu LCP & Logo (`assets/img/logo-sm.png`, `header.php`)**: Tạo logo 2x Retina `logo-sm.png` (21.6 KB, giảm 95% dung lượng so với 426 KB), chỉ preload ảnh Hero LCP trên trang chủ, bổ sung `<meta name="description">`, OpenGraph `og:*`, skip-link `#main`, và banner Sondertag.
- **Phase 5 — Modern Web `<dialog>`, Accessibility WCAG 2.1 AA, Tối ưu INP & Quản lý Ngày nghỉ Lễ (`page-menu.php`, `assets/js/wowthai.js`, `assets/css/styles.css`, `inc/admin-orders.php`)**:
  - Nâng cấp toàn bộ Modal món ăn, Giỏ hàng trượt và Bảng Allergen sang phần tử gốc `<dialog closedby="any">` mở bằng `.showModal()` kèm coordinate light-dismiss fallback cho Safari và khóa cuộn nền bằng CSS (`overscroll-behavior: contain`).
  - Chuẩn hóa `role="tab"`, `aria-selected`, `aria-pressed`, vùng thông báo `#dish-result-status` (`role="status" aria-live="polite"`), nhãn giỏ hàng động `Bestellung (N Artikel)` chuẩn WCAG 2.5.3.
  - Thay vòng lặp `setInterval` 1s bằng sự kiện WooCommerce (`updated_checkout`, `checkout_error`) và tự động tạm dừng polling 30s khi tab ẩn (`document.hidden`).
  - Bổ sung thanh điều khiển **Sondertage / Feiertage / Betriebsurlaub** ngay trong trang Đơn hàng Admin.

---

## 📂 DANH MỤC TỆP TIN THUỘC THEME (22 PHP Files — 10/10 Gates Passed)
- `style.css` (v1.3.0)
- `functions.php`
- `header.php`, `footer.php`, `front-page.php`, `index.php`, `page.php`, `woocommerce.php`
- `page-menu.php`, `page-reservations.php`, `page-impressum.php`, `page-datenschutz.php`
- `inc/hours.php` (Single Source of Truth cho giờ mở cửa, timezone `Europe/Vienna`, Sushi & Mittagsmenü)
- `inc/menu-normalizer.php` (Chuẩn hóa tên danh mục & chính tả tiếng Đức cho 94 món ăn)
- `inc/reservations-front.php` (PRG, Rate-limit, Honeypot, DSGVO, `.ics`, 1-click Cancel, 90-day Cron)
- `inc/pickup.php`, `inc/restaurant-info.php`, `inc/restaurant-experience.php`
- `inc/admin-orders.php`, `inc/admin-reservations.php`, `inc/email-templates.php`
- `woocommerce/checkout/form-checkout.php`, `woocommerce/checkout/thankyou.php`
- `assets/js/wowthai.js`, `assets/css/styles.css`, `assets/css/checkout.css`, `assets/css/fonts.css`
- `assets/img/logo-sm.png`, `assets/img/logo.png`, `assets/img/hero.jpg`, `assets/pwa/manifest.json`

