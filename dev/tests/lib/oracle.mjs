/**
 * Authoritative Business Logic Specification Oracle
 * 
 * Source of Truth: ORIGINAL_REQUEST.md, PROJECT.md, and specifications.md.
 * Provides independent mathematical, geographical, and formatting models
 * to generate expected values for all E2E tests.
 */
import fs from 'node:fs';
import path from 'node:path';

export const ZONES = {
    CORE: 'core',
    EXTENDED: 'extended',
    OUT_OF_ZONE: 'none'
};

export const ZONE_DETAILS = {
    [ZONES.CORE]: {
        name: 'Kernzone St. Valentin',
        postal_codes: ['4300'],
        min_order: 20.00,
        delivery_fee: 2.50,
        free_threshold: 30.00,
        transit_min: 10,
        transit_max: 15
    },
    [ZONES.EXTENDED]: {
        name: 'Erweiterte Lieferzone',
        postal_codes: ['4303', '4470', '4481'],
        cities: { '4303': 'Ennsdorf', '4470': 'Enns', '4481': 'Asten' },
        min_order: 25.00,
        delivery_fee: 3.90,
        free_threshold: 45.00,
        transit_min: 15,
        transit_max: 25
    }
};

export const BRANCH_CONFIGS = {
    steyr: {
        id: 'steyr',
        name: 'WoW Thai Steyr',
        short_name: 'Steyr',
        address: 'Enge Gasse 19',
        postcode: '4400',
        city: 'Steyr',
        phone: '+43 7252 45866',
        email: 'info@wow-thai-steyr.at',
        has_takeaway: true,
        has_dinein: true,
        has_delivery: false
    },
    valentin: {
        id: 'valentin',
        name: 'WoW Thai St. Valentin',
        short_name: 'St. Valentin',
        address: 'Hauptplatz 1',
        postcode: '4300',
        city: 'St. Valentin',
        phone: '+43 7435 52899',
        email: 'valentin@wow-thai.at',
        has_takeaway: true,
        has_dinein: true,
        has_delivery: false
    },
    saalbach: {
        id: 'saalbach',
        name: 'WoW Thai Saalbach',
        short_name: 'Saalbach',
        address: 'Oberdorf 45',
        postcode: '5753',
        city: 'Saalbach-Hinterglemm',
        phone: '+43 6541 6333',
        email: 'saalbach@wow-thai.at',
        has_takeaway: true,
        has_dinein: true,
        has_delivery: false
    },
    express: {
        id: 'express',
        name: 'WoW Express St. Valentin',
        short_name: 'WoW Express',
        address: 'Hauptplatz 1',
        postcode: '4300',
        city: 'St. Valentin',
        phone: '+43 7435 52899',
        email: 'express@wow-thai.at',
        has_takeaway: true,
        has_dinein: false,
        has_delivery: true,
        delivery_zones: ['4300', '4303', '4470', '4481']
    }
};

/**
 * Normalizes postal code input (strips whitespace, 'A-' prefix, non-digits).
 */
export function normalizePlz(input) {
    if (input === null || input === undefined) return '';
    let str = String(input).trim().toUpperCase();
    if (str.startsWith('A-') || str.startsWith('AT-')) {
        str = str.replace(/^(A|AT)-/, '');
    }
    const match = str.match(/\b\d{4}\b/);
    if (match) return match[0];
    return str.replace(/\D/g, '');
}

/**
 * Validates delivery postal code according to Section 3.1.1.
 */
export function validateDeliveryPlz(input) {
    const plz = normalizePlz(input);
    if (!plz || plz.length !== 4 || !/^\d{4}$/.test(plz)) {
        return {
            valid: false,
            plz,
            zone: null,
            zone_name: null,
            min_order: null,
            delivery_fee: null,
            free_threshold: null,
            error_message: 'Bitte geben Sie eine gültige 4-stellige österreichische Postleitzahl ein.'
        };
    }

    if (ZONE_DETAILS[ZONES.CORE].postal_codes.includes(plz)) {
        const z = ZONE_DETAILS[ZONES.CORE];
        return {
            valid: true,
            plz,
            zone: ZONES.CORE,
            zone_name: z.name,
            min_order: z.min_order,
            delivery_fee: z.delivery_fee,
            free_threshold: z.free_threshold,
            error_message: null
        };
    }

    if (ZONE_DETAILS[ZONES.EXTENDED].postal_codes.includes(plz)) {
        const z = ZONE_DETAILS[ZONES.EXTENDED];
        return {
            valid: true,
            plz,
            zone: ZONES.EXTENDED,
            zone_name: z.name,
            min_order: z.min_order,
            delivery_fee: z.delivery_fee,
            free_threshold: z.free_threshold,
            error_message: null
        };
    }

    return {
        valid: false,
        plz,
        zone: null,
        zone_name: null,
        min_order: null,
        delivery_fee: null,
        free_threshold: null,
        error_message: `Leider beliefern wir die Postleitzahl ${plz} derzeit nicht. Eine Lieferung ist nur in St. Valentin (4300), Ennsdorf (4303), Enns (4470) und Asten (4481) möglich. Möchten Sie Ihre Bestellung stattdessen zur kostenlosen Selbstabholung im Restaurant aufgeben?`
    };
}

