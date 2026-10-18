/**
 * Tier 3: Cross-Feature Combinations E2E Test Suite
 * 
 * Verifies pairwise and cross-module interactions:
 * 1. Branch Switching + Dynamic Checkout Adaptation
 * 2. Core Zone + Free Delivery Threshold (€34.50) + Cash on Delivery Payment
 * 3. Extended Zone + Delivery Fee (€3.90) + Online Paid + 80mm Driver Thermal Receipt
 * 4. Out-of-Zone PLZ + 1-Click Graceful Fallback to Selbstabholung (Pickup)
 * 5. Operations Dashboard Multi-Tenant Context + Live Fulfillment Filtering + Dual Bon Actions
 */

import { describe, test, expect } from './lib/test-harness.mjs';
import * as Oracle from './lib/oracle.mjs';

export function registerTier3Tests() {
    describe('Tier 3.1: Branch Switching & Dynamic Checkout Capabilities', () => {
        test('T3.1.1: Switching from "valentin" (restaurant) to "express" (hub) unlocks delivery mode', () => {
            const valentinCfg = Oracle.BRANCH_CONFIGS.valentin;
            expect(valentinCfg.has_delivery).toBe(false);
            expect(valentinCfg.has_takeaway).toBe(true);

            // Switching branch context to express
            const expressCfg = Oracle.BRANCH_CONFIGS.express;
            expect(expressCfg.has_delivery).toBe(true);
            expect(expressCfg.delivery_zones).toBeDefined();
            expect(expressCfg.delivery_zones.length).toBe(4);
        });

        test('T3.1.2: Delivery PLZ validation activates only when branch supports delivery', () => {
            const currentBranch = Oracle.BRANCH_CONFIGS.express;
            if (currentBranch.has_delivery) {
                const val = Oracle.validateDeliveryPlz('4300');
                expect(val.valid).toBe(true);
            } else {
                throw new Error('Express branch must support delivery');
            }
        });
    });

    describe('Tier 3.2: Core Zone + Free Delivery Threshold + Cash Payment Interaction', () => {
        test('T3.2.1: Order meeting €30 free threshold with cash payment renders exact COD banner', () => {
            const plz = '4300';
            const subtotal = 34.50;
            const feeResult = Oracle.calculateDeliveryFee(plz, subtotal);

            // Fee should be 0.00
            expect(feeResult.allowed).toBe(true);
            expect(feeResult.fee).toBe(0.00);
            expect(feeResult.free_threshold_met).toBe(true);
            expect(feeResult.total).toBe(34.50);

            // Generate driver receipt with cash on delivery
            const order = {
                id: 10555,
                customer_name: 'Familie Gruber',
                customer_address: { street: 'Schubertstraße 12', plz: '4300', city: 'St. Valentin', phone: '+43 664 1234567' },
                payment_status: 'unpaid',
                payment_method: 'cod',
                subtotal: feeResult.subtotal,
                delivery_fee: feeResult.fee,
                total: feeResult.total,
                items: [
                    { quantity: 2, name: 'Pad Thai Gai', modifiers: [] },
                    { quantity: 1, name: 'Gäng Kiew Wan', modifiers: ['Scharf'] }
                ]
            };

            const bon = Oracle.renderDriverReceipt(order);
            expect(bon).toContain('[ ⚠️ BARZAHLUNG BEI LIEFERUNG ]');
            expect(bon).toContain('BITTE BEIM KUNDEN KASSIEREN:');
            expect(bon).toContain('EXAKTER BETRAG:  34,50 €');
            expect(bon).toMatch(/Liefergebühr:\s+0,00 €/);
            expect(bon).toMatch(/GESAMTSUMME:\s+34,50 €/);
            // Must NOT contain online paid banner
            expect(bon.includes('[ ✓ BEREITS ONLINE BEZAHLT ]')).toBe(false);
        });
    });

    describe('Tier 3.3: Extended Zone + Delivery Fee + Online Paid + Monospace Thermal Receipt', () => {
        test('T3.3.1: Enns order with €3.90 fee and Stripe payment generates DO NOT COLLECT banner', () => {
            const plz = '4470';
            const subtotal = 28.00;
            const feeResult = Oracle.calculateDeliveryFee(plz, subtotal);

            // Delivery fee of 3.90 applies
            expect(feeResult.allowed).toBe(true);
            expect(feeResult.fee).toBe(3.90);
            expect(feeResult.total).toBe(31.90);
            expect(feeResult.free_threshold_met).toBe(false);

            const order = {
                id: 10666,
                customer_name: 'Dr. Stefan Hofer',
                customer_address: { street: 'Stadtplatz 15', plz: '4470', city: 'Enns', phone: '+43 650 9876543' },
                zone_label: 'ZONE 2 (Extended · Enns)',
                payment_status: 'paid',
                payment_method: 'stripe',
                payment_method_title: 'Stripe Online Kreditkarte',
                subtotal: feeResult.subtotal,
                delivery_fee: feeResult.fee,
                total: feeResult.total,
                items: [
                    { quantity: 1, name: 'Massaman Curry (Rind)', modifiers: ['Erdnüsse separat'] },
                    { quantity: 1, name: 'Satay Spieße (4 Stück)', modifiers: [] }
                ]
            };

            const bon = Oracle.renderDriverReceipt(order);
            expect(bon).toContain('[ ✓ BEREITS ONLINE BEZAHLT ]');
            expect(bon).toContain('KEIN GELD BEIM KUNDEN KASSIEREN!');
            expect(bon).toContain('4470 Enns');
            expect(bon).toMatch(/Liefergebühr:\s+3,90 €/);
            expect(bon).toMatch(/GESAMTSUMME:\s+31,90 €/);
            // Must NOT contain COD warning
            expect(bon.includes('[ ⚠️ BARZAHLUNG BEI LIEFERUNG ]')).toBe(false);
        });
    });

    describe('Tier 3.4: Out-of-Zone + Graceful Fallback to Selbstabholung (Pickup)', () => {
        test('T3.4.1: Entering out-of-zone PLZ 4400 blocks delivery then switching to pickup unlocks checkout', () => {
            const subtotal = 18.00; // Below delivery min, but pickup has no min

            // 1. User enters Steyr PLZ 4400 under delivery
            const deliveryAttempt = Oracle.calculateDeliveryFee('4400', subtotal, 'lieferung');
            expect(deliveryAttempt.allowed).toBe(false);
            expect(deliveryAttempt.valid).toBe(false);
            expect(deliveryAttempt.error_message).toContain('Leider beliefern wir die Postleitzahl 4400 derzeit nicht');

            // 2. User clicks "Zur Selbstabholung wechseln" (1-click fallback)
            const pickupFallback = Oracle.calculateDeliveryFee('4400', subtotal, 'abholung');
            expect(pickupFallback.allowed).toBe(true);
            expect(pickupFallback.fulfillment_type).toBe('abholung');
            expect(pickupFallback.fee).toBe(0.00);
            expect(pickupFallback.total).toBe(18.00);
            expect(pickupFallback.notice).toContain('Kostenlose Selbstabholung');
        });
    });

    describe('Tier 3.5: Dashboard Multi-Location Context & Live Fulfillment Filtering', () => {
        test('T3.5.1: Express dashboard filters live queue and exposes dual bon print actions', () => {
            const mixedOrders = [
                { id: 201, fulfillment_type: 'lieferung', status: 'new', customer_name: 'Kunde 1', plz: '4300', payment_status: 'paid' },
                { id: 202, fulfillment_type: 'abholung',  status: 'new', customer_name: 'Kunde 2', plz: '4300', payment_status: 'unpaid' },
                { id: 203, fulfillment_type: 'lieferung', status: 'preparing', customer_name: 'Kunde 3', plz: '4470', payment_status: 'paid' }
            ];

            // Filter for delivery only
            const deliveryOrders = Oracle.filterDashboardOrders(mixedOrders, 'delivery');
            expect(deliveryOrders.length).toBe(2);
            expect(deliveryOrders.every(o => o.fulfillment_type === 'lieferung')).toBe(true);

            // Each delivery order provides dual receipts capability
            for (const order of deliveryOrders) {
                const driverReceipt = Oracle.renderDriverReceipt({
                    id: order.id,
                    customer_name: order.customer_name,
                    payment_status: order.payment_status,
                    subtotal: 25.00,
                    delivery_fee: 2.50,
                    total: 27.50,
                    items: [{ quantity: 1, name: 'Testgericht' }]
                });
                expect(driverReceipt).toContain('FAHRER-DISPATCH-LIEFERSCHEIN');
            }
        });
    });
}
