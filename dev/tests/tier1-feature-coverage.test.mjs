/**
 * Tier 1: Feature Coverage E2E Test Suite
 * 
 * Verifies the primary behavior (happy paths and functional contracts) across:
 * 1. PLZ Validation Engine (Core, Extended, Out of zone, Normalization)
 * 2. Branch Identity Engine (Steyr, Valentin, Saalbach, Express)
 * 3. Delivery Fee & Minimum Order Thresholds (Core & Extended)
 * 4. Dual Thermal Bon Generation (Kitchen Bon & 80mm Driver Monospace Receipt)
 * 5. Operations Dashboard (/betrieb/) Filtering & Concurrency
 * 6. Local Asset DSGVO Compliance (Zero External Fonts/CDNs, Local WOFF2)
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, test, expect } from './lib/test-harness.mjs';
import * as Oracle from './lib/oracle.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const THEME_DIR = path.resolve(__dirname, '../..');

export function registerTier1Tests() {
    describe('Tier 1.1: Postal Code (PLZ) Validation Engine', () => {
        test('T1.1.1: Core Zone 4300 (St. Valentin) validates successfully', () => {
            const res = Oracle.validateDeliveryPlz('4300');
            expect(res.valid).toBe(true);
            expect(res.zone).toBe('core');
            expect(res.min_order).toBe(20.00);
            expect(res.delivery_fee).toBe(2.50);
            expect(res.free_threshold).toBe(30.00);
            expect(res.error_message).toBeNull();
        });

        test('T1.1.2: Extended Zone 4303 (Ennsdorf) validates successfully', () => {
            const res = Oracle.validateDeliveryPlz('4303');
            expect(res.valid).toBe(true);
            expect(res.zone).toBe('extended');
            expect(res.min_order).toBe(25.00);
            expect(res.delivery_fee).toBe(3.90);
            expect(res.free_threshold).toBe(45.00);
        });

        test('T1.1.3: Extended Zone 4470 (Enns) validates successfully', () => {
            const res = Oracle.validateDeliveryPlz('4470');
            expect(res.valid).toBe(true);
            expect(res.zone).toBe('extended');
            expect(res.min_order).toBe(25.00);
            expect(res.delivery_fee).toBe(3.90);
            expect(res.free_threshold).toBe(45.00);
        });

        test('T1.1.4: Extended Zone 4481 (Asten) validates successfully', () => {
            const res = Oracle.validateDeliveryPlz('4481');
            expect(res.valid).toBe(true);
            expect(res.zone).toBe('extended');
            expect(res.min_order).toBe(25.00);
            expect(res.delivery_fee).toBe(3.90);
            expect(res.free_threshold).toBe(45.00);
        });

        test('T1.1.5: Out of Zone 4400 (Steyr) is rejected with German advisory', () => {
            const res = Oracle.validateDeliveryPlz('4400');
            expect(res.valid).toBe(false);
            expect(res.zone).toBeNull();
            expect(res.error_message).toContain('Leider beliefern wir die Postleitzahl 4400 derzeit nicht');
            expect(res.error_message).toContain('Selbstabholung');
        });

        test('T1.1.6: Padded postal code input "  A-4300  " normalizes cleanly', () => {
            const normalized = Oracle.normalizePlz('  A-4300  ');
            expect(normalized).toBe('4300');
            const res = Oracle.validateDeliveryPlz('  A-4300  ');
            expect(res.valid).toBe(true);
            expect(res.zone).toBe('core');
        });
    });

    describe('Tier 1.2: Branch Identity & Multi-Tenant Engine', () => {
        test('T1.2.1: Branch "steyr" returns correct identity and capabilities', () => {
            const cfg = Oracle.BRANCH_CONFIGS.steyr;
            expect(cfg.id).toBe('steyr');
            expect(cfg.name).toBe('WoW Thai Steyr');
            expect(cfg.city).toBe('Steyr');
            expect(cfg.address).toBe('Enge Gasse 19');
            expect(cfg.has_takeaway).toBe(true);
            expect(cfg.has_dinein).toBe(true);
            expect(cfg.has_delivery).toBe(false);
        });

        test('T1.2.2: Branch "valentin" returns correct identity and capabilities', () => {
            const cfg = Oracle.BRANCH_CONFIGS.valentin;
            expect(cfg.id).toBe('valentin');
            expect(cfg.name).toBe('WoW Thai St. Valentin');
            expect(cfg.city).toBe('St. Valentin');
            expect(cfg.address).toBe('Hauptplatz 1');
            expect(cfg.has_takeaway).toBe(true);
            expect(cfg.has_dinein).toBe(true);
            expect(cfg.has_delivery).toBe(false);
        });

        test('T1.2.3: Branch "saalbach" returns correct identity and capabilities', () => {
            const cfg = Oracle.BRANCH_CONFIGS.saalbach;
            expect(cfg.id).toBe('saalbach');
            expect(cfg.name).toBe('WoW Thai Saalbach');
            expect(cfg.city).toBe('Saalbach-Hinterglemm');
            expect(cfg.has_takeaway).toBe(true);
            expect(cfg.has_dinein).toBe(true);
            expect(cfg.has_delivery).toBe(false);
        });

        test('T1.2.4: Branch "express" returns dedicated delivery hub configuration', () => {
            const cfg = Oracle.BRANCH_CONFIGS.express;
            expect(cfg.id).toBe('express');
            expect(cfg.name).toBe('WoW Express St. Valentin');
            expect(cfg.has_takeaway).toBe(true);
            expect(cfg.has_dinein).toBe(false);
            expect(cfg.has_delivery).toBe(true);
            expect(cfg.delivery_zones).toContain('4300');
            expect(cfg.delivery_zones).toContain('4303');
            expect(cfg.delivery_zones).toContain('4470');
            expect(cfg.delivery_zones).toContain('4481');
        });

        test('T1.2.5: Branch identity phone numbers are distinct and localized', () => {
            expect(Oracle.BRANCH_CONFIGS.steyr.phone).toContain('7252');
            expect(Oracle.BRANCH_CONFIGS.valentin.phone).toContain('7435');
            expect(Oracle.BRANCH_CONFIGS.saalbach.phone).toContain('6541');
        });
    });

    describe('Tier 1.3: Delivery Fee & Minimum Order Thresholds', () => {
        test('T1.3.1: Core Zone subtotal €15.00 blocks checkout under €20.00 min', () => {
            const res = Oracle.calculateDeliveryFee('4300', 15.00);
            expect(res.allowed).toBe(false);
            expect(res.deficit).toBe(5.00);
            expect(res.error_message).toContain('Mindestbestellwert für Lieferungen nach 4300 St. Valentin beträgt 20,00 €');
            expect(res.error_message).toContain('5,00 €');
        });

        test('T1.3.2: Core Zone subtotal €20.00 permits checkout with €2.50 fee', () => {
            const res = Oracle.calculateDeliveryFee('4300', 20.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(2.50);
            expect(res.total).toBe(22.50);
            expect(res.free_threshold_met).toBe(false);
            expect(res.remaining_to_free).toBe(10.00);
        });

        test('T1.3.3: Core Zone subtotal €30.00 waives delivery fee (Free Delivery)', () => {
            const res = Oracle.calculateDeliveryFee('4300', 30.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(0.00);
            expect(res.total).toBe(30.00);
            expect(res.free_threshold_met).toBe(true);
            expect(res.remaining_to_free).toBe(0.00);
        });

        test('T1.3.4: Extended Zone subtotal €22.00 blocks checkout under €25.00 min', () => {
            const res = Oracle.calculateDeliveryFee('4470', 22.00);
            expect(res.allowed).toBe(false);
            expect(res.deficit).toBe(3.00);
            expect(res.error_message).toContain('Mindestbestellwert');
            expect(res.error_message).toContain('25,00 €');
        });

        test('T1.3.5: Extended Zone subtotal €25.00 permits checkout with €3.90 fee', () => {
            const res = Oracle.calculateDeliveryFee('4470', 25.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(3.90);
            expect(res.total).toBe(28.90);
            expect(res.free_threshold_met).toBe(false);
            expect(res.remaining_to_free).toBe(20.00);
        });

        test('T1.3.6: Extended Zone subtotal €45.00 waives delivery fee', () => {
            const res = Oracle.calculateDeliveryFee('4470', 45.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(0.00);
            expect(res.total).toBe(45.00);
            expect(res.free_threshold_met).toBe(true);
        });

        test('T1.3.7: Takeaway fulfillment waives all delivery fees and minimums', () => {
            const res = Oracle.calculateDeliveryFee('4300', 8.50, 'abholung');
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(0.00);
            expect(res.total).toBe(8.50);
        });
    });

    describe('Tier 1.4: Dual Thermal Bon Generation Engine', () => {
        const sampleOrder = {
            id: 10452,
            order_number: '10452',
            created_at_vienna: '17.10.2026 · 18:25 Uhr',
            target_time: 'ca. 18:55–19:10 Uhr (ASAP)',
            customer_name: 'Herr Markus Weber',
            customer_address: {
                street: 'Franz-Lehar-Gasse 8',
                details: 'Stiege 1, Top 4 (2. Stock)',
                plz: '4300',
                city: 'St. Valentin',
                phone: '+43 676 9876543'
            },
            zone_label: 'ZONE 1 (Core · St. Valentin)',
            driver_note: '⚠️ Bei "Weber / Top 4" läuten. Hauseingang im Innenhof!',
            payment_status: 'paid',
            payment_method: 'stripe',
            payment_method_title: 'Stripe Kreditkarte (Paid)',
            subtotal: 32.00,
            delivery_fee: 2.50,
            total: 34.50,
            items: [
                { quantity: 2, name: '★ M1 - Gebratene Nudeln (Huhn)', modifiers: ['Knuspriges Huhn', 'Scharf'] },
                { quantity: 1, name: '12. Tom Kha Gai (Suppe)', modifiers: [] },
                { quantity: 2, name: 'Singha Bier 0.33l (Kalt)', modifiers: [] }
            ]
        };

        test('T1.4.1: Driver receipt renders 42-column monospace separators', () => {
            const bon = Oracle.renderDriverReceipt(sampleOrder);
            expect(bon).toContain('==========================================');
            expect(bon).toContain('------------------------------------------');
            expect(bon).toContain('******************************************');
        });

        test('T1.4.2: Driver receipt renders complete customer delivery details', () => {
            const bon = Oracle.renderDriverReceipt(sampleOrder);
            expect(bon).toContain('Herr Markus Weber');
            expect(bon).toContain('Franz-Lehar-Gasse 8');
            expect(bon).toContain('Stiege 1, Top 4 (2. Stock)');
            expect(bon).toContain('4300 St. Valentin');
            expect(bon).toContain('+43 676 9876543');
        });

        test('T1.4.3: Driver receipt renders prominent online paid banner', () => {
            const bon = Oracle.renderDriverReceipt(sampleOrder);
            expect(bon).toContain('[ ✓ BEREITS ONLINE BEZAHLT ]');
            expect(bon).toContain('KEIN GELD BEIM KUNDEN KASSIEREN!');
        });

        test('T1.4.4: Driver receipt renders cash on delivery warning when unpaid', () => {
            const codOrder = { ...sampleOrder, payment_status: 'unpaid', payment_method: 'cod' };
            const bon = Oracle.renderDriverReceipt(codOrder);
            expect(bon).toContain('[ ⚠️ BARZAHLUNG BEI LIEFERUNG ]');
            expect(bon).toContain('BITTE BEIM KUNDEN KASSIEREN:');
            expect(bon).toContain('EXAKTER BETRAG:  34,50 €');
        });

        test('T1.4.5: Driver receipt renders packing checklist with check boxes', () => {
            const bon = Oracle.renderDriverReceipt(sampleOrder);
            expect(bon).toContain('KONTROLL-PACKLISTE (3 Artikel):');
            expect(bon).toContain('[ ] 2x ★ M1 - Gebratene Nudeln (Huhn)');
            expect(bon).toContain('+ Knuspriges Huhn, Scharf');
            expect(bon).toContain('[ ] 1x 12. Tom Kha Gai (Suppe)');
            expect(bon).toContain('[ ] 2x Singha Bier 0.33l (Kalt)');
        });

        test('T1.4.6: Driver receipt calculates and prints formatted totals', () => {
            const bon = Oracle.renderDriverReceipt(sampleOrder);
            expect(bon).toMatch(/Zwischensumme:\s+32,00 €/);
            expect(bon).toMatch(/Liefergebühr:\s+2,50 €/);
            expect(bon).toMatch(/GESAMTSUMME:\s+34,50 €/);
        });
    });

    describe('Tier 1.5: Operations Dashboard (/betrieb/) Filtering & Concurrency', () => {
        const mockOrders = [
            { id: 101, fulfillment_type: 'abholung', status: 'new', customer_name: 'Anna' },
            { id: 102, fulfillment_type: 'lieferung', status: 'preparing', customer_name: 'Bernd' },
            { id: 103, fulfillment_type: 'takeaway', status: 'ready', customer_name: 'Claudia' },
            { id: 104, fulfillment_type: 'delivery', status: 'dispatched', customer_name: 'David' }
        ];

        test('T1.5.1: Filter "all" returns all active orders without omission', () => {
            const res = Oracle.filterDashboardOrders(mockOrders, 'all');
            expect(res.length).toBe(4);
        });

        test('T1.5.2: Filter "pickup" isolates takeaway orders', () => {
            const res = Oracle.filterDashboardOrders(mockOrders, 'pickup');
            expect(res.length).toBe(2);
            expect(res.every(o => o.fulfillment_type === 'abholung' || o.fulfillment_type === 'takeaway')).toBe(true);
        });

        test('T1.5.3: Filter "delivery" isolates delivery orders', () => {
            const res = Oracle.filterDashboardOrders(mockOrders, 'delivery');
            expect(res.length).toBe(2);
            expect(res.every(o => o.fulfillment_type === 'lieferung' || o.fulfillment_type === 'delivery')).toBe(true);
        });

        test('T1.5.4: Concurrency control passes on matching version hash', () => {
            const hash = 'a6c8e312f45b';
            const allowed = Oracle.checkOrderConcurrency(hash, hash);
            expect(allowed).toBe(true);
        });

        test('T1.5.5: Concurrency control detects conflict on outdated version hash', () => {
            const currentHash = 'b7d9f423e56c';
            const staleHash = 'a6c8e312f45b';
            const allowed = Oracle.checkOrderConcurrency(currentHash, staleHash);
            expect(allowed).toBe(false);
        });
    });

    describe('Tier 1.6: Local Asset DSGVO Compliance Verification', () => {
        test('T1.6.1: Theme CSS contains zero Google Fonts or external CDN links', () => {
            const cssDir = path.join(THEME_DIR, 'assets/css');
            const files = fs.readdirSync(cssDir).filter(f => f.endsWith('.css'));
            for (const file of files) {
                const content = fs.readFileSync(path.join(cssDir, file), 'utf8');
                expect(content.includes('fonts.googleapis.com')).toBe(false);
                expect(content.includes('fonts.gstatic.com')).toBe(false);
                expect(content.includes('cdnjs.cloudflare.com')).toBe(false);
            }
        });

        test('T1.6.2: Theme PHP files contain zero external Google Fonts enqueues', () => {
            const functionsPhp = fs.readFileSync(path.join(THEME_DIR, 'functions.php'), 'utf8');
            expect(functionsPhp.includes('fonts.googleapis.com')).toBe(false);
            expect(functionsPhp.includes('fonts.gstatic.com')).toBe(false);
            const headerPhp = fs.readFileSync(path.join(THEME_DIR, 'header.php'), 'utf8');
            expect(headerPhp.includes('fonts.googleapis.com')).toBe(false);
        });

        test('T1.6.3: Local WOFF2 font files exist in assets/fonts/ and match fonts.css', () => {
            const fontDir = path.join(THEME_DIR, 'assets/fonts');
            expect(fs.existsSync(fontDir)).toBe(true);
            const fontFiles = fs.readdirSync(fontDir).filter(f => f.endsWith('.woff2'));
            expect(fontFiles.length >= 10).toBe(true);

            // Verify that all fonts referenced in fonts.css exist in assets/fonts
            const fontsCss = fs.readFileSync(path.join(THEME_DIR, 'assets/css/fonts.css'), 'utf8');
            const fontUrlMatches = [...fontsCss.matchAll(/url\(\.\.\/fonts\/([^)]+)\)/g)].map(m => m[1]);
            expect(fontUrlMatches.length > 0).toBe(true);
            for (const fontName of fontUrlMatches) {
                expect(fs.existsSync(path.join(fontDir, fontName))).toBe(true);
            }
        });

        test('T1.6.4: Local @font-face rules reference local relative font paths', () => {
            const fontsCss = path.join(THEME_DIR, 'assets/css/fonts.css');
            expect(fs.existsSync(fontsCss)).toBe(true);
            const content = fs.readFileSync(fontsCss, 'utf8');
            expect(content).toContain('@font-face');
            expect(content).toContain('../fonts/');
            expect(content.includes('http://')).toBe(false);
            expect(content.includes('https://')).toBe(false);
        });

        test('T1.6.5: Zero third-party tracker scripts in theme templates', () => {
            const headerPhp = fs.readFileSync(path.join(THEME_DIR, 'header.php'), 'utf8');
            const footerPhp = fs.readFileSync(path.join(THEME_DIR, 'footer.php'), 'utf8');
            const combined = headerPhp + footerPhp;
            expect(combined.includes('google-analytics.com')).toBe(false);
            expect(combined.includes('googletagmanager.com')).toBe(false);
            expect(combined.includes('connect.facebook.net')).toBe(false);
        });
    });
}
