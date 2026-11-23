> Heratio Help Center article. Category: Library.

# Inter-Library Loan Supplier Routing

Supplier routing sends a borrowing request to one partner library at a time, in an order you control. If that supplier declines, does not answer in time or cannot be reached, the request can move to the next one. Partners can send their replies straight into Heratio as EDI messages, and staff can enter replies that arrive by post or email.

Automatic routing is off by default. Until you turn it on, nothing goes to a partner unless a member of staff sends it.

## Where to find it

The rota is at `/library-manage/ill-routing` and the request list is at `/library-manage/ill-requests`. Neither has a button on the Library dashboard at present, and the dashboard's **Inter-Library Loan** button opens a different list. To route a request, open it from `/library-manage/ill-requests` and choose **Supplier routing**. These pages need the update permission.

Suppliers must first exist as trading partners, which are managed at `/library-manage/trading-partners`.

## Set up the rota

1. Open the rota and find **Add a supplier**.
2. Choose the **Trading partner**.
3. Set the **Priority**. Lower numbers are asked first. The default is 100.
4. Set the **Timeout (hours)**, which is how long this supplier has to reply. The default is 72, and the maximum is 720.
5. Enter **Material types**, comma separated, if this supplier handles only some kinds of material. Leave it blank for all.
6. Choose **Add**.

Entries can be edited in place with **Save**, taken out of use by unticking **Active**, or deleted with **Remove**.

Among suppliers with the same priority, Heratio prefers the better fill rate over the last 365 days, then whichever was asked least recently, so equal partners share the work. A new partner is not penalised for one early decline.

A partner is skipped when it is in test mode, or when its transport is not available on your installation. The rota page lists any skipped supplier with the reason.

## Route a request

1. Open the request and choose **Supplier routing**.
2. Check the **Next suppliers** list.
3. Choose **Send to next supplier**.

The **Attempts** table records each supplier tried, with the status, send time, reply deadline and any note. Statuses are Awaiting reply, Accepted, Declined, Timed out, Send failed, Withdrawn and Sent by hand.

A request sent by hand outside the rota stays off it, so that a second library is not asked for the same item.

When nobody is left, **Mark unfulfilled** closes the request. If no supplier could be reached at all and nothing was delivered, Heratio will not close the request by itself. Choose **Retry failed sends**, or **Mark unfulfilled** if you decide to give up. A supplier that fails three sends is skipped for that request until you retry.

**Stop waiting for this supplier** frees the request so it can move on. The supplier is not told, so contact them if the item may still be on its way.

## Enter a reply by hand

When a reply arrives outside the system, open the request's routing page and use **Enter a partner reply**. Choose who it is **From**, paste the **EDI message**, and choose **Apply reply**. The result is shown at the top of the page.

## Turn on automatic routing

With **Automatic routing** on, a decline or timeout sends the request to the next supplier with no staff action. A scheduled job checks for expired attempts every hour. It also picks up open requests left idle by a failed send or by a decline received while routing was off.

We recommend proving the whole loop with one real partner before switching this on, because it transmits to partners without anyone reviewing each send. An administrator can preview what the job would do without changing or sending anything:

```bash
php artisan ahg:library-ill-route-timeouts --dry-run
```

## Receive replies from partners

A partner sends its messages by HTTP POST to `/api/library/ill/edi/inbound`, with a bearer token in the `Authorization` header. To issue one, choose **Issue** in the Inbound token column of the rota. The token is shown once only, so copy it at that moment and pass it to the partner securely. **Replace** issues a new token and stops the old one working.

A message can be up to 256 KB and must be UTF-8. Each partner can reach only requests it was actually asked to supply. A repeated identical message that has already taken effect is not applied twice.

**Recent inbound messages** on the rota page shows the last 25 messages with their outcomes:

| Outcome | Meaning |
| --- | --- |
| Applied | The reply took effect |
| Late reply, not applied | This supplier is no longer active on the request |
| No matching request | No request is open with this partner under that reference |
| Status not understood | The message could not be decoded, or its status is not recognised |
| Needs staff review | The status is not one a supplier may set |
| Invalid status change | The request has moved on, so the change cannot be made |

: Inbound EDI message outcomes

## Deadlines after acceptance

Once a supplier accepts, it has 14 days to ship. After that the request appears under **Requests needing attention** on the rota. The period comes from the setting `library_ill_fulfilment_days`, which has no settings screen yet, so changing it is a job for your system administrator.

## Troubleshooting

"No eligible supplier on the rota" means no active supplier fits the request. Check that entries are active, that the partner is active and out of test mode, and that the material type matches.

"This request was sent manually and is not on the rota" means staff already sent it by hand, so routing will not ask a second library.

If timeouts do not fail over, check that Automatic routing is on and that the application scheduler is running on your server.

For partners using the manual file transport, outbound files are written to the folder set by `HERATIO_ILL_EDI_OUTBOUND_PATH`, by default `storage/app/library-edi/outbound`. The web server must be able to write to it, or sends will fail.
