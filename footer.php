<footer class="site-footer">
  <div class="wrap footer-row">
    <span>WOW THAI · STEYR</span>
    <span>
      <a href="<?php echo esc_url(home_url('/menu/')); ?>">Online bestellen</a> · 
      <a href="<?php echo esc_url(home_url('/reservations/')); ?>">Tisch reservieren</a> · 
      <a href="tel:+43725274973">+43 7252 74973</a> · 
      <a href="mailto:info@wow-thai-restaurant.at">E-Mail</a> · 
      © <span data-year><?php echo date('Y'); ?></span>
    </span>
  </div>
</footer>

<div id="sheet-root"></div>
<div id="cart-root"></div>
<div id="toast-root" class="toast-root" aria-live="polite"></div>

<?php wp_footer(); ?>
</body>
</html>