/**
 * Calculates delivery fee and minimum order enforcement according to Section 3.1.2.
 */
export function calculateDeliveryFee(inputPlz, subtotalInput, fulfillmentType = 'lieferung') {
    const subtotal = Math.round(Number(subtotalInput || 0) * 100) / 100;
    
    if (fulfillmentType === 'abholung' || fulfillmentType === 'takeaway') {
        return {
            valid: true,
            allowed: true,
            fulfillment_type: 'abholung',
            fee: 0.00,
            min_order: 0.00,
            deficit: 0.00,
            free_threshold_met: true,
            remaining_to_free: 0.00,
            total: subtotal,
            notice: 'Kostenlose Selbstabholung im Restaurant'
        };
    }

    const val = validateDeliveryPlz(inputPlz);
    if (!val.valid) {
        return {
            valid: false,
            allowed: false,
            fulfillment_type: 'lieferung',
            fee: 0.00,
            min_order: null,
            deficit: 0.00,
            free_threshold_met: false,
            remaining_to_free: null,
            total: subtotal,
            error_message: val.error_message
        };
    }

    const minOrder = val.min_order;
    const standardFee = val.delivery_fee;
    const freeThreshold = val.free_threshold;

    if (subtotal < minOrder) {
        const deficit = Math.round((minOrder - subtotal) * 100) / 100;
        const ort = val.plz === '4300' ? '4300 St. Valentin' : `${val.plz} ${ZONE_DETAILS[ZONES.EXTENDED].cities[val.plz] || ''}`.trim();
        return {
            valid: true,
            allowed: false,
            fulfillment_type: 'lieferung',
            zone: val.zone,
            subtotal,
            fee: standardFee,
            min_order: minOrder,
            deficit,
            free_threshold_met: false,
            remaining_to_free: Math.round((freeThreshold - subtotal) * 100) / 100,
            total: subtotal + standardFee,
            error_message: `Der Mindestbestellwert für Lieferungen nach ${ort} beträgt ${minOrder.toFixed(2).replace('.', ',')} €. Ihr aktueller Bestellwert liegt bei ${subtotal.toFixed(2).replace('.', ',')} €. Bitte fügen Sie noch Artikel im Wert von ${deficit.toFixed(2).replace('.', ',')} € hinzu.`
        };
    }

    if (subtotal >= freeThreshold) {
        return {
            valid: true,
            allowed: true,
            fulfillment_type: 'lieferung',
            zone: val.zone,
            subtotal,
            fee: 0.00,
            min_order: minOrder,
            deficit: 0.00,
            free_threshold_met: true,
            remaining_to_free: 0.00,
            total: subtotal,
            notice: '✓ Kostenlose Lieferung! Sie haben die Freigrenze erreicht.'
        };
    }

    const remainingToFree = Math.round((freeThreshold - subtotal) * 100) / 100;
    return {
        valid: true,
        allowed: true,
        fulfillment_type: 'lieferung',
        zone: val.zone,
        subtotal,
        fee: standardFee,
        min_order: minOrder,
        deficit: 0.00,
        free_threshold_met: false,
        remaining_to_free: remainingToFree,
        total: Math.round((subtotal + standardFee) * 100) / 100,
        notice: `Liefergebühr: ${standardFee.toFixed(2).replace('.', ',')} € · Noch ${remainingToFree.toFixed(2).replace('.', ',')} € bis zur kostenlosen Lieferung!`
    };
}

/**
 * Calculates delivery time ETA based on prep time preset and zone transit time.
 */
export function calculateDeliveryEta(inputPlz, prepPreset = 'normal') {
    const val = validateDeliveryPlz(inputPlz);
    let prepMin = 20;
    let prepMax = 30;

    switch (prepPreset) {
        case 'express': prepMin = 15; prepMax = 20; break;
        case 'busy':    prepMin = 35; prepMax = 45; break;
        case 'surge':   prepMin = 45; prepMax = 60; break;
        default:        prepMin = 20; prepMax = 30; break;
    }

    const transitMin = (val.zone === ZONES.EXTENDED) ? 15 : 10;
    const transitMax = (val.zone === ZONES.EXTENDED) ? 25 : 15;

    const totalMin = prepMin + transitMin;
    const totalMax = prepMax + transitMax;

    return {
        zone: val.zone,
        min_minutes: totalMin,
        max_minutes: totalMax,
        nominal_window: `ca. ${totalMin}–${totalMax} Min.`
    };
}

/**
 * Generates standard 80mm monospace driver dispatch receipt markup/text.
 */
