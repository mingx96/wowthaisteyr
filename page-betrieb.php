<?php
/**
 * Template Name: WOW Thai Betrieb (Staff Operations Portal)
 * Standalone restaurant operations board for orders, table bookings, and customer CRM.
 * Direct domain endpoint: /betrieb/
 */

if (!defined('ABSPATH')) {
    exit;
}

$theme_uri = get_template_directory_uri();
$version = '1.5.0';
// A release number can already be cached before an asynchronous deployment finishes.
$portal_js_version = $version . '-' . substr(hash_file('sha256', get_template_directory() . '/assets/js/portal.js'), 0, 12);
$portal_hours_version = $version . '-' . substr(hash_file('sha256', get_template_directory() . '/assets/js/portal-hours.js'), 0, 12);
$portal_css_version = $version . '-' . substr(hash_file('sha256', get_template_directory() . '/assets/css/portal.css'), 0, 12);

// Fallback direct POST login handler
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wto_portal_login'])) {
    if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['portal_nonce'] ?? '')), 'wowthai_portal_login_nonce')) {
        $creds = [
            'user_login'    => sanitize_text_field($_POST['log'] ?? ''),
            'user_password' => wp_unslash((string)($_POST['pwd'] ?? '')), // verify-ok
            'remember'      => !empty($_POST['rememberme']),
        ];
        $signon = wp_signon($creds, is_ssl());
        if (is_wp_error($signon)) {
            $login_error = 'Ungültige Anmeldedaten. Bitte Zugangsdaten prüfen.';
        } else {
            $is_staff = user_can($signon, 'manage_woocommerce')
                     || user_can($signon, 'manage_options')
                     || user_can($signon, 'edit_shop_orders')
                     || in_array('shop_manager', (array)($signon->roles ?? []), true)
                     || in_array('administrator', (array)($signon->roles ?? []), true);
            if (!$is_staff) {
                wp_logout();
                $login_error = 'Ihr Konto hat keine Berechtigung für das Restaurant-Betriebsportal.';
            } else {
                wp_safe_redirect(home_url('/betrieb/'));
                exit;
            }
        }
    } else {
        $login_error = 'Sicherheitstoken abgelaufen. Bitte erneut versuchen.';
    }
}

$is_logged_in = is_user_logged_in();
// Staff pages must not be stored in shared browser/CDN caches.
nocache_headers();
$current_user = wp_get_current_user();
$is_staff     = $is_logged_in && (
    current_user_can('manage_woocommerce')
    || current_user_can('manage_options')
    || current_user_can('edit_shop_orders')
    || in_array('shop_manager', (array)($current_user->roles ?? []), true)
    || in_array('administrator', (array)($current_user->roles ?? []), true)
);

$prep_time    = function_exists('wowthai_get_kitchen_prep_time') ? wowthai_get_kitchen_prep_time() : '25–35 Min.';
$is_open      = function_exists('wowthai_is_open') ? wowthai_is_open() : null;
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>WOW Thai Betrieb · Restaurant-Verwaltung</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#141210">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="WOW Betrieb">
  <link rel="icon" type="image/png" href="<?php echo esc_url($theme_uri . '/assets/img/logo-sm.png'); ?>">
  <link rel="apple-touch-icon" href="<?php echo esc_url($theme_uri . '/assets/img/logo-sm.png'); ?>">

  <!-- Fonts & Portal CSS -->
  <link rel="stylesheet" href="<?php echo esc_url($theme_uri . '/assets/css/fonts.css?v=' . $version); ?>">
  <link rel="stylesheet" href="<?php echo esc_url($theme_uri . '/assets/css/portal.css?v=' . $portal_css_version); ?>">
</head>
<body>

