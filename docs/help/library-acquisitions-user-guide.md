# Library Acquisitions

The Acquisitions module manages the money side of collection building: vendors you buy from, budgets you spend against, and the purchase orders that draw down those budgets as items are ordered and received.

Open it from **Library Management → Acquisitions Dashboard**.

## Acquisitions dashboard

The dashboard is the landing page for the module. It summarises committed and actual spend against each budget, lists recent and outstanding orders, and links to vendors, budgets and order creation. Use it to see at a glance how much of each budget remains and which orders are still awaiting delivery.

## Vendors

Vendors are the suppliers (booksellers, subscription agents, donors-of-record) that orders are placed with.

- **Add a vendor** with its name, contact details and, where relevant, an account or customer number.
- Vendors can be **edited** or **deleted** (deletion is blocked while orders reference the vendor).
- Each purchase order is linked to exactly one vendor.

## Budgets

Budgets are the funds orders spend against, identified by a **budget code** (for example a fund or cost-centre code).

- **Create a budget** with its code, description and allocated amount.
- As order lines are committed and received, the budget's committed and spent totals update; the remaining balance is recalculated from the order activity.
- Budgets can be edited or removed when no order lines reference them.

## Purchase orders

A purchase order records what was ordered from a vendor and tracks it through to receipt.

1. **Create an order** — choose the vendor and the budget it draws from.
2. **Add order lines** — one line per title/item, with quantity, unit price and the catalogue or bibliographic detail. The line total rolls up into the order total and the budget commitment.
3. **Move the order through its status** — orders advance through their workflow (for example draft → ordered → received) using the status actions on the order page.
4. **Receive items** — receive lines individually, or use **Receive all** to mark every line on the order as received in one step. Receiving converts committed spend into actual spend on the budget.
5. **Write-off** — an order (or its outstanding balance) can be written off when it will not be fulfilled, releasing the commitment from the budget.

## Tips

- Set up vendors and budgets first; an order needs both before it can be created.
- The dashboard's remaining-balance figures come from live order activity, so receiving or writing off an order updates them immediately.
- For programmatic access to the same data, see the **Library Acquisitions API** help article.
