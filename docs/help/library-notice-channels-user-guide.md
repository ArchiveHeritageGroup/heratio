> Heratio Help Center article. Category: Library.

# Library Notice Channels: Email, SMS and Print

Heratio can send overdue notices by email or SMS, or queue them for printing. Each patron can have a preferred channel, and when that channel cannot be used Heratio tries the next one and records what happened.

## Current status

Delivery on these channels has not yet been proven. The logic has been exercised only with simulated email and SMS services, and no real SMS gateway, mail delivery or printed run has been tested. Treat your first live run as a pilot: start with a few loans, confirm that the messages actually arrive, and read the log before relying on it.

Nothing changes for patrons when this feature is installed. The SMS and print templates start out inactive, and no SMS can be sent until a gateway is configured.

## What you need to configure

Email uses the mail settings of your Heratio installation. Ask your administrator to confirm that outgoing mail works, because a misconfigured server shows up only as failed entries in the notice log.

SMS needs a gateway that accepts a simple HTTP request. There is no settings screen for this yet, so an administrator must add these values to Heratio's settings table:

| Setting | Purpose |
| --- | --- |
| `sms_gateway` | Must be `http`. Any other value means no SMS is sent. |
| `sms_http_endpoint` | The full URL of the provider's API. Required. |
| `sms_http_token` | Bearer token, if the provider uses one. |
| `sms_http_to_field` | Field name for the phone number. Defaults to `to`. |
| `sms_http_body_field` | Field name for the message. Defaults to `body`. |
| `sms_http_method` | `POST` (the default) or `GET`. |

These settings are shared with the one-time passcode feature in security clearance. Changing them affects both.

Print needs no external service, only active print templates. Patron phone numbers must contain 8 to 15 digits, with an optional leading plus sign. Spaces, brackets, dots and hyphens are ignored.

## Review and activate templates

Templates live at `/library-manage/notice-templates`. The page has no menu link at present, so bookmark it. There are three tiers, `overdue_1`, `overdue_2` and `overdue_final`, each with its own template per channel. The SMS and print templates are set to trigger at 1, 7 and 21 days overdue.

1. Open a template with **Edit**.
2. Adjust the subject, body and **Trigger (days overdue)**.
3. Tick **Active** when you are happy with it, then save.

An SMS is cut off at 459 characters, so keep the wording short. The placeholders you can use are listed on the templates page, including `{{patron_name}}`, `{{title}}`, `{{due_date}}`, `{{days_overdue}}`, `{{fine_amount}}` and `{{branch_name}}`.

## Set a patron's channel

Open the patron's record, select **Notice channel**, choose the **Preferred channel** and save. **Library default (email)** clears any preference. The page is restricted to administrators, so other staff who select the button will be refused.

## How a channel is chosen

Heratio tries the patron's preferred channel first, then email, then print. A channel is passed over when it has no active template for that tier, no gateway, or no usable address, and the reason is logged as skipped.

A real sending failure behaves differently. A failed email is logged and tried again on the next run. A failed SMS is tried at most twice for the same loan and tier before Heratio moves on, and three SMS failures in a row stop SMS for the remainder of that run. A patron receives each tier for a loan only once, whichever channel delivered it.

The notice job, `ahg:library-overdue-notices`, is scheduled daily at 06:00. An administrator can preview a run with this command:

```
php artisan ahg:library-overdue-notices --dry-run
```

A dry run sends and queues nothing, but it does add entries marked skipped to the notice log.

## Produce a print run

The **Notice Channels** page at `/library-manage/notice-channels` is available to administrators and also has no menu link yet. It shows how many notices are waiting to print.

1. Select **Create print run**. Heratio removes notices whose loan has ended or is no longer overdue. If a loan has several tiers waiting, only the most severe is printed.
2. Select **Download** beside the new run to get the PDF.
3. Print the notices and post them or hand them over at the counter.

A patron with no postal address on file is marked for counter collection. Each run holds up to 500 notices, so a larger queue takes several runs. Printed notices show as **In a print run** and never as Sent, because Heratio cannot know whether the paper reached the patron.

## Read the notice log

The same page summarises every attempt by channel and status: Sent, Queued for print, In a print run, Failed or Skipped. Sent means only that the mail server or SMS gateway accepted the message. It does not confirm that the patron received it.

## Known limits

A gateway timeout is treated as a failure even if the provider accepted the message, so a retry can occasionally send a duplicate SMS. Downloading an older print run regenerates the same PDF without checking the loans again, so do not reprint an old run without checking for items since returned. If the 500 oldest queued notices are all out of date, the page may report nothing to print while newer notices are still waiting. Create another run to continue.

## Troubleshooting

A warning that no SMS gateway is configured means `sms_gateway` is not `http` or `sms_http_endpoint` is empty.

When a patron who prefers SMS gets an email instead, the notice log will say why. Usually the phone number is unusable, the SMS template for that tier is inactive, or the gateway failed.

If no notices go out at all, check that at least one template is active and that the scheduler is running on your server. A print queue that stays at zero is often correct: print is used only when a patron prefers it or the earlier channels could not be used, and only when a print template for that tier is active.
