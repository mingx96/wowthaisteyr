---
name: woocommerce-checkout-qa
description: Verify WooCommerce cart and checkout behavior safely in a disposable local or approved staging environment.
---

# WooCommerce checkout QA

1. Read `AGENTS.md`, `COORDINATION.md`, and [the QA checklist](../../../docs/woocommerce-qa.md).
2. Run existing static and unit checks first; state their limits accurately.
3. Use test products, a non-live payment method, a test email recipient, and disposable/local or approved staging data.
4. Exercise only scenarios relevant to the changed flow: cart/session, options/modifiers, pickup slots, validation, confirmation, admin order details, email rendering, and responsive layout.
5. Do not use production data, create a real order, send real customer email, sync a database, or deploy during verification.
6. Record environment, scenarios, outcomes, and gaps; leave a PR for review.