<?php if (!$is_logged_in): ?>
  <!-- =======================================================================
       BRANDED LOGIN VIEW (Matching Homepage Luxury Dark & Gold)
       ======================================================================= -->
  <main class="wto-login-wrap" role="main">
    <div class="wto-login-card">
      <div class="wto-brand-badge" aria-hidden="true">W</div>
      <h1 class="wto-login-title">WOW Thai Steyr</h1>
      <p class="wto-login-subtitle">Betriebs-Portal · Bestell- &amp; Reservierungsmanagement</p>

      <div id="wto-login-error" class="wto-login-error" style="<?php echo $login_error ? 'display:block;' : ''; ?>">
        <?php echo esc_html($login_error); ?>
      </div>

      <div style="margin-bottom:18px;">
        <a href="<?php echo esc_url(wp_login_url(home_url('/betrieb/'))); ?>" class="wto-login-sso-btn" style="display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;padding:13px 18px;font-size:14px;font-weight:700;width:100%;box-sizing:border-box;background:linear-gradient(135deg, #d4af37, #b8860b);color:#141210;border-radius:10px;box-shadow:0 4px 15px rgba(212,175,55,0.25);transition:all .2s ease;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
          <span>Mit WordPress.com / SSO anmelden</span>
        </a>
        <div style="display:flex;align-items:center;gap:10px;margin:16px 0 10px;color:var(--wto-ink-subtle);font-size:12px;">
          <div style="flex:1;height:1px;background:var(--wto-border-dark);"></div>
          <span>oder mit Passwort anmelden</span>
          <div style="flex:1;height:1px;background:var(--wto-border-dark);"></div>
        </div>
      </div>

      <form id="wto-portal-login-form" class="wto-login-form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <input type="hidden" name="action" value="wowthai_portal_login">
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wowthai_portal_login_nonce')); ?>">
        <input type="hidden" name="wto_portal_login" value="1">
        <input type="hidden" name="portal_nonce" value="<?php echo esc_attr(wp_create_nonce('wowthai_portal_login_nonce')); ?>">

        <div class="wto-login-field">
          <label for="wto-user-login">Benutzername oder E-Mail</label>
          <input type="text" id="wto-user-login" name="log" required autocomplete="username" autofocus>
        </div>

        <div class="wto-login-field">
          <label for="wto-user-pass">Passwort</label>
          <input type="password" id="wto-user-pass" name="pwd" required autocomplete="current-password">
        </div>

        <label class="wto-login-remember">
          <input type="checkbox" name="rememberme" value="forever" checked>
          <span>Angemeldet bleiben</span>
        </label>

        <button type="submit" class="wto-login-btn">
          <span>Anmelden →</span>
        </button>
      </form>

      <div class="wto-login-footer">
        <span>Nur für autorisiertes Restaurant-Personal</span><br>
        <a href="<?php echo esc_url(home_url('/')); ?>" style="color:var(--wto-gold);text-decoration:none;display:inline-block;margin-top:8px;">← Zurück zur Startseite</a>
      </div>
    </div>
  </main>

<?php elseif (!$is_staff): ?>
  <!-- =======================================================================
       ACCESS DENIED VIEW (User is logged in but lacks staff role)
       ======================================================================= -->
  <main class="wto-login-wrap" role="main">
    <div class="wto-login-card">
      <div class="wto-brand-badge" style="background:var(--wto-red);color:#fff;" aria-hidden="true">✕</div>
      <h1 class="wto-login-title">Keine Berechtigung</h1>
      <p class="wto-login-subtitle" style="line-height:1.6;">
        Sie sind angemeldet als <strong><?php echo esc_html($current_user->display_name); ?></strong>.<br>
        Dieses Benutzerkonto hat keine Berechtigung für das Restaurant-Betriebsportal.
      </p>
      <a href="<?php echo esc_url(wp_logout_url(home_url('/betrieb/'))); ?>" class="wto-btn btn-primary" style="text-decoration:none;margin-top:16px;">
        Mit anderem Konto anmelden ⎋
      </a>
      <div class="wto-login-footer">
        <a href="<?php echo esc_url(home_url('/')); ?>" style="color:var(--wto-gold);text-decoration:none;">← Zurück zur Startseite</a>
      </div>
    </div>
  </main>

