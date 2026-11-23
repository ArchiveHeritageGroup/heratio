> Heratio Help Center article. Category: Library.

# Circulation Statistics

The circulation statistics report shows how your collection is being used over a chosen period: loans, returns, renewals, overdue rates, active borrowers and turnover, with breakdowns by month and branch. It also names your busiest titles and the stock nobody has borrowed. You can read it on screen, download it as CSV or PDF, or have it emailed on a schedule.

Open **Library Management > Reports > Circulation**, or go to `/library-manage/reports/circulation`. The report needs the update permission. It counts patrons but never names them.

## Run the report

1. Choose a **Period**: Last full month, Last full quarter, Last full year or Custom dates.
2. For Custom dates, set **From** and **To**. A custom range can be up to five years.
3. Choose a **Branch** if you want one, or leave all branches.
4. Choose **Apply**.

The named periods always mean the last complete one, so a half-finished month is never shown as if it could be compared with a full month. The **From** and **To** boxes are only used with Custom dates.

If your account is locked to a branch, you see that branch only. If some branches are denied to you, the list offers **All my branches** and the report covers just those.

## What the figures mean

| Measure | How it is counted |
| --- | --- |
| Issues | Loans issued in the period |
| Returns | Loans returned in the period, whenever they were issued |
| Renewals | Total renewals on loans issued in the period |
| Loans renewed (%) | Share of issued loans renewed at least once |
| Late or overdue loans | Issued loans returned after the due date, or still out and past it |
| Overdue rate (%) | Late or overdue loans as a share of issues |
| Active borrowers | Different patrons who borrowed in the period |
| Copies in stock | Copies held at the end of the period |
| Collection turnover | Issues divided by copies in stock |
| Holds placed, cancelled, expired | Hold activity in the period |

: Circulation summary measures

Below the summary you will find issues and returns per month, a branch comparison, the 25 busiest titles and a dead stock list. Dead stock means copies held with no loan in the period, longest unused first. The badge gives the full total, and the table shows the first 25.

**Patron activity** groups patrons by how many loans they took in the period, including a band for those who took none. The **Fines** table shows amounts assessed and outstanding per currency.

## Reading the numbers with care

Copies now marked withdrawn, lost or missing with no recorded date are counted as held in past periods, because the date they left stock is unknown. The report displays a note when this affects your figures. Recording withdrawal dates makes historic turnover more accurate.

Heratio does not yet record fine payments or waivers. The Paid and Waived columns appear only when stored records carry those amounts. Otherwise Outstanding is simply the full amount assessed.

Holds waiting for collection are deliberately left out, because a ready hold is not closed at checkout and the count would be misleading.

## Export

Use the **CSV** or **PDF** button above the summary. Both carry the period and branch you are viewing.

## Email the report on a schedule

Heratio can send the last complete period to a list of recipients as CSV and PDF attachments. This is off by default, and the settings below have no screen yet, so your system administrator needs to set them.

| Setting | Purpose |
| --- | --- |
| `circulation_report_email_enabled` | Master switch, off by default |
| `circulation_report_email_recipients` | Email addresses, comma separated |
| `circulation_report_email_period` | `monthly` (default), `quarterly` or `yearly` |
| `circulation_report_email_branch` | One branch ID, or empty for all branches |
| `circulation_report_email_from_name` | Display name for the sender |

: Scheduled circulation report settings

The job also has to be enabled. Go to the Cron Jobs page at `/admin/ahgSettings/cronJobs`, find **Circulation Report Email**, and turn it on. If the row is missing, use **Seed Defaults** on that page. It runs at 04:30 on the first day of each month. A quarterly or yearly setting sends only in the first month of the new period, and a period already sent is not sent again.

To test without sending anything:

```bash
php artisan ahg:library-email-circulation-report --dry-run
```

The `--force` option sends even if the report is switched off, not due or already sent, but it still needs recipients. Use it with care, since it delivers real email.

## Troubleshooting

"The report period cannot be longer than five years" means the custom range is too wide, so shorten it.

A 403 error on the report usually means your account is locked to a branch you are not permitted to report on, or that you asked for a branch outside your access.

If the scheduled email does not arrive, check that the switch is on, the cron job is enabled, at least one recipient address is valid, and the system can send mail. A dry run tells you straight away if the switch is off or no recipients are set.
