# Restaurant menu reconciliation — 2026-10-15

Source: published Wix site `ad825369-3cc6-47b2-b249-e1656bcf1585` (`www.wow-thai-steyr.at`), Wix Restaurants Menus API. Target: WordPress.com site `wowthaisteyr.wpcomstaging.com`, published WooCommerce products.

- Wix has 116 item records. Of these, 94 are visible and linked to a visible section of the two public menus. One linked item is hidden; 21 records are not linked to those menus. The 94 public items all exist on WordPress, matched by `_wix_item_id`.
- Product names, descriptions, base prices, section assignments and dish order within each section match the Wix source for all 94 items. All 94 WordPress products are in stock.
- 26 items have price variants, with 100 individual choices. Choice IDs, names and prices match Wix for all 100. The menu card previously showed only the lowest price; clicking `Auswählen` exposed the variant prices.
- Three Wix modifier groups were absent from WordPress: required noodle type on **H5. Pad Thai** (rice noodles or glass noodles, no surcharge); optional extra fried egg on **★ S9. Pad Ka Prao** (+€2.60); optional shrimp on **V4. Grüner Salat mit Avocado** (+€3.00). These groups are now stored as `_wowthai_modifier_groups` on WordPress product IDs 56, 47 and 73.
- The former public WordPress menu rendered **Maki & Sashimi** twice. The theme now guards against duplicate section IDs and follows the public Wix section order.

Theme release 1.1.4 is based on the latest GitHub `main` theme (1.1.3), preserving its order and reservation administration. It displays the missing choices, validates required choices, recomputes every variant and surcharge from product metadata on the server, and includes selected choices in cart and order line items. At audit time, the live WordPress site rendered theme 1.1.2; it will not show the new controls until the release ZIP is installed.