export function renderDriverReceipt(order) {
    const width = 42;
    const sepEqual = '='.repeat(width);
    const sepDash = '-'.repeat(width);
    const sepStar = '*'.repeat(width);

    const isPaidOnline = order.payment_status === 'paid' || order.payment_method === 'stripe' || order.payment_method === 'online';
    const totalFormatted = (Number(order.total || 0)).toFixed(2).replace('.', ',') + ' €';
    const subtotalFormatted = (Number(order.subtotal || 0)).toFixed(2).replace('.', ',') + ' €';
    const feeFormatted = (Number(order.delivery_fee || 0)).toFixed(2).replace('.', ',') + ' €';

    let paymentBanner = '';
    if (isPaidOnline) {
        paymentBanner = [
            sepStar,
            '*                                        *',
            '*    [ ✓ BEREITS ONLINE BEZAHLT ]        *',
            '*                                        *',
            '*    KEIN GELD BEIM KUNDEN KASSIEREN!    *',
            `*    Zahlart: ${(order.payment_method_title || 'Online bezahlt')}       *`.slice(0, 41) + '*',
            '*                                        *',
            sepStar
        ].join('\n');
    } else {
        paymentBanner = [
            sepStar,
            '*                                        *',
            '*    [ ⚠️ BARZAHLUNG BEI LIEFERUNG ]     *',
            '*                                        *',
            '*    BITTE BEIM KUNDEN KASSIEREN:        *',
            `*    EXAKTER BETRAG:  ${totalFormatted.padEnd(19)} *`,
            '*                                        *',
            sepStar
        ].join('\n');
    }

    const itemLines = (order.items || []).map(item => {
        let line = ` [ ] ${item.quantity}x ${item.name}`;
        if (item.modifiers && item.modifiers.length > 0) {
            line += '\n     + ' + item.modifiers.join(', ');
        }
        return line;
    }).join('\n');

    const address = order.customer_address || {};
    const street = address.street || '';
    const details = address.details || (address.floor ? `Stiege ${address.staircase || 1}, Top ${address.top || 1}` : '');
    const plzCity = `${address.plz || ''} ${address.city || ''}`.trim();
    const phone = order.customer_phone || address.phone || '';

    const receipt = [
        sepEqual,
        '        WOW EXPRESS · ST. VALENTIN        ',
        '         FAHRER-DISPATCH-LIEFERSCHEIN     ',
        sepEqual,
        `Bestellung:  #${order.order_number || order.id || 'N/A'}`,
        `Datum/Zeit:  ${order.created_at_vienna || '17.10.2026 · 18:25 Uhr'}`,
        `Zielzeit:    ${order.target_time || 'ca. 30–45 Min. (ASAP)'}`,
        sepDash,
        'EMPFÄNGER & LIEFERADRESSE:',
        `  Name:      ${order.customer_name || 'Kunde'}`,
        `  Strasse:   ${street}`,
        ...(details ? [`  Details:   ${details}`] : []),
        `  PLZ / Ort: ${plzCity}`,
        `  Zone:      ${order.zone_label || 'ZONE 1 (Core · St. Valentin)'}`,
        `  TELEFON:   ${phone}`,
        sepDash,
        'FAHRER-HINWEIS:',
        `  ${order.driver_note || 'Keine besonderen Hinweise.'}`,
        sepDash,
        paymentBanner,
        sepDash,
        `KONTROLL-PACKLISTE (${(order.items || []).length} Artikel):`,
        itemLines,
        sepDash,
        `Zwischensumme:                   ${subtotalFormatted.padStart(9)}`,
        `Liefergebühr:                    ${feeFormatted.padStart(9)}`,
        sepDash,
        `GESAMTSUMME:                     ${totalFormatted.padStart(9)}`,
        sepEqual,
        'Abfahrt: ____:____  Fahrer: ______________',
        '   WOW Express St. Valentin · Tel: +43 7435 52899',
        sepEqual
    ].join('\n');

    return receipt;
}

/**
 * Filters dashboard orders by fulfillment type.
 */
export function filterDashboardOrders(orders, filterType) {
    if (!orders || !Array.isArray(orders)) return [];
    if (!filterType || filterType === 'all') return orders;
    
    const normalized = filterType.toLowerCase();
    if (normalized === 'pickup' || normalized === 'abholung') {
        return orders.filter(o => o.fulfillment_type === 'abholung' || o.fulfillment_type === 'takeaway');
    }
    if (normalized === 'delivery' || normalized === 'lieferung') {
        return orders.filter(o => o.fulfillment_type === 'lieferung' || o.fulfillment_type === 'delivery');
    }
    return orders;
}

/**
 * Checks order optimistic concurrency lock version.
 */
export function checkOrderConcurrency(currentVersion, submittedVersion) {
    if (!currentVersion || !submittedVersion) return false;
    return currentVersion === submittedVersion;
}