<?php else: ?>
  <!-- =======================================================================
       AUTHORIZED STAFF OPERATIONS PORTAL APPLICATION
       ======================================================================= -->
  <div id="wto-portal-app" class="wto-portal-app">

    <!-- One dropdown for navigation, filters and staff options. -->
    <header class="wto-portal-header wto-command-header">
      <div class="wto-header-brand" title="WoW Thai Steyr">
        <img src="<?php echo esc_url($theme_uri . '/assets/img/logo-sm.png'); ?>" width="48" height="40" alt="WoW Thai Steyr">
      </div>
      <nav class="wto-primary-nav" aria-label="Bestellungen und Reservierungen">
        <button type="button" class="wto-nav-tab active" data-tab="orders"><span>Bestellungen</span><span id="wto-badge-orders" class="wto-nav-badge" title="Neu · alle Tage">—</span></button>
        <button type="button" class="wto-nav-tab" data-tab="reservations"><span>Reservierungen</span><span id="wto-badge-res" class="wto-nav-badge" title="Offen · alle Tage">—</span></button>
      </nav>
      <span id="wto-current-section" class="wto-sr-only">Bestellungen</span>
      <details id="wto-command-menu" class="wto-command-menu">
        <summary><span>Menü</span><span aria-hidden="true">▾</span></summary>
        <div class="wto-command-panel">
          <nav class="wto-portal-nav" aria-label="Betriebsbereiche">
      <button type="button" class="wto-nav-tab" data-tab="hours"><span>Öffnungszeiten</span></button>
      <button type="button" class="wto-nav-tab" data-tab="customers">
        <span>👥 Kunden &amp; CRM</span>
      </button>

      <button type="button" class="wto-nav-tab" data-tab="stats">
        <span>📊 Tagesübersicht</span>
      </button>
    </nav>
          <div data-menu-section="orders"><h3>Küchenspalte</h3> <div class="wto-mobile-col-switcher" id="wto-orders-col-switcher">
          <button type="button" class="wto-mcol-btn active" data-col="new">
            🔔 Neu <span class="wto-mcol-badge" id="wto-m-count-new">0</span>
          </button>
          <button type="button" class="wto-mcol-btn" data-col="prep">
            🍳 In Zubereitung <span class="wto-mcol-badge" id="wto-m-count-prep">0</span>
          </button>
          <button type="button" class="wto-mcol-btn" data-col="ready">
            🥡 Abholbereit <span class="wto-mcol-badge" id="wto-m-count-ready">0</span>
          </button>
        </div></div>
          <div data-menu-section="reservations" hidden><h3>Reservierungen</h3><div class="wto-res-filters">
              <button type="button" class="active" data-view="daily">Tag</button>
              <button type="button" data-view="pending">Offen</button>
              <button type="button" data-view="all">Alle</button>
            </div><h3>Zeitraum</h3><div class="wto-res-shift-filters" style="display:flex;gap:6px;margin-left:8px;">
              <button type="button" class="wto-shift-btn active" data-shift="all">Alle</button>
              <button type="button" class="wto-shift-btn" data-shift="lunch">☀️ Mittag</button>
              <button type="button" class="wto-shift-btn" data-shift="dinner">🌙 Abend</button>
            </div> <div class="wto-mobile-col-switcher" id="wto-res-col-switcher">
          <button type="button" class="wto-mcol-btn active" data-col="new">
            📩 Neu <span class="wto-mcol-badge" id="wto-m-res-new">0</span>
          </button>
          <button type="button" class="wto-mcol-btn" data-col="prep">
            📅 Erwartet <span class="wto-mcol-badge" id="wto-m-res-prep">0</span>
          </button>
          <button type="button" class="wto-mcol-btn" data-col="ready">
            🍽️ Im Haus <span class="wto-mcol-badge" id="wto-m-res-ready">0</span>
          </button>
        </div></div>
          <div data-menu-section="customers" hidden><h3>Kundenfilter</h3><div class="wto-command-option">
            <label for="wto-cust-consent" style="font-size:12px;color:var(--wto-ink-muted);">Marketing-Einwilligung:</label>
            <select id="wto-cust-consent" style="height:38px;background:var(--wto-surface);border:1px solid var(--wto-line);color:var(--wto-ink);border-radius:6px;padding:0 10px;">
              <option value="">Alle Kunden</option>
              <option value="eligible">Angemeldet (Subscribed)</option>
              <option value="unsubscribed">Abgemeldet</option>
              <option value="unknown">Nicht festgelegt</option>
            </select>
          </div></div>
          <div class="wto-command-options">
            <h3>Betrieb</h3>
            <div class="wto-command-option"><label for="wto-kitchen-prep-select">Küchenzeit</label><select id="wto-kitchen-prep-select">
              <?php $prep_opts = ['15–20 Min.', '25–35 Min.', '40–50 Min.', '60+ Min.']; foreach ($prep_opts as $p): ?>
              <option value="<?php echo esc_attr($p); ?>" <?php selected($prep_time, $p); ?>><?php echo esc_html($p); ?></option>
              <?php endforeach; ?>
            </select></div>
            <div class="wto-command-option"><button type="button" id="wto-sound-toggle" class="wto-btn-sound">🔔 Signal: AN</button><button type="button" id="wto-help-toggle" class="wto-help-toggle" aria-haspopup="dialog">Hilfe</button></div>
            <div class="wto-command-option wto-command-meta"><span>Wien <span id="wto-live-clock">--:--:--</span></span><span data-network-text>Verbunden</span></div>
            <div class="wto-command-option wto-command-meta"><span><?php echo esc_html($current_user->display_name); ?></span><a href="<?php echo esc_url(wp_logout_url(home_url('/betrieb/'))); ?>" class="wto-btn-logout">Abmelden ⎋</a></div>
          </div>
        </div>
      </details>
      <div id="wto-opening-status" class="wto-status-pill <?php echo $is_open === null ? 'unknown' : ($is_open ? 'open' : 'closed'); ?>" role="status"><?php echo $is_open === null ? 'Küche unbekannt' : ($is_open ? 'Küche geöffnet' : 'Küche geschlossen'); ?></div>
      <div class="wto-network-pill"><span id="wto-network-dot" class="wto-network-dot"></span><span data-network-text>Verbunden</span></div>

    </header>
    <p id="wto-queue-status" class="wto-sr-only" role="status" aria-atomic="true"></p>

    <dialog id="wto-dialog-help" class="wto-modal wto-help-dialog" aria-labelledby="wto-help-title">
      <div class="wto-modal-head">
        <h2 id="wto-help-title">Betrieb · Hilfe</h2>
        <button type="button" class="wto-btn-close" aria-label="Hilfe schließen" onclick="document.getElementById('wto-dialog-help').close()">×</button>
      </div>
      <div class="wto-help-body">
        <h3>Bestellungen</h3>
        <p>Das Küchenboard enthält aktive Bestellungen aller Tage. Im Archiv sind abgeschlossene, stornierte, fehlgeschlagene und erstattete Bestellungen nur lesbar.</p>
        <h3>Reservierungen · Zeiträume</h3>
        <p>Tag: gewähltes Datum. Offen / Alle: alle Tage; das Datumsfeld ist dann deaktiviert. Mittag vor 16 Uhr, Abend ab 16 Uhr sind Filter, keine Öffnungszeiten. Stornos stehen separat und zählen nicht zu Reservierungen oder Gästen.</p>
        <h3>Seiten und Kennzahlen</h3>
        <p>Die Kennzahlen gelten für den gesamten Filter. Bei mehreren Seiten zeigen die Karten die gewählte Seite. Weitere Seiten sind unten erreichbar.</p>
        <h3>Tagesübersicht</h3>
        <p>Zuordnung nach Erstellungsdatum in Europe/Vienna. Storniert / fehlgeschlagen bleibt sichtbar und zählt nicht zum Bestellwert. Erstattungen sind bisher für diese Bestellungen erfasste Beträge, auch von anderen Tagen. Bestellwerte und Zahlungsangaben sind kein Kassenabschluss oder Nachweis kassierter Beträge. „Barzahlung vor Ort“ nennt die Zahlungsart und bestätigt keinen Zahlungseingang.</p>
        <h3>Abgleich</h3>
        <p>Automatische Aktualisierung alle 12 Sekunden. Bei Verbindungsfehlern bleiben Daten zur Orientierung sichtbar und werden als möglicherweise veraltet gekennzeichnet.</p>
      </div>
    </dialog>

    <!-- Main Viewport -->
    <p id="wto-sync-status" class="wto-data-status" role="status" aria-live="polite">Daten werden geladen …</p>
    <main class="wto-portal-viewport">

      <!-- ===================================================================
           VIEW 1: BESTELLUNGEN (WIX-STYLE 3-COLUMN KANBAN)
           =================================================================== -->
      <section id="wto-view-orders" class="wto-view active" aria-label="Bestellungs-Management">
        <div class="wto-orders-toolbar"><div class="wto-orders-switch">
            <button type="button" class="active" data-view="kanban">Küchenboard</button>
            <button type="button" data-view="archive">Archiv</button>
          </div></div>



        <!-- Kanban Board (3 Columns on Desktop, 1 Active Column on Mobile) -->
        <div id="wto-kanban-board" class="wto-kanban-board show-new">
          <!-- Col 1: Neu -->
          <div class="wto-kanban-col col-new">
            <div class="wto-col-head">
              <h2><span>🔔 Neu eingegangen</span></h2>
              <span id="wto-count-new" class="wto-col-count">0</span>
            </div>
            <div id="wto-col-new-cards" class="wto-cards-scroll">
              <p class="wto-empty-col">Lade Bestellungen …</p>
            </div>
          </div>

          <!-- Col 2: In Zubereitung -->
          <div class="wto-kanban-col col-prep">
            <div class="wto-col-head">
              <h2><span>🍳 In Zubereitung</span></h2>
              <span id="wto-count-prep" class="wto-col-count">0</span>
            </div>
            <div id="wto-col-prep-cards" class="wto-cards-scroll">
              <p class="wto-empty-col">Küche ist frei.</p>
            </div>
          </div>

          <!-- Col 3: Abholbereit -->
          <div class="wto-kanban-col col-ready">
            <div class="wto-col-head">
              <h2><span>🥡 Abholbereit</span></h2>
              <span id="wto-count-ready" class="wto-col-count">0</span>
            </div>
            <div id="wto-col-ready-cards" class="wto-cards-scroll">
              <p class="wto-empty-col">Keine fertigen Speisen.</p>
            </div>
          </div>
        </div>

        <!-- Archive List (Hidden by default) -->
        <div id="wto-archive-list" class="wto-archive-grid" hidden></div>
        <nav id="wto-orders-pagination" class="wto-pagination" aria-label="Bestellseiten"></nav>
        <p id="wto-orders-feedback" class="wto-data-status" role="status"></p>
      </section>

      <!-- ===================================================================
           VIEW 2: RESERVIERUNGEN (WIX-STYLE TABLE BOOKINGS)
           =================================================================== -->
      <section id="wto-view-reservations" class="wto-view" aria-label="Reservierungs-Management">
        <div class="wto-res-toolbar">
          <div class="wto-res-nav">


            <div class="wto-res-date-picker">
              <label for="wto-res-date-input">Datum</label>
              <input type="date" id="wto-res-date-input">
            </div>


          </div>

          <p id="wto-res-scope" class="wto-data-status" role="status"></p>
          <div class="wto-res-kpis">
            <span class="wto-kpi-item"><span class="wto-kpi-label">Res.</span> <strong id="wto-kpi-res-total">—</strong></span>
            <span class="wto-kpi-item">Gäste <strong id="wto-kpi-res-guests">—</strong></span>
            <button type="button" id="wto-btn-new-reservation" aria-label="Telefonreservierung hinzufügen" title="Telefonreservierung hinzufügen" class="wto-btn btn-primary" style="height:38px;padding:0 14px;">
              +
            </button>
          </div>
        </div>



        <div id="wto-res-list" class="wto-res-scroll" style="flex:1;display:flex;flex-direction:column;min-height:0;overflow:auto;">
          <p class="wto-empty-col">Lade Reservierungen …</p>
        </div>
        <nav id="wto-res-pagination" class="wto-pagination" aria-label="Reservierungsseiten"></nav>
        <p id="wto-res-feedback" class="wto-data-status" role="status"></p>
      </section>

      <!-- ===================================================================
           VIEW 3: KUNDEN & CRM (10.106 WIX / WOOCOMMERCE CUSTOMERS)
           =================================================================== -->
      <section id="wto-view-customers" class="wto-view" aria-label="Kunden-Verwaltung">
        <div class="wto-cust-toolbar">
          <div class="wto-search-wrap">
            <span class="wto-search-icon">🔍</span>
            <input type="search" id="wto-cust-search" placeholder="Name, E-Mail oder Telefonnummer suchen …">
          </div>


        </div>

        <div class="wto-cust-table-wrap">
          <table class="wto-table">
            <thead>
              <tr>
                <th>Kunde &amp; Ort</th>
                <th>Kontakt</th>
                <th>Newsletter</th>
                <th>Herkunft</th>
                <th>Aktion</th>
              </tr>
            </thead>
            <tbody id="wto-cust-tbody">
              <tr><td colspan="5" style="text-align:center;padding:24px;">Lade Kunden …</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===================================================================
           VIEW 4: TAGESABSCHLUSS & STATISTIKEN
           =================================================================== -->
      <section id="wto-view-stats" class="wto-view" aria-label="Tagesübersicht">
        <div class="wto-daily-toolbar"><label for="wto-daily-date">Datum (Wien)</label> <input id="wto-daily-date" type="date"></div>
        <p id="wto-daily-scope" class="wto-data-status" role="status">Lade Tagesübersicht …</p>
        <div class="wto-stats-grid">
          <div class="wto-stat-card">
            <span>Bestellungen am gewählten Tag</span>
            <strong id="wto-stat-orders-today">—</strong>
          </div>
          <div class="wto-stat-card">
            <span>Bestellwert nach Erstattungen</span>
            <strong id="wto-stat-revenue-today">—</strong>
          </div>
          <div class="wto-stat-card">
            <span>Reservierungen (ohne Storno)</span>
            <strong id="wto-stat-res-today">—</strong>
          </div>
          <div class="wto-stat-card">
            <span>Gäste (ohne Storno)</span>
            <strong id="wto-stat-guests-today">—</strong>
          </div>
        </div>
        <p id="wto-daily-totals" class="wto-data-status"></p>
        <p class="wto-data-status wto-daily-note">Bestellwerte · kein Kassenabschluss. Details unter Hilfe.</p>
        <p id="wto-daily-backlog" class="wto-data-status"></p>
        <div id="wto-daily-orders" class="wto-daily-orders"></div>
        <nav id="wto-daily-pagination" class="wto-pagination" aria-label="Tagesbestellseiten"></nav>
        <p id="wto-daily-feedback" class="wto-data-status" role="status"></p>
      </section>


      <section id="wto-view-hours" class="wto-view" aria-labelledby="wto-hours-title">
        <div class="wto-hours-shell">
          <div class="wto-hours-heading"><div><h1 id="wto-hours-title">Öffnungszeiten</h1><p>Europe/Vienna · Sondertage ersetzen den Wochenplan.</p></div><button type="button" id="wto-hours-reload" class="wto-btn btn-secondary">Stand neu laden</button></div>
          <p id="wto-hours-message" role="status" aria-atomic="true" tabindex="-1">Öffnungszeiten werden beim Öffnen geladen.</p>
          <p id="wto-hours-permission"></p>
          <div class="wto-hours-tabs" role="tablist" aria-label="Öffnungszeiten bearbeiten">
            <button type="button" id="wto-hours-tab-weekly" role="tab" aria-controls="wto-hours-weekly" aria-selected="true" data-hours-tab="weekly">Wochenplan</button>
            <button type="button" id="wto-hours-tab-exceptions" role="tab" aria-controls="wto-hours-exceptions" aria-selected="false" tabindex="-1" data-hours-tab="exceptions">Sondertage</button>
            <button type="button" id="wto-hours-tab-preview" role="tab" aria-controls="wto-hours-preview" aria-selected="false" tabindex="-1" data-hours-tab="preview">Vorschau</button>
          </div>
          <div id="wto-hours-weekly" role="tabpanel" aria-labelledby="wto-hours-tab-weekly">
            <p>Mehrere Zeitfenster pro Tag möglich. Keine Überschneidungen oder Zeiten über Mitternacht.</p>
            <fieldset id="wto-hours-weekly-fields" disabled><legend class="wto-sr-only">Wochenplan</legend><div id="wto-hours-days"></div></fieldset>
          </div>
          <div id="wto-hours-exceptions" role="tabpanel" aria-labelledby="wto-hours-tab-exceptions" hidden>
            <fieldset id="wto-hours-exception-fields" disabled><legend>Sondertag oder Zeitraum</legend>
              <div class="wto-hours-fields"><label>Von<input id="wto-hours-start" type="date" required></label><label>Bis einschließlich<input id="wto-hours-end" type="date" required></label>
              <label>Regel<select id="wto-hours-mode"><option value="closed">Geschlossen</option><option value="hours">Eigene Zeiten</option><option value="remove">Ausnahme entfernen (Wochenplan)</option></select></label></div>
              <p>Maximal 367 Tage. Entfernen stellt den Wochenplan wieder her.</p>
              <label class="wto-hours-reason">Öffentlicher Hinweis für Gäste<input id="wto-hours-reason" type="text" maxlength="500"></label>
              <div id="wto-hours-exception-ranges" hidden><div id="wto-hours-custom-ranges"></div><button type="button" id="wto-hours-add-custom" class="wto-btn btn-secondary">Zeitfenster hinzufügen</button></div>
            </fieldset>
            <details class="wto-hours-existing"><summary>Gespeicherte Sondertage · alle Daten</summary><ul id="wto-hours-existing"></ul></details>
          </div>
          <div id="wto-hours-preview" role="tabpanel" aria-labelledby="wto-hours-tab-preview" hidden>
            <div class="wto-hours-fields"><label>Datum (Wien)<input id="wto-hours-preview-date" type="date" required></label><button type="button" id="wto-hours-published" class="wto-btn btn-secondary">Veröffentlichte Zeiten anzeigen</button></div>
            <p id="wto-hours-preview-source">Noch keine Vorschau geladen.</p>
            <div id="wto-hours-result"></div>
            <div id="wto-hours-conflicts"></div>
            <label id="wto-hours-ack-label" class="wto-hours-ack" hidden><input id="wto-hours-ack" type="checkbox">Ich habe die betroffenen Vorgänge geprüft und bestätige die Änderung. Reservierungen und Bestellungen bleiben bestehen.</label>
          </div>
          <div class="wto-hours-actions"><button type="button" id="wto-hours-check" class="wto-btn btn-secondary" disabled>Entwurf prüfen</button><button type="button" id="wto-hours-save" class="wto-btn btn-primary" disabled hidden>Geprüfte Änderung speichern</button></div>
        </div>
      </section>
    </main>

    <!-- Modal for New Phone Reservation -->
    <dialog id="wto-dialog-new-res" class="wto-modal">
      <div class="wto-modal-head">
        <h3>+ Telefonische Reservierung aufnehmen</h3>
        <button type="button" class="wto-btn-close" aria-label="Reservierung schließen" onclick="document.getElementById('wto-dialog-new-res').close()">×</button>
      </div>
      <form class="wto-modal-form">
        <div class="wto-form-row">
          <label>Name des Gastes *</label>
          <input type="text" name="name" required placeholder="z. B. Herr Bauer">
        </div>
        <div class="wto-form-row">
          <label>Telefonnummer *</label>
          <input type="tel" name="phone" required placeholder="z. B. +43 676 1234567">
        </div>
        <div class="wto-form-row">
          <label>E-Mail (optional für Bestätigung)</label>
          <input type="email" name="email" placeholder="gast@example.at">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="wto-form-row">
            <label>Personenanzahl *</label>
            <input type="number" name="party" min="1" max="50" value="2" required>
          </div>
          <div class="wto-form-row">
            <label>Datum *</label>
            <input type="date" name="date" required>
          </div>
        </div>
        <div class="wto-form-row">
          <label>Uhrzeit (Verfügbare Zeiten) *</label>
          <select name="time" required>
            <option value="">Wähle ein Datum …</option>
          </select>
        </div>
        <div class="wto-form-row">
          <label>Anmerkung / Sonderwünsche</label>
          <textarea name="notes" placeholder="z. B. Hochstuhl, Fensterplatz, Geburtstag"></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
          <button type="button" class="wto-btn btn-secondary" onclick="document.getElementById('wto-dialog-new-res').close()">Abbrechen</button>
          <button type="submit" class="wto-btn btn-primary">Reservierung speichern</button>
        </div>
      </form>
    </dialog>

  </div>
<?php endif; ?>

<!-- Hidden Print Area for 80mm Thermal Receipt -->
<div id="wto-print-area"></div>

<!-- Portal Configuration for JS -->
<script>
window.wtoPortalConfig = {
  api: '<?php echo esc_url_raw(rest_url("wowthai-operations/v1/")); ?>',
  hours_api: '<?php echo esc_url_raw(rest_url("wowthai-hours/v1/")); ?>',
  read_api: '<?php echo esc_url_raw(rest_url("wowthai-portal/v1/")); ?>',
  read_revision: '<?php echo esc_js($portal_js_version); ?>',
  nonce: '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>',
  ajax_url: '<?php echo esc_url_raw(admin_url("admin-ajax.php")); ?>',
  user: '<?php echo esc_js($current_user->display_name ?? ""); ?>',
  prep_time: '<?php echo esc_js($prep_time); ?>'
};
</script>
<script src="<?php echo esc_url($theme_uri . '/assets/js/portal.js?v=' . $portal_js_version); ?>"></script>

<script src="<?php echo esc_url($theme_uri . '/assets/js/portal-hours.js?v=' . $portal_hours_version); ?>"></script>

</body>
</html>
