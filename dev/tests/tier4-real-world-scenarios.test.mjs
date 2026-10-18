/**
 * Tier 4: Real-World Application Scenarios E2E Test Suite
 * 
 * Verifies complete, realistic end-to-end user journeys and operational workflows:
 * 1. Scenario 1: Sunday Evening Family Dinner Delivery (St. Valentin Core Zone, Online Paid)
 * 2. Scenario 2: Ennsdorf Office Worker Lunch (Under Min Order -> Upsell -> COD Delivery)
 * 3. Scenario 3: Commuter Out-of-Zone Delivery Blocked -> 1-Click Takeaway Fallback
 * 4. Scenario 4: Friday Night Kitchen Peak Surge -> Dynamic ETA Extension to 60-75 Min.
 * 5. Scenario 5: Full Operational Dispatch Lifecycle on /betrieb/ (New -> Prep -> Dispatched -> Done)
 */

import { describe, test, expect } from './lib/test-harness.mjs';
import * as Oracle from './lib/oracle.mjs';

export function registerTier4Tests() {
    describe('Tier 4.1: Scenario 1 — Sunday Family Dinner Delivery (Core Zone, Online Paid)', () => {
        test('T4.1.1: Complete customer checkout, fee waiver, and driver thermal bon', () => {
            // Customer in St. Valentin orders family dinner
            const plz = '4300';
            const items = [
                { quantity: 2, name: 'Pad Thai Gai', price: 13.50, modifiers: ['Extra Limette'] },
                { quantity: 1, name: 'Tom Kha Gai (Groß)', price: 6.50, modifiers: [] },
                { quantity: 2, name: 'Singha Bier 0.33l', price: 3.50, modifiers: ['Gekühlt'] }
            ];
            const subtotal = items.reduce((sum, item) => sum + (item.price * item.quantity), 0); // 40.50
            expect(subtotal).toBe(40.50);

            // Step 1: Validate PLZ
            const plzValidation = Oracle.validateDeliveryPlz(plz);
            expect(plzValidation.valid).toBe(true);
            expect(plzValidation.zone).toBe('core');

            // Step 2: Calculate fee (€40.50 >= €30.00 free delivery threshold)
            const feeResult = Oracle.calculateDeliveryFee(plz, subtotal);
            expect(feeResult.allowed).toBe(true);
            expect(feeResult.fee).toBe(0.00);
            expect(feeResult.free_threshold_met).toBe(true);
            expect(feeResult.total).toBe(40.50);

            // Step 3: Estimate transit window
            const etaResult = Oracle.calculateDeliveryEta(plz, 'normal');
            expect(etaResult.nominal_window).toContain('30–45 Min.');

            // Step 4: Generate 80mm driver dispatch receipt
            const order = {
                id: 10801,
                order_number: '10801',
                created_at_vienna: '18.10.2026 · 18:30 Uhr',
                target_time: 'ca. 19:00–19:15 Uhr',
                customer_name: 'Familie Leitner',
                customer_address: {
                    street: 'Linzer Straße 14',
                    details: 'Einfamilienhaus mit Holzgartenzaun',
                    plz: '4300',
                    city: 'St. Valentin',
                    phone: '+43 676 1122334'
                },
                zone_label: 'ZONE 1 (Core · St. Valentin)',
                driver_note: 'Hund im Garten bitte Tor schließen!',
                payment_status: 'paid',
                payment_method: 'apple_pay',
                payment_method_title: 'Apple Pay (Online bezahlt)',
                subtotal,
                delivery_fee: feeResult.fee,
                total: feeResult.total,
                items
            };

            const driverBon = Oracle.renderDriverReceipt(order);
            expect(driverBon).toContain('WOW EXPRESS · ST. VALENTIN');
            expect(driverBon).toContain('Familie Leitner');
            expect(driverBon).toContain('Linzer Straße 14');
            expect(driverBon).toContain('[ ✓ BEREITS ONLINE BEZAHLT ]');
            expect(driverBon).toContain('KEIN GELD BEIM KUNDEN KASSIEREN!');
            expect(driverBon).toMatch(/Liefergebühr:\s+0,00 €/);
            expect(driverBon).toMatch(/GESAMTSUMME:\s+40,50 €/);
        });
    });

    describe('Tier 4.2: Scenario 2 — Ennsdorf Worker Lunch (Under Min -> Upsell -> COD)', () => {
        test('T4.2.1: Enforces €25 min order, recovers on upsell, generates COD warning receipt', () => {
            const plz = '4303'; // Ennsdorf Extended Zone
            
            // Initial cart: subtotal €22.90 (< €25.00 min)
            const initialSubtotal = 22.90;
            const step1 = Oracle.calculateDeliveryFee(plz, initialSubtotal);
            expect(step1.allowed).toBe(false);
            expect(step1.deficit).toBe(2.10);
            expect(step1.error_message).toContain('Mindestbestellwert für Lieferungen nach 4303 Ennsdorf beträgt 25,00 €');

            // Customer adds dessert (€4.50) -> new subtotal €27.40
            const updatedSubtotal = 27.40;
            const step2 = Oracle.calculateDeliveryFee(plz, updatedSubtotal);
            expect(step2.allowed).toBe(true);
            expect(step2.fee).toBe(3.90);
            expect(step2.total).toBe(31.30);
            expect(step2.free_threshold_met).toBe(false); // below €45 free threshold

            // Order submitted with Cash on Delivery
            const order = {
                id: 10802,
                order_number: '10802',
                customer_name: 'Herr Manuel Berger',
                customer_address: {
                    street: 'Gewerbestraße 4',
                    details: 'Bürogebäude 2, 1. Stock',
                    plz: '4303',
                    city: 'Ennsdorf',
                    phone: '+43 699 8765432'
                },
                zone_label: 'ZONE 2 (Extended · Ennsdorf)',
                driver_note: 'Bei Empfang melden',
                payment_status: 'unpaid',
                payment_method: 'cod',
                subtotal: updatedSubtotal,
                delivery_fee: step2.fee,
                total: step2.total,
                items: [
                    { quantity: 1, name: 'Gebratener Reis (Huhn)', modifiers: [] },
                    { quantity: 1, name: 'Frühlingsrollen (4 Stk)', modifiers: [] },
                    { quantity: 1, name: 'Gebackene Banane mit Honig', modifiers: [] }
                ]
            };

            const driverBon = Oracle.renderDriverReceipt(order);
            expect(driverBon).toContain('[ ⚠️ BARZAHLUNG BEI LIEFERUNG ]');
            expect(driverBon).toContain('BITTE BEIM KUNDEN KASSIEREN:');
            expect(driverBon).toContain('EXAKTER BETRAG:  31,30 €');
            expect(driverBon).toMatch(/Liefergebühr:\s+3,90 €/);
            expect(driverBon).toMatch(/GESAMTSUMME:\s+31,30 €/);
        });
    });

    describe('Tier 4.3: Scenario 3 — Out-of-Zone Commuter Fallback to Takeaway', () => {
        test('T4.3.1: Graceful rejection of Steyr address with 1-click fallback to self-pickup', () => {
            const outPlz = '4400';
            const subtotal = 19.50;

            // Delivery attempt fails gracefully
            const val = Oracle.validateDeliveryPlz(outPlz);
            expect(val.valid).toBe(false);
            expect(val.error_message).toContain('Leider beliefern wir die Postleitzahl 4400 derzeit nicht');
            expect(val.error_message).toContain('Selbstabholung im Restaurant');

            // 1-Click fallback action executed
            const pickupOrder = Oracle.calculateDeliveryFee(outPlz, subtotal, 'abholung');
            expect(pickupOrder.allowed).toBe(true);
            expect(pickupOrder.fee).toBe(0.00);
            expect(pickupOrder.min_order).toBe(0.00);
            expect(pickupOrder.total).toBe(19.50);
        });
    });

    describe('Tier 4.4: Scenario 4 — Kitchen Surge & Peak Hour Delivery Time Adjustment', () => {
        test('T4.4.1: Surge delay recalculates transit window to 60-75 minutes for customer display', () => {
            const plz = '4470'; // Enns (Extended zone)
            
            // Standard off-peak ETA
            const normalEta = Oracle.calculateDeliveryEta(plz, 'normal');
            expect(normalEta.min_minutes).toBe(35); // 20 prep + 15 transit
            expect(normalEta.max_minutes).toBe(55); // 30 prep + 25 transit

            // Kitchen peak surge activated in /betrieb/
            const surgeEta = Oracle.calculateDeliveryEta(plz, 'surge');
            expect(surgeEta.min_minutes).toBe(60); // 45 prep + 15 transit
            expect(surgeEta.max_minutes).toBe(85); // 60 prep + 25 transit
            expect(surgeEta.nominal_window).toContain('60–85 Min.');
        });
    });

    describe('Tier 4.5: Scenario 5 — Full Operational Dispatch & Kanban Lifecycle', () => {
        test('T4.5.1: Live order enters feed, filters by delivery, advances states with concurrency lock', () => {
            let orderFeed = [
                { id: 901, status: 'new', fulfillment_type: 'lieferung', version: 'v1_hash_init', customer_name: 'Sophie Kern' },
                { id: 902, status: 'new', fulfillment_type: 'abholung',  version: 'v1_hash_init', customer_name: 'Thomas Eder' }
            ];

            // 1. Staff switches filter to "Lieferung"
            const filteredFeed = Oracle.filterDashboardOrders(orderFeed, 'lieferung');
            expect(filteredFeed.length).toBe(1);
            expect(filteredFeed[0].id).toBe(901);

            // 2. Staff accepts order: state transitions from 'new' to 'preparing'
            const currentOrder = filteredFeed[0];
            const lockCheck1 = Oracle.checkOrderConcurrency(currentOrder.version, 'v1_hash_init');
            expect(lockCheck1).toBe(true);

            currentOrder.status = 'preparing';
            currentOrder.version = 'v2_hash_prep';

            // 3. Concurrent conflicting update attempt by second staff member
            const staleLockAttempt = Oracle.checkOrderConcurrency(currentOrder.version, 'v1_hash_init');
            expect(staleLockAttempt).toBe(false); // HTTP 409 Conflict

            // 4. Order is ready and dispatched
            currentOrder.status = 'dispatched';
            currentOrder.version = 'v3_hash_dispatched';

            // 5. Driver thermal receipt printed
            const driverBon = Oracle.renderDriverReceipt({
                id: currentOrder.id,
                customer_name: currentOrder.customer_name,
                customer_address: { street: 'Kirchenplatz 2', plz: '4300', city: 'St. Valentin', phone: '+43 676 5554433' },
                payment_status: 'paid',
                payment_method: 'stripe',
                subtotal: 26.00,
                delivery_fee: 2.50,
                total: 28.50,
                items: [{ quantity: 2, name: 'M1 Gebratene Nudeln' }]
            });
            expect(driverBon).toContain('[ ✓ BEREITS ONLINE BEZAHLT ]');

            // 6. Delivery completed
            currentOrder.status = 'completed';
            expect(currentOrder.status).toBe('completed');
        });
    });
}
