# AI Agent Skills cho WOW Thai Steyr — Windows

**Mục tiêu:** cài skill hỗ trợ Codex và Antigravity vào **bản local** của custom WordPress PHP Theme (`wowthaisteyr`). Các câu lệnh dưới đây **chưa được chạy trên PC của người dùng**. Không chạy trên máy chủ WordPress production.

## 0. Đọc trước khi cài

- [WordPress/agent-skills — compatibility](https://github.com/WordPress/agent-skills/blob/main/docs/compatibility-policy.md) hiện nhắm tới **WordPress core 7.0+**. Header `style.css` của theme này chỉ ghi `Tested up to: 6.7` — đó **không phải** chứng cứ về phiên bản WordPress đang hoạt động. Xác minh core + WooCommerce + PHP trên **local/staging** trước khi áp dụng lời khuyên API mới.
- Các skill upstream có thể hướng dẫn gọi WP-CLI, chạy shell, ghi file hoặc kết nối dịch vụ. Đọc `SKILL.md` trước, chỉ cấp quyền phù hợp nhiệm vụ.
- `COORDINATION.md` điều phối tác nhân nhưng không khóa việc ghi đồng thời. Không cho hai agent cùng sửa staging/live.
- PR [#1](https://github.com/mingx96/wowthaisteyr/pull/1) chứa `AGENTS.md`, skills nội bộ và CI; nếu chưa merge, chúng **chưa tồn tại trên main**.

## 1. Chuẩn bị bản local

Cài Git for Windows và Node.js LTS; mở PowerShell. Nếu đã clone repository, **không** clone đè:

```powershell
New-Item -ItemType Directory -Path C:\AI-Workspace -Force | Out-Null
Set-Location C:\AI-Workspace
git clone https://github.com/mingx96/wowthaisteyr.git
Set-Location .\wowthaisteyr
git status --short
git switch -c setup/agent-skills-local
node --version
npm --version
```

`git switch -c` chỉ dùng một lần cho branch mới; nếu đã tồn tại, chọn tên mới. Nếu working tree có thay đổi, **dừng và review**. Không dùng `reset --hard`.

Muốn xem tài liệu cài đang nằm trong PR này trước khi merge:

```powershell
git fetch origin docs/agent-skills-install-20261017
git show origin/docs/agent-skills-install-20261017:docs/INSTALL-AGENT-SKILLS.md
```

### Kiểm tra phiên bản thực tế

Từ **thư mục WordPress installation** trên local/staging có WP-CLI (không phải từ theme repo):

```powershell
wp core version
wp plugin get woocommerce --field=version
php --version
```

Nếu các lệnh này không khả dụng, đọc phiên bản trong WordPress Admin của môi trường được cấp quyền hoặc nhờ agent chỉ kiểm tra mã tĩnh. **Chưa dùng WP 7-only patterns khi chưa xác minh core.**

## 2. Xem trước WordPress skills — không cài toàn bộ

```powershell
npx skills@latest add WordPress/agent-skills --list
```

Các lựa chọn ban đầu: `wp-project-triage` và `wp-performance`. Chỉ thêm `wp-wpcli-and-ops` hoặc `wp-playground` khi cần, trong môi trường được cấp quyền. Tránh cài mặc định `wp-block-themes` cho PHP Theme hiện tại.

**Cách chính thức từ WordPress cho cả Codex và Antigravity, với dry-run:**

```powershell
Set-Location C:\AI-Workspace
git clone https://github.com/WordPress/agent-skills.git wordpress-agent-skills-src
Set-Location .\wordpress-agent-skills-src
node shared/scripts/skillpack-build.mjs --clean --targets=codex,antigravity
node shared/scripts/skillpack-install.mjs --dest=..\wowthaisteyr --targets=codex,antigravity --skills=wp-project-triage,wp-performance --dry-run
```

Chỉ sau khi hiểu phiên bản/compatibility, review kết quả dry-run và chấp thuận việc ghi file: chạy lại lệnh install **không có `--dry-run`**. Installer chính thức sử dụng `.codex/skills/` cho Codex và `.agents/skills/` cho Antigravity; kiểm tra thực tế các đường dẫn được tạo. **Không cài một skill lần nữa bằng `npx skills add` sau khi đã dùng installer này.**

Nếu clone nguồn đã có, dùng `git -C .\wordpress-agent-skills-src status` / `git pull --ff-only` thay vì clone đè. Không copy toàn bộ source repo WordPress vào theme.

## 3. Web design — cài riêng khi có nhiệm vụ UI

**Audit trước:** xem skill của Vercel:

```powershell
Set-Location C:\AI-Workspace\wowthaisteyr
npx skills@latest add vercel-labs/agent-skills --list
```

Sau khi review `web-design-guidelines`, cài *chỉ* skill đó theo installer tương tác:

```powershell
npx skills@latest add vercel-labs/agent-skills --skill web-design-guidelines
```

Chọn đúng target agent/installation scope. Không cài toàn bộ React/Next.js skills vào theme PHP.

**Visual design:** [UI UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) có CLI riêng:

```powershell
npm install -g ui-ux-pro-max-cli
uipro init --ai universal --dry-run
```

Chỉ khi preview hợp lý, chạy `uipro init --ai universal` (không `--force`). Cách `universal` dùng cấu trúc `.agents/skills/`; kiểm tra phát hiện skill trong từng agent. Có thể dùng target riêng `--ai codex` hoặc `--ai antigravity` nếu phiên bản installer yêu cầu. **Không cài cả 2 gói UI trong một nhiệm vụ chỉ để tăng số skills**; chọn một để thử, đo kết quả, rồi mở rộng.

**Workflow:** [Superpowers](https://github.com/obra/superpowers) là lựa chọn *tùy chọn* cho việc lập kế hoạch và TDD. Codex App/CLI dùng plugin marketplace, Antigravity hỗ trợ `agy plugin install https://github.com/obra/superpowers`. Chỉ thử trên một project và không stack thêm bộ workflow khác ngay.

## 4. Kiểm chứng code ≠ kiểm chứng checkout

```powershell
Set-Location C:\AI-Workspace\wowthaisteyr
git status --short
git diff --stat
node dev/verify.mjs
```

- Script `verify.mjs` kiểm tra G1–G10 và chạy PHP syntax/hours tests **chỉ khi tìm thấy PHP CLI**. Một local `PASS` khi thiếu PHP không chứng minh PHP tests đã chạy.
- CI trong [PR #1](https://github.com/mingx96/wowthaisteyr/pull/1) cài PHP 8.2/Node để kiểm thử kỹ thuật, nhưng **không** có Playwright WooCommerce end-to-end.
- Browser checkout validation cần một WordPress/WooCommerce **local/staging** với dữ liệu sản phẩm giả, email giả và payment test. Theo checklist của PR #1 tại `docs/woocommerce-qa.md` sau khi PR được merge.
- Chưa cài WordPress Coding Standards (PHPCS) hoặc Playwright runner vào project bởi tài liệu này. Đó là hạng mục CI/E2E riêng để review.

**Không deploy, không thay đổi Live payment/checkout, không import/overwrite production database**, không gửi đơn hàng hoặc email thật trong quá trình test.

## 5. Commit an toàn

Sau cài đặt, xem `git status`, `git diff` và nội dung file của skill. Loại bỏ generated files hoặc secrets không cần version hóa; chỉ commit bộ skill đã duyệt qua một Pull Request riêng. Các file được commit lên repo **PUBLIC** sẽ hiển thị công khai. Sử dụng GitHub branch/PR, không push thẳng lên `main`.

`AI-System` và `wowthaisteyr` là hai repository độc lập. Không giả định tác nhân cloud có thể nhìn thấy thư mục sibling trên PC.
