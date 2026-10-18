/**
 * Tier 2: Boundary & Corner Cases E2E Test Suite
 * 
 * Verifies edge conditions and boundary precision across:
 * 1. PLZ Boundaries (4299, 4301, 4302, 4304, 4469, 4471, 4480, 4482, non-numeric, 5-digit)
 * 2. Subtotal Boundaries (€19.99/€20.00, €24.99/€25.00, €29.99/€30.00, €44.99/€45.00)
 * 3. Zero & Negative Inputs (€0.00, -€10.00, null, NaN, extreme €1500.00, precision)
 * 4. German Umlauts & Special Characters (ä, ö, ü, ß, Ä, Ö, Ü, € on receipts & files)
 * 5. Empty & Malformed Addresses (empty street, whitespace, empty phone, fallback)
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, test, expect } from './lib/test-harness.mjs';
import * as Oracle from './lib/oracle.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const THEME_DIR = path.resolve(__dirname, '../..');

export function registerTier2Tests() {
    describe('Tier 2.1: Postal Code (PLZ) Boundaries & Corner Cases', () => {
        test('T2.1.1: Boundary PLZ 4299 (immediately below 4300) is rejected as Out of Zone', () => {
            const res = Oracle.validateDeliveryPlz('4299');
            expect(res.valid).toBe(false);
            expect(res.zone).toBeNull();
        });

        test('T2.1.2: Boundary PLZ 4301 (immediately above 4300) is rejected as Out of Zone', () => {
            const res = Oracle.validateDeliveryPlz('4301');
            expect(res.valid).toBe(false);
            expect(res.zone).toBeNull();
        });

        test('T2.1.3: Intermediate PLZ 4302 (between 4300 and 4303) is rejected as Out of Zone', () => {
            const res = Oracle.validateDeliveryPlz('4302');
            expect(res.valid).toBe(false);
            expect(res.zone).toBeNull();
        });

        test('T2.1.4: Boundary PLZ 4304 (immediately above 4303) is rejected as Out of Zone', () => {
            const res = Oracle.validateDeliveryPlz('4304');
            expect(res.valid).toBe(false);
            expect(res.zone).toBeNull();
        });

        test('T2.1.5: Boundary PLZs 4469 & 4471 (around 4470 Enns) are rejected as Out of Zone', () => {
            expect(Oracle.validateDeliveryPlz('4469').valid).toBe(false);
            expect(Oracle.validateDeliveryPlz('4471').valid).toBe(false);
        });

        test('T2.1.6: Boundary PLZs 4480 & 4482 (around 4481 Asten) are rejected as Out of Zone', () => {
            expect(Oracle.validateDeliveryPlz('4480').valid).toBe(false);
            expect(Oracle.validateDeliveryPlz('4482').valid).toBe(false);
        });

        test('T2.1.7: Non-numeric and typo inputs ("ABCD", "430O") are rejected with format notice', () => {
            const resAlpha = Oracle.validateDeliveryPlz('ABCD');
            expect(resAlpha.valid).toBe(false);
            expect(resAlpha.error_message).toContain('gültige 4-stellige');
            const resTypo = Oracle.validateDeliveryPlz('430O');
            expect(resTypo.valid).toBe(false);
        });

        test('T2.1.8: 5-digit German postal code ("80331 München") is rejected as non-Austrian PLZ', () => {
            const res = Oracle.validateDeliveryPlz('80331');
            expect(res.valid).toBe(false);
        });
    });

    describe('Tier 2.2: Subtotal Exact 1-Cent Boundaries', () => {
        test('T2.2.1: Core Zone €19.99 (1 cent below €20 min) is blocked with exact €0.01 deficit', () => {
            const res = Oracle.calculateDeliveryFee('4300', 19.99);
            expect(res.allowed).toBe(false);
            expect(res.deficit).toBe(0.01);
            expect(res.error_message).toContain('0,01 €');
        });

        test('T2.2.2: Core Zone €20.00 (exact minimum order) is permitted with €2.50 fee', () => {
            const res = Oracle.calculateDeliveryFee('4300', 20.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(2.50);
            expect(res.total).toBe(22.50);
        });

        test('T2.2.3: Core Zone €29.99 (1 cent below €30 free) applies €2.50 fee with €0.01 progress notice', () => {
            const res = Oracle.calculateDeliveryFee('4300', 29.99);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(2.50);
            expect(res.free_threshold_met).toBe(false);
            expect(res.remaining_to_free).toBe(0.01);
            expect(res.notice).toContain('0,01 €');
        });

        test('T2.2.4: Core Zone €30.00 (exact free delivery threshold) waives fee completely', () => {
            const res = Oracle.calculateDeliveryFee('4300', 30.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(0.00);
            expect(res.free_threshold_met).toBe(true);
            expect(res.total).toBe(30.00);
        });

        test('T2.2.5: Extended Zone €24.99 (1 cent below €25 min) is blocked with exact €0.01 deficit', () => {
            const res = Oracle.calculateDeliveryFee('4470', 24.99);
            expect(res.allowed).toBe(false);
            expect(res.deficit).toBe(0.01);
            expect(res.error_message).toContain('0,01 €');
        });

        test('T2.2.6: Extended Zone €25.00 (exact minimum order) is permitted with €3.90 fee', () => {
            const res = Oracle.calculateDeliveryFee('4470', 25.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(3.90);
            expect(res.total).toBe(28.90);
        });

        test('T2.2.7: Extended Zone €44.99 (1 cent below €45 free) applies €3.90 fee with €0.01 progress notice', () => {
            const res = Oracle.calculateDeliveryFee('4470', 44.99);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(3.90);
            expect(res.free_threshold_met).toBe(false);
            expect(res.remaining_to_free).toBe(0.01);
            expect(res.notice).toContain('0,01 €');
        });

        test('T2.2.8: Extended Zone €45.00 (exact free delivery threshold) waives fee completely', () => {
            const res = Oracle.calculateDeliveryFee('4470', 45.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(0.00);
            expect(res.free_threshold_met).toBe(true);
            expect(res.total).toBe(45.00);
        });
    });

    describe('Tier 2.3: Zero, Negative & Extreme Numerical Inputs', () => {
        test('T2.3.1: Subtotal €0.00 is blocked with deficit equal to full minimum order', () => {
            const resCore = Oracle.calculateDeliveryFee('4300', 0.00);
            expect(resCore.allowed).toBe(false);
            expect(resCore.deficit).toBe(20.00);
            const resExt = Oracle.calculateDeliveryFee('4303', 0.00);
            expect(resExt.allowed).toBe(false);
            expect(resExt.deficit).toBe(25.00);
        });

        test('T2.3.2: Negative subtotal -€10.00 is blocked safely', () => {
            const res = Oracle.calculateDeliveryFee('4300', -10.00);
            expect(res.allowed).toBe(false);
            expect(res.deficit).toBe(30.00);
        });

        test('T2.3.3: Null or undefined subtotal defaults to 0 and blocks checkout', () => {
            const resNull = Oracle.calculateDeliveryFee('4300', null);
            expect(resNull.allowed).toBe(false);
            expect(resNull.subtotal).toBe(0.00);
            const resUndef = Oracle.calculateDeliveryFee('4300', undefined);
            expect(resUndef.allowed).toBe(false);
            expect(resUndef.subtotal).toBe(0.00);
        });

        test('T2.3.4: Extreme order size €1,500.00 waives fee and calculates total correctly', () => {
            const res = Oracle.calculateDeliveryFee('4300', 1500.00);
            expect(res.allowed).toBe(true);
            expect(res.fee).toBe(0.00);
            expect(res.total).toBe(1500.00);
            expect(res.free_threshold_met).toBe(true);
        });

        test('T2.3.5: Float precision 19.9999 rounds to 20.00 and permits order', () => {
            const res = Oracle.calculateDeliveryFee('4300', 19.9999);
            expect(res.allowed).toBe(true);
            expect(res.subtotal).toBe(20.00);
        });
    });

    describe('Tier 2.4: German Umlauts & Special Characters (ä, ö, ü, ß, Ä, Ö, Ü, €)', () => {
        const umlautOrder = {
            id: 10999,
            order_number: '10999',
            created_at_vienna: '20.10.2026 · 12:30 Uhr',
            customer_name: 'Herr Jürgen Schöfferl-Grüßner',
            customer_address: {
                street: 'Franz-Lehár-Straße 8/Top 4',
                details: 'Hinterer Hauseingang bei Stiege 2',
                plz: '4300',
                city: 'St. Valentin',
                phone: '+43 676 9876543'
            },
            zone_label: 'ZONE 1 (Core · St. Valentin)',
            driver_note: '⚠️ Bitte nicht läuten! Baby schläft (großes Dankeschön & € 2 Trinkgeld liegt bereit)!',
            payment_status: 'paid',
            payment_method: 'stripe',
            payment_method_title: 'Stripe Online (Bezahlt)',
            subtotal: 35.00,
            delivery_fee: 0.00,
            total: 35.00,
            items: [
                { quantity: 2, name: 'Frühlingsrollen mit süß-saurer Sauce', modifiers: ['Sehr knusprig', 'Schärfe: Mäßig'] },
                { quantity: 1, name: 'Grünes Curry mit Rindfleisch', modifiers: ['Auberginen', 'Basilikum'] }
            ]
        };

        test('T2.4.1: Customer name with all umlauts & ß renders cleanly on driver receipt', () => {
            const bon = Oracle.renderDriverReceipt(umlautOrder);
            expect(bon).toContain('Herr Jürgen Schöfferl-Grüßner');
        });

        test('T2.4.2: Street address with accent and ß renders cleanly on driver receipt', () => {
            const bon = Oracle.renderDriverReceipt(umlautOrder);
            expect(bon).toContain('Franz-Lehár-Straße 8/Top 4');
        });

        test('T2.4.3: Order note with umlauts, ß, and Euro symbol (€) renders cleanly', () => {
            const bon = Oracle.renderDriverReceipt(umlautOrder);
            expect(bon).toContain('großes Dankeschön & € 2 Trinkgeld liegt bereit');
        });

        test('T2.4.4: Dish items with umlauts render cleanly in packing checklist', () => {
            const bon = Oracle.renderDriverReceipt(umlautOrder);
            expect(bon).toContain('Frühlingsrollen mit süß-saurer Sauce');
            expect(bon).toContain('Grünes Curry mit Rindfleisch');
            expect(bon).toContain('Schärfe: Mäßig');
        });

        test('T2.4.5: Theme codebase files contain zero UTF-8 Mojibake sequences', () => {
            const mojibakeRegex = /Ã[\x80-\xBF]|Â[\x80-\xBF]|ðŸ|â€[\x80-\xBF]/;
            const checkFiles = [
                'functions.php',
                'header.php',
                'footer.php',
                'page-betrieb.php',
                'inc/hours.php',
                'inc/restaurant-info.php',
                'inc/admin-orders.php'
            ];
            for (const rel of checkFiles) {
                const fullPath = path.join(THEME_DIR, rel);
                if (fs.existsSync(fullPath)) {
                    const text = fs.readFileSync(fullPath, 'utf8');
                    expect(mojibakeRegex.test(text)).toBe(false);
                }
            }
        });

        test('T2.4.6: Theme codebase files contain zero UTF-8 Byte Order Marks (BOM)', () => {
            const checkFiles = [
                'functions.php',
                'style.css',
                'page-betrieb.php',
                'inc/hours.php',
                'inc/restaurant-info.php'
            ];
            for (const rel of checkFiles) {
                const fullPath = path.join(THEME_DIR, rel);
                if (fs.existsSync(fullPath)) {
                    const buf = fs.readFileSync(fullPath);
                    const hasBom = buf.length >= 3 && buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf;
                    expect(hasBom).toBe(false);
                }
            }
        });
    });

    describe('Tier 2.5: Empty & Malformed Address Fields', () => {
        test('T2.5.1: Empty street address in order results in empty string without crashing', () => {
            const emptyStreetOrder = {
                id: 11001,
                customer_name: 'Testkunde',
                customer_address: { street: '', plz: '4300', city: 'St. Valentin', phone: '+43 676 1111111' },
                items: [],
                subtotal: 25.00,
                delivery_fee: 2.50,
                total: 27.50
            };
            const bon = Oracle.renderDriverReceipt(emptyStreetOrder);
            expect(bon).toContain('Strasse:   ');
            expect(bon.includes('undefined')).toBe(false);
            expect(bon.includes('null')).toBe(false);
        });

        test('T2.5.2: Missing apartment/floor details omits Details line cleanly', () => {
            const noDetailsOrder = {
                id: 11002,
                customer_name: 'Testkunde',
                customer_address: { street: 'Kirchenweg 3', plz: '4300', city: 'St. Valentin', phone: '+43 676 1111111' },
                items: [],
                subtotal: 25.00,
                delivery_fee: 2.50,
                total: 27.50
            };
            const bon = Oracle.renderDriverReceipt(noDetailsOrder);
            expect(bon.includes('Details:')).toBe(false);
        });

        test('T2.5.3: Missing phone number prints empty without "undefined"', () => {
            const noPhoneOrder = {
                id: 11003,
                customer_name: 'Testkunde',
                customer_address: { street: 'Kirchenweg 3', plz: '4300', city: 'St. Valentin' },
                items: [],
                subtotal: 25.00,
                delivery_fee: 2.50,
                total: 27.50
            };
            const bon = Oracle.renderDriverReceipt(noPhoneOrder);
            expect(bon).toContain('TELEFON:   ');
            expect(bon.includes('undefined')).toBe(false);
        });

        test('T2.5.4: Missing driver note defaults to friendly fallback', () => {
            const noNoteOrder = {
                id: 11004,
                customer_name: 'Testkunde',
                customer_address: { street: 'Kirchenweg 3', plz: '4300', city: 'St. Valentin', phone: '+43 676 111' },
                driver_note: '',
                items: [],
                subtotal: 25.00,
                delivery_fee: 2.50,
                total: 27.50
            };
            const bon = Oracle.renderDriverReceipt(noNoteOrder);
            expect(bon).toContain('Keine besonderen Hinweise.');
        });

        test('T2.5.5: Empty items list handles 0 articles gracefully without crash', () => {
            const emptyItemsOrder = {
                id: 11005,
                customer_name: 'Testkunde',
                customer_address: { street: 'Kirchenweg 3', plz: '4300', city: 'St. Valentin', phone: '+43 676 111' },
                items: [],
                subtotal: 0.00,
                delivery_fee: 2.50,
                total: 2.50
            };
            const bon = Oracle.renderDriverReceipt(emptyItemsOrder);
            expect(bon).toContain('KONTROLL-PACKLISTE (0 Artikel):');
        });
    });
}
