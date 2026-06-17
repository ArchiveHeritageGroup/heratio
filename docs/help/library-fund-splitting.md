# Library: Splitting an order line across multiple funds

Heratio acquisitions can charge a single purchase-order line to more than one budget fund. For example, a 1,000.00 order line can be split 60/40 across "Reference Materials" and "Special Collections", so each fund is debited 600.00 and 400.00 respectively. When a line is not split it continues to charge its whole value to a single fund, exactly as before.

## When to use this

Use multi-fund splitting whenever the cost of one item must be shared between budgets, such as:

- Co-funded purchases (a grant fund plus a departmental fund).
- Shared resources bought for several collections or faculties.
- Items partly covered by a special allocation and partly by the general acquisitions budget.

If only one fund pays for the line, you do not need to split it. Just leave the line's single fund as it is.

## How fund splits work

- Each order line can have zero or more split portions.
- Each portion names one fund (a budget code) and the amount charged to that fund.
- The portions must sum exactly to the line total. The editor shows a running balance and will not save until the balance reaches zero.
- A line with split portions is charged only through those portions. Its single fund code is ignored while the split exists.
- A line with no split portions charges its whole line total to its single fund (the legacy behaviour).

Funds offered in the split editor are the budgets for the order's fiscal year (derived from the order date). If no budgets match that year, all active budgets are offered.

## Editing a split

1. Open the purchase order (Acquisitions then the order number).
2. In the Order Lines table, find the line and select **Split funds**.
3. In the dialog, add a row for each fund: choose the fund and enter the amount.
4. Watch the **Balance** indicator. It turns green when the amounts sum to the line total.
5. Select **Save splits**. The page reloads and the line shows its fund portions.

To remove a split and return the line to a single fund, open the editor, delete every row, and save with an empty table.

You cannot edit splits on an order that is already received or cancelled.

## Effect on budgets

Budget figures update immediately after you save a split, with no overnight job:

- **Committed** is the total of all non-cancelled order portions charged to a fund.
- **Spent** is the total of received portions charged to a fund.

For split lines the portion amount is applied to each named fund. For unsplit lines the whole line total is applied to the order's fund. The two sources never overlap, so a line is never counted twice.

When a split line is received, each fund's spent amount rises by that fund's portion. When an order is cancelled or written off, its portions are released from every affected fund's committed total.

## Validation rules

- Every split row must name a known fund.
- Amounts cannot be negative.
- The portions must sum to the line total to the cent. A clear message is shown if they do not, and nothing is saved until the sum matches.

## Notes for administrators

- The split portions live in the `library_order_line_fund` table, one row per portion.
- Removing a line removes its split portions and refreshes every affected fund.
- The single fund column on the order line is retained for unsplit lines and as a fallback, so existing orders keep working unchanged.
