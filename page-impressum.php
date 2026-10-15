<?php
/**
 * Template Name: Impressum
 */
get_header();
$info = wowthai_get_restaurant_data();
?>

<main class="legal-page">
  <section class="page-intro wrap">
    <p class="eyebrow"><span class="eyebrow-dot"></span> RECHTLICHE INFORMATIONEN</p>
    <div class="page-intro-row">
      <div>
        <h1>Impressum</h1>
        <p>Informationspflicht laut §5 E-Commerce Gesetz (ECG), §14 Unternehmensgesetzbuch (UGB), §63 Gewerbeordnung und Offenlegungspflicht laut §25 Mediengesetz.</p>
      </div>
    </div>
  </section>

  <section class="wrap legal-content">
    <div class="legal-card">
      <h2>Angaben zum Medieninhaber & Dienstanbieter</h2>
      <p>
        <strong><?php echo esc_html($info['name']); ?></strong><br>
        <?php echo esc_html($info['street']); ?><br>
        <?php echo esc_html($info['postal_code'] . ' ' . $info['city']); ?><br>
        <?php echo esc_html($info['country_name']); ?>
      </p>

      <h3>Kontakt</h3>
      <p>
        <strong>Telefon:</strong> <a href="tel:<?php echo esc_attr($info['phone_clean']); ?>" style="color:#d4a359;"><?php echo esc_html($info['phone']); ?></a><br>
        <strong>E-Mail:</strong> <a href="mailto:<?php echo esc_attr($info['email']); ?>" style="color:#d4a359;"><?php echo esc_html($info['email']); ?></a><br>
        <strong>Website:</strong> <a href="<?php echo esc_url(home_url('/')); ?>" style="color:#d4a359;">wow-thai-steyr.at</a>
      </p>

      <h3>Unternehmensgegenstand</h3>
      <p>Gastronomiebetrieb (Restaurant, Zubereitung thailändischer Speisen, Sushi & Takeaway)</p>

      <h3>Kammerzugehörigkeit & Berufsrecht</h3>
      <p>
        <strong>Mitglied bei:</strong> Wirtschaftskammer Oberösterreich (WKO)<br>
        <strong>Fachgruppe:</strong> Gastronomie & Restaurantbetriebe<br>
        <strong>Gewerbebehörde:</strong> Magistrat der Stadt Steyr / Bezirkshauptmannschaft Steyr<br>
        <strong>Anwendbare Rechtsvorschriften:</strong> Österreichische Gewerbeordnung (GewO 1994), abrufbar unter <a href="https://www.ris.bka.gv.at" target="_blank" rel="noopener" style="color:#d4a359;">www.ris.bka.gv.at</a>
      </p>

      <h3>EU-Streitschlichtung & Verbraucherstreitbeilegung</h3>
      <p>
        Verbraucher haben die Möglichkeit, Beschwerden an die Online-Streitbeilegungsplattform der EU zu richten: 
        <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener" style="color:#d4a359;">https://ec.europa.eu/consumers/odr</a>.<br>
        Sie können etwaige Beschwerden auch direkt an die oben angegebene E-Mail-Adresse richten. Wir sind nicht verpflichtet oder bereit, an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.
      </p>

      <h3>Haftung für Inhalte dieser Website</h3>
      <p>
        Wir entwickeln die Inhalte dieser Webseite ständig weiter und bemühen uns, korrekte und aktuelle Informationen bereitzustellen. Leider können wir keine Haftung für die Korrektheit aller Inhalte auf dieser Webseite übernehmen, speziell für jene, die seitens Dritter bereitgestellt wurden. Sollten Ihnen problematische oder rechtswidrige Inhalte auffallen, bitten wir Sie, uns umgehend zu kontaktieren.
      </p>

      <h3>Urheberrechtshinweis</h3>
      <p>
        Alle Inhalte dieser Webseite (Bilder, Fotos, Texte, Videos) unterliegen dem Urheberrecht. Falls notwendig, werden wir die unerlaubte Nutzung von Teilen der Inhalte unserer Seite rechtlich verfolgen.
      </p>
    </div>
  </section>
</main>

<?php get_footer(); ?>
