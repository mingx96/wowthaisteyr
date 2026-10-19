# COORDINATION & HANDOVER PROTOCOL (WOW THAI STEYR)

## Betrieb repair checkpoint — 2026-10-19T22:30:18+00:00

- Task ID: `notion:3fe9ccae-c10f-811c-aa0f-f561d4c47a8f`. Execution owner: Codex in the assigned repair chat; owner/scope registered in the existing private project task. The user approved implementing/releasing these scoped repairs. Historical multi-branch/other-agent local work remains outside scope.
- Scope: theme portal/read-only API only, v1.4.2. Canonical kitchen hours, archive visibility/pagination, reservation scope/date/totals, global alerts, daily order-value drilldown and accessible polling feedback. Current Operations plugin remains responsible for writes, version tokens, email and payment behavior. No product/customer/production database or CRM changes.
- Base: main `5980f6bb5b72a3f9fbb85e9560cb72e48ad74882`; branch `fix/betrieb-consistency-20261019`. HEAD before commit: base; scoped PHP/JS/CSS/tests/docs are in the isolated worktree. The proposed branch/PR HEAD is authoritative after commit; no unrelated WIP was moved.
- Verification: native PHP8.2.34/Node24, `node dev/verify.mjs` G1–G11 PASS (98 hours, 13 menu-description, 25 read-model assertions). `python dev/tests/portal.browser.py` PASS at1440/390/768px using actual template/assets and synthetic APIs. Independent review approved after global-chime/focus fixes. Local WordPress7.1.3/WooCommerce11.2.1 integration:14 assertions PASS each in legacy and HPOS, including REST permissions/no-store, >200orders, real refund and full detail reconciliation. Local fixture database only; outbound mail/HTTP blocked. Snapshot DTOs used for integration are not a live plugin source fingerprint.
- Tested runtime source fingerprints: assets/js/portal.js: `e0e82f0aec6ab767534c6e06d726238ed0a629cef314c35d9c918ed975548647`; inc/portal-read-model.php: `692b59c7bd9c6e7fb3490ed4b27dc0ab01315a3be73b8df61660919708493c61`.
- Release: pending. Public live JS previously matched main (`da88798512f08019022dfd20e36567bc9397b4c27e4023072fe9e0a9d9a80889`); current live plugin reports Operations1.0.2, its source bytes are unverified. GitHub's main publish workflow uploads an artifact; external deployment and authenticated staff API/UI smoke require separate observed evidence.
- Rollback: preserve base code/ZIP and revert only scoped theme changes if release smoke fails; do not restore/sync a database. Managed backup check reports active/last attempt finished, not a restore rehearsal.
- Notion: owner/scope and Business OS audit plan saved and read back in the existing project. Lifecycle remains in progress until release evidence is recorded; no cash-closing/POS feature in this patch.
- Next: publish reviewable branch/PR, verify exact-head CI, release through the configured path, compare live assets and staff read API behavior, then record observed result/limitations in the same task.


## Documentation setup checkpoint — 2026-10-19T20:16:00Z

- Task reference: `notion:3fe9ccae-c10f-81de-8d73-d35baf473442`. The task's private record owns lifecycle, acceptance and execution owner; no private Notion URL is published here.
- Scope: documentation only on `docs/ai-handoff-contract-20261019`, based on main `2784511`. No PHP/JS/CSS/runtime/workflow changes, no merge, deployment or database write.
- Delivery boundary: these instructions are proposed on this branch/PR, not activated on default main until reviewed and merged. Check the branch/PR for its current HEAD and CI, not a stale hash in prose.
- Next: review docs, CI and main's publish trigger, then obtain the required merge decision. Opening the PR does not complete main activation.

## Verified technical checkpoint — 2026-10-19T20:10:27Z

- **Source:** GitHub read-only audit. Default branch `main`, HEAD [2784511](https://github.com/mingx96/wowthaisteyr/commit/278451185705cebc7b0e86f3617ee6fb544b493b); source `style.css` says 1.4.1. Neither value proves the running live revision.
- **Menu HTML fix:** [PR #5](https://github.com/mingx96/wowthaisteyr/pull/5) merged 2026-10-19T19:02:51Z from `fix/menu-description-html-20261019` / `ca6178f`. No open PRs were returned at audit time.
- **Evidence:** [Verify WordPress theme](https://github.com/mingx96/wowthaisteyr/actions/runs/39251193554) passed on `ca6178f`; [agent skills](https://github.com/mingx96/wowthaisteyr/actions/runs/39251193684) and [design/workflow skills](https://github.com/mingx96/wowthaisteyr/actions/runs/39251193598) also passed. The PR reports 13 menu-description cases and 98 hours assertions. These are not live WordPress/browser checkout checks.
- **Live:** unknown in this audit. No deployment, product save, database write or live test was performed. The checked-in `.github/workflows/wpcom.yml` runs “Publish Website” artifact creation on every push to main without a path filter. The external deployment connection is not verified; historical hub notes describe auto-deploy. Recheck the actual deployment configuration and runtime before release work.
- **Owner / shared-site claim:** not reverified. The older Antigravity claim below is preserved as a historical claim, not silently released or assigned to the current reader. Confirm current execution owner, allowed scope and site-write permission in the existing private task before any shared-site write.
- **Next action:** read the existing task's latest acceptance/release decision and compare the approved source with the active runtime using read-only checks. Do not repeat the old “review/merge PR #5” step; do not deploy based only on this file.

## Checkpoint contract

Follow [AGENTS.md](AGENTS.md). Notion owns task lifecycle/priority/decisions; this file owns the repo's technical checkpoint and shared-site coordination evidence. Keep private task URLs/session links out of this public repo.

For the active task record: `Task ID`; `updated_at/observed_at (UTC)`; `execution owner + allowed file/site scope`; `base/branch/HEAD/PR`; `dirty/untracked WIP`; `changed`; `test command + environment + SHA + result + evidence URL`; `release observed/reported/unknown`; `blocker/resume condition`; `next action`; `Notion sync time or pending`.

Checkpoint before stopping or handing off. Re-read current owner before accepting a handoff. Missing freshness is a reason to verify, not permission to overwrite. Review the actual script and [docs/TESTING.md](docs/TESTING.md), not historic gate counts.

## Historical records — preserve context, do not treat as current authorization

Entries below record what was known at their written time. “Active/current”, branch/PR state, version numbers, machine paths and test counts in these entries are historical unless reverified above. Their safety constraints continue to apply; they do not grant permission to merge, deploy or mutate a database.


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

