# Architecture

**PrestaShop module:** `psclientcare`. This module is independent from Salesforce and from Kairoseth Incidents.

**Customer entry point:** `displayCustomerAccount` hook -> module front controller `portal`, requiring an authenticated account.

**Persistence:** `pscc_ticket` (shop, customer, order, status), `pscc_message` (conversation, email delivery result), `pscc_ticket_product` (historical order-detail product lines), `pscc_tracking` (per-order tracking with source=manual).

**Back Office:** Module configuration screen, including support recipient, ticket reply/status and manual tracking code.

**Mail:** PrestaShop Mail::Send with local template pairs (HTML and text) under `mails/`; delivery attempt occurs after database write. The initial beta has no retry queue.

**Security invariants:** Customer order and ticket queries must filter by current shop and authenticated customer. An arbitrary order_detail ID cannot be linked unless it belongs to the selected order. Staff operations must validate shop context and admin CSRF. Do not save access credentials in Git.

**Follow-up automation:** Carrier connectors may set `source=automatic` later. No transport provider is included in 0.1.0.
