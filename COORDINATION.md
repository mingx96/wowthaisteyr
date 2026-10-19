# COORDINATION & HANDOVER PROTOCOL (WOW THAI STEYR)

## 2026-10-19 — Codex: menu description HTML hotfix, review only

- Scope: branch `fix/menu-description-html-20261019`, based on `dbd75c0`.
  Public `/menu/` inspection found six lunch cards displaying literal `<em>`
  markup after product edits. The menu builder passed rich product content to
  plain-text card/modal/search/schema consumers.
- Change: opt-in plain-text normalization at the shared menu data boundary;
  decode entities, strip editor HTML and preserve word spacing. Existing rich
  WooCommerce descriptions, product data, prices and choices are unchanged.
- Verification: regression reproduced nine failures before the fix, then
  13/13 passed; existing hours suite 98/98 passed. G1–G11 passed using the local
  PHP-WASM CLI. Native PHP 8.2 verification is required in PR CI; these are not
  live WordPress/browser checkout tests.
- Next: review the PR and its CI, compare the changed PHP files against the
  active theme before a scoped release, then verify menu cards and selection
  dialogs on mobile. No live upload, database write, or deployment occurred.
  This branch does not claim the shared-site write lock listed below.

- **Active Agent in Charge**: **Antigravity (Mobile-First Redesign & Operations Portal v1.4.0)**
- **Current scope**: Mobile-First redesign for `/betrieb/` (compact header, segmented Kanban switcher), customer menu above-the-fold, global header drawer, typography unification.
- **Current Theme Version**: `v1.4.0`
- **Current Plugin Version**: `v1.0.0`
- **Git Repository**: `https://github.com/mingx96/wowthaisteyr.git`
- **Primary Branch**: `main`

---

## 🔒 QUY TẮC PHỐI HỢP (RULES OF ENGAGEMENT)
1. **Chỉ Agent giữ lượt được ghi vào site chung**: Trước khi ghi vào Studio hoặc push lên Live (`wpcomstaging.com`), kiểm tra và cập nhật `COORDINATION.md`.
2. **Triển khai Theme — KHÔNG đồng bộ Database**: Mọi thay đổi nằm hoàn toàn trong mã nguồn theme `wp-content/themes/wowthai`. Tuyệt đối không import/overwrite database (`--options "themes"` duy nhất) để bảo toàn dữ liệu đơn hàng và cấu hình thực tế.
3. **Kiểm định Tự động Bắt buộc**: Luôn chạy `node dev/verify.mjs` (10/10 cổng G1–G10 PASS + 98 unit assertions trong `dev/tests/hours.test.php`) trước khi đóng gói `node dev/build.mjs` và đẩy lên Live.

---

## 🚀 TÓM TẮT THAY ĐỔI TỪ v1.1.4 ĐẾN v1.3.1 (CHANGELOG)

### 1. Phiên bản v1.3.1 (Chuẩn hóa Giờ mở cửa 11:00–14:30 Mo–Sa, Mittagsmenü M4 Di–Fr, Kết nối Email Toàn diện)
- **Chuẩn hóa Giờ Mở Cửa Mới (`inc/hours.php`)**:
  - Thứ 2 đến Thứ 7 (`Mo–Sa`): Bếp mở từ **`11:00 – 14:30`** và **`17:00 – 21:00`** (điều chỉnh ca trưa từ `11:30` thành `11:00`).
  - Chủ Nhật (`So`): Mở liên tục từ **`11:30 – 19:00`**.
  - Thứ 2 giữ nguyên là ngày nghỉ Sushi (`Montags Sushi-Ruhetag`, bếp nóng mở bình thường).
- **Quy tắc Nghiêm ngặt cho Mittagsmenü M4 (`★ M4 – Spicy Crunchy Tuna Rolls`, ID 84)**:
  - Khác với Mittagsmenü thường (phục vụ `Mo–Sa`), riêng **M4 Sushi chỉ phục vụ từ Thứ 3 đến Thứ 6 (`Di – Fr` `[2, 3, 4, 5]`) trong khung giờ `11:00 – 14:30`**.
  - Bổ sung `wowthai_product_is_mittagsmenu_m4()`, `wowthai_cart_has_mittagsmenu_m4()`, `wowthai_mittagsmenu_m4_available_on_dow()`, `wowthai_is_slot_valid_for_mittagsmenu_m4()`.
  - Trên `/menu/`: Gắn badge nổi bật `🍣 Nur Di – Fr · 11:00 – 14:30 Uhr` trên thẻ món M4; vô hiệu hóa nút đặt món M4 vào Thứ 2, Thứ 7, Chủ Nhật (`🍣 Nur Di – Fr verfügbar`).
  - Tại Giỏ hàng / Kasse: Chỉ hiển thị các khung giờ Thứ 3–Thứ 6 cho đơn chứa M4; tự động chặn đặt ASAP ngoài giờ M4 với thông báo lịch sự tiếng Đức.
- **Giải quyết Triệt để Lỗi Ký tự Thực thể HTML & Bổ sung Mô tả Món Ăn (`inc/menu-normalizer.php`)**:
  - Giải mã thực thể HTML (`html_entity_decode`) tránh lỗi đúp mã hóa `&#8211;` của WordPress `wptexturize()`.
  - Đồng bộ khoảng trắng tên món: `★M` → `★ M` (nhất quán `★ M1`, `★ M2`, `★ M3R`, `★ M3H`, `★ M4`, `★ M2V`, `★ M3V`).
  - Sửa lỗi chính tả `(inkl.Reis)` → `(inkl. Reis)`, `Osinko Maki` → `Oshinko Maki`.
  - Bổ sung mô tả ẩm thực chuyên nghiệp cho 5 món bị bỏ trống trên Live (`V9`, `V11`, `V12`, `Lachs Bowl`, `Vegi Bowl`).
- **Nâng cấp Hệ thống Kết nối Email Khách hàng & Bếp (`inc/email-templates.php`, `inc/admin-orders.php`, `inc/admin-reservations.php`)**:
  - Thêm header `Reply-To: WOW Thai Steyr <info@wow-thai-restaurant.at>`.
  - Bổ sung `wowthai_email_log` (ring buffer 20 email gần nhất) và hook `wp_mail_failed` để bắt lỗi SMTP từ server.
  - Tự động gửi email HTML Branded khi nhân viên bấm **✓ Annehmen** (`✓ Bestellung #... angenommen – In Zubereitung`).
  - Tự động gửi email HTML Branded khi nhân viên bấm **🥡 Abholbereit** (`🥡 Ihre Bestellung #... ist abholbereit!`).
  - Đính kèm link tải file lịch `.ics` và link tự hủy đặt bàn 1-click trong email xác nhận bàn của khách.
  - Tích hợp thanh trạng thái kết nối E-mail trực quan ngay tại bảng điều khiển Admin Orders.
- **Bộ Kiểm định Tự động**: Tăng từ 62 lên **98 unit assertions** trong `dev/tests/hours.test.php`; vượt qua 10/10 cổng kiểm định tự động `dev/verify.mjs`.

### 2. Phiên bản v1.2.0 – v1.2.3
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

