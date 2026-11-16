# PS Client Care — Beta v0.1.0 acceptance criteria

## Customer dashboard
- [ ] The "Incidencias y seguimiento" account entry is visible for authenticated users.
- [ ] An authenticated customer sees only orders from their account and current shop.
- [ ] Selecting an order shows historical purchased items, quantities and references.
- [ ] Creating a ticket saves subject, message, customer/order/shop IDs and selected product lines.
- [ ] Customer messages are visible in ticket history; resolved tickets reopen on reply.
- [ ] Modifying id_order, ticket_id, or product IDs never exposes another customer's data.

## Back Office
- [ ] An employee can configure the recipient address.
- [ ] Staff can see ticket details, respond and transition open / in_progress / resolved.
- [ ] Entering a manual tracking number by order ID validates the current shop.
- [ ] Manual tracking is visible in customer dashboard without overwriting native carrier data.
- [ ] A native tracking number remains visible if no manual number is stored.

## Email
- [ ] Notification emails include the order reference and **all** purchased item lines.
- [ ] Email templates work with Spanish, Catalan, English and Portuguese storefront languages.
- [ ] An SMTP failure preserves the ticket/message with failed email status.
- [ ] Verify shop mail configuration before enabling use in production.

## Compatibility and operations
- [ ] Install, uninstall, and reinstall in a staging shop without destroying customer service history.
- [ ] Verify on at least one PrestaShop 1.7.8, 8 and 9 environment before advertising compatibility.
- [ ] Confirm multishop behavior and translation availability in the actual storefront theme.
- [ ] Document GDPR retention/deletion approach before production.
- [ ] No calls to Salesforce, Kairoseth Incidents or paid transport APIs.

## Gate
Do **not** merge the beta as production-ready until all critical checks above are confirmed. Passing PHP lint alone is insufficient.
