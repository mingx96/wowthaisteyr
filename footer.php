<?php
$wow_hd   = function_exists('wowthai_hours_display') ? wowthai_hours_display() : [];
$wow_info = function_exists('wowthai_get_restaurant_data') ? wowthai_get_restaurant_data() : [];
$wow_tel  = $wow_info['phone_tel'] ?? '+43725274973';
$wow_ph   = $wow_info['phone'] ?? '+43 7252 74973';
$wow_mail = $wow_info['email'] ?? 'info@wow-thai-restaurant.at';
?>
<footer class="site-footer">
  <div class="wrap footer-content-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:32px;padding:48px 0 32px 0;border-top:1px solid var(--line);font-size:13px;line-height:1.7;">
    <div>
      <h3 style="font-family:var(--serif);font-size:18px;color:var(--ink);margin:0 0 12px 0;letter-spacing:0.5px;">WOW THAI · STEYR</h3>
      <p style="color:var(--muted);margin:0 0 8px 0;">Authentische thailändische Spezialitäten, Wok-Küche und frisches Sushi mitten im City Point Steyr.</p>
      <p style="color:var(--muted);margin:0;">Leopold-Werndl-Straße 2 (1. OG)<br>4400 Steyr · Österreich</p>
    </div>

    <div>
      <h4 style="font-size:13px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:1px;margin:0 0 12px 0;">Öffnungszeiten</h4>
      <div data-wowthai-hours-rows>
        <?php foreach (($wow_hd['rows'] ?? []) as $row): ?>
          <p data-wowthai-hours-row style="color:var(--muted);margin:0 0 6px 0;">
            <strong style="color:var(--ink);"><?php echo esc_html($row['days']); ?>:</strong><br>
            <?php echo esc_html($row['hours']); ?>
          </p>
        <?php endforeach; ?>
      </div>
      <p style="color:#d4a359;font-size:12px;margin:0;font-weight:600;">
        🍣 <?php echo esc_html($wow_hd['sushi_note'] ?? 'Montags Sushi-Ruhetag (warme Küche regulär)'); ?>
      </p>
    </div>

    <div>
      <h4 style="font-size:13px;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:1px;margin:0 0 12px 0;">Direkter Kontakt</h4>
      <p style="color:var(--muted);margin:0 0 8px 0;">
        Telefon: <a href="tel:<?php echo esc_attr($wow_tel); ?>" style="color:var(--ink);font-weight:700;text-decoration:none;"><?php echo esc_html($wow_ph); ?></a><br>
        E-Mail: <a href="mailto:<?php echo esc_attr($wow_mail); ?>" style="color:var(--muted);text-decoration:none;"><?php echo esc_html($wow_mail); ?></a>
      </p>
      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?php echo esc_url(home_url('/menu/')); ?>" class="button button-quiet" style="padding:8px 14px;font-size:12px;">Speisekarte <?php echo wowthai_action_icon('action-icon--compact'); ?></a>
        <a href="<?php echo esc_url(home_url('/reservations/')); ?>" class="button button-quiet" style="padding:8px 14px;font-size:12px;">Tisch reservieren <?php echo wowthai_action_icon('action-icon--compact'); ?></a>
      </div>
    </div>
  </div>

  <div class="wrap footer-row" style="border-top:1px solid rgba(255,255,255,0.06);padding:20px 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--muted);">
    <span>© <span data-year><?php echo esc_html(wp_date('Y')); ?></span> WOW Thai Steyr · Alle Rechte vorbehalten.</span>
    <span>
      <a href="<?php echo esc_url(home_url('/impressum/')); ?>" style="color:var(--muted);text-decoration:none;">Impressum</a> · 
      <a href="<?php echo esc_url(home_url('/datenschutz/')); ?>" style="color:var(--muted);text-decoration:none;">Datenschutz (DSGVO)</a>
    </span>
  </div>
</footer>

<div id="sheet-root"></div>
<div id="cart-root"></div>
<div id="toast-root" class="toast-root" aria-live="polite"></div>

<?php wp_footer(); ?>
</body>
</html>
