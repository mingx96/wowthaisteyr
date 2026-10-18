<?php
/**
 * Template Name: Datenschutzerklärung
 */
get_header();
$info = wowthai_get_restaurant_data();
?>

<main id="main" class="legal-page" tabindex="-1">
  <section class="page-intro wrap">
    <p class="eyebrow"><span class="eyebrow-dot"></span> DATENSCHUTZ &amp; DSGVO</p>
    <div class="page-intro-row">
      <div>
        <h1>Datenschutzerklärung</h1>
        <p>Informationen zur Verarbeitung Ihrer personenbezogenen Daten gemäß Art. 13 &amp; 14 DSGVO bei <?php echo esc_html($info['name']); ?>.</p>
      </div>
    </div>
  </section>

  <section class="wrap legal-content">
    <div class="legal-card">
      <h2>1. Verantwortlicher für die Datenverarbeitung</h2>
      <p>
        <strong><?php echo esc_html($info['legal_name'] ?? $info['name']); ?></strong><br>
        <?php echo esc_html($info['street']); ?><br>
        <?php echo esc_html($info['postal_code'] . ' ' . $info['city']); ?><br>
        Telefon: <a href="tel:<?php echo esc_attr($info['phone_clean']); ?>" style="color:#d4a359;"><?php echo esc_html($info['phone']); ?></a><br>
        E-Mail: <a href="mailto:<?php echo esc_attr($info['email']); ?>" style="color:#d4a359;"><?php echo esc_html($info['email']); ?></a>
      </p>

      <h2>2. Online-Bestellungen &amp; Abholservice (Takeaway)</h2>
      <p>
        Wenn Sie über unsere Website Speisen zur Abholung bestellen, erfassen wir die von Ihnen eingegebenen Daten (Vor- und Nachname, Telefonnummer, E-Mail-Adresse sowie gewünschte Abholzeit und Anmerkungen).
      </p>
      <p>
        <strong>Zweck:</strong> Abwicklung Ihrer Bestellung, Bereitstellung der Speisen zum gewünschten Zeitpunkt und eventuelle telefonische Rückfragen zur Bestellung.<br>
        <strong>Rechtsgrundlage:</strong> Vertragserfüllung bzw. Durchführung vorvertraglicher Maßnahmen gemäß Art. 6 Abs. 1 lit. b DSGVO.<br>
        <strong>Speicherdauer:</strong> Die Bestelldaten werden im Rahmen der gesetzlichen steuer- und unternehmensrechtlichen Aufbewahrungsfristen (BAO / UGB in Österreich in der Regel 7 Jahre) aufbewahrt.
      </p>

      <h2>3. Tischreservierungen</h2>
      <p>
        Bei einer Online-Tischreservierung verarbeiten wir Ihren Namen, Ihre Telefonnummer, optional Ihre E-Mail-Adresse, das gewünschte Reservierungsdatum, die Uhrzeit, die Personenanzahl und freiwillige Anmerkungen. Zur Missbrauchs- und Spam-Abwehr wird kurzzeitig ein anonymisierter, nicht rückrechenbarer Prüf-Hash (Rate-Limiting für 10 Minuten) verwendet.
      </p>
      <p>
        <strong>Zweck:</strong> Reservierung eines Tisches im Restaurant, Bestätigung per E-Mail und Kontaktaufnahme bei Rückfragen oder Änderungen.<br>
        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vorvertragliche Maßnahmen bzw. Reservierungsvereinbarung) sowie Art. 6 Abs. 1 lit. a DSGVO (Einwilligung).<br>
        <strong>Speicherdauer:</strong> Reservierungsanfragen werden gemäß dem Grundsatz der Speicherbegrenzung (Art. 5 Abs. 1 lit. e DSGVO) automatisch 90 Tage nach dem Reservierungstermin gelöscht, sofern keine gesetzlichen Aufbewahrungspflichten entgegenstehen.
      </p>

      <h2>4. Server-Log-Dateien &amp; Lokal eingebundene Schriftarten (Webfonts)</h2>
      <p>
        Der Provider dieser Website erhebt und speichert automatisch Informationen in sogenannten Server-Log-Dateien, die Ihr Browser automatisch übermittelt (IP-Adresse, Browsertyp, Betriebssystem, Referrer URL, Uhrzeit der Serveranfrage). Dies dient der technischen Stabilität und Sicherheit der Systeme (Art. 6 Abs. 1 lit. f DSGVO).<br>
        <strong>Lokale Schriftarten:</strong> Auf dieser Website werden alle Schriftarten (Webfonts) ausschließlich lokal von unserem eigenen Webserver geladen. Es findet beim Seitenaufruf keine Verbindung zu Servern von Google Fonts (USA) statt.
      </p>

      <h2>5. Cookies &amp; Lokale Speicherung</h2>
      <p>
        Unsere Website verwendet technisch notwendige Cookies bzw. den Local Storage Ihres Browsers, um den Warenkorb während Ihres Besuchs zu speichern und eine reibungslose Bestellung zu gewährleisten (§ 165 Abs. 3 TKG 2021 / Art. 6 Abs. 1 lit. f DSGVO). Es werden keine zustimmungspflichtigen Werbe- oder Tracking-Cookies von Drittanbietern eingesetzt.
      </p>

      <h2>6. Ihre Rechte gemäß DSGVO</h2>
      <p>
        Ihnen stehen laut den Bestimmungen der DSGVO grundsätzlich folgende Rechte zu:
      </p>
      <ul style="margin-left: 20px; line-height: 1.8;">
        <li>Recht auf Auskunft (Art. 15 DSGVO)</li>
        <li>Recht auf Berichtigung (Art. 16 DSGVO)</li>
        <li>Recht auf Löschung (Art. 17 DSGVO)</li>
        <li>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
        <li>Recht auf Datenübertragbarkeit (Art. 20 DSGVO)</li>
        <li>Widerspruchsrecht (Art. 21 DSGVO)</li>
      </ul>
      <p>
        Wenn Sie der Ansicht sind, dass die Verarbeitung Ihrer Daten gegen das Datenschutzrecht verstößt, können Sie sich an die österreichische Datenschutzbehörde wenden:<br>
        <strong>Österreichische Datenschutzbehörde</strong><br>
        Barichgasse 40–42, 1030 Wien · Web: <a href="https://www.dsb.gv.at" target="_blank" rel="noopener" style="color:#d4a359;">www.dsb.gv.at</a>
      </p>
    </div>
  </section>
</main>

<?php get_footer(); ?>
