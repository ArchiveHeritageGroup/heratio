> Heratio Help Center article. Category: Administration / AHG Central.

# AHG Central - Fleet Monitoring

AHG Central is the optional cloud service that lets a group of Heratio installs
be watched from one place. Each install reports a daily **heartbeat** (it is
alive, and on which version) and - when the operator opts in - an hourly
**error-log sync**. The fleet is monitored from the AHG Workbench.

AHG Central is **advisory**. If it is switched off, unreachable, or the install
is air-gapped, Heratio runs exactly as before - nothing in the product depends
on it.

Configure it at **Admin → AHG Settings → AHG Central**.

## It onboards itself

A freshly installed Heratio joins the fleet with no manual steps:

- the AHG Central client ships inside Heratio, so updating Heratio updates it;
- the install reads its connection settings from its deployment configuration;
- it identifies itself from the server hostname;
- on its first heartbeat, AHG Central registers it automatically.

There is no registration form to fill in and no key to copy. The one switch an
operator touches is **Enable AHG Central Integration** - and for an
internet-connected install that is part of a managed fleet it is already on.
Turn it **off** for an air-gapped or sovereign install that must not contact a
cloud service.

## Error-log sync (opt-in)

**Sync Error Logs to AHG Central** is a separate switch, **off by default**.
Error logs can contain sensitive detail, so shipping them off the server needs
a deliberate decision.

When you turn it on, Heratio sends new error-log entries to AHG Central once an
hour, **redacted before they leave the server**: email addresses and long
number sequences are masked, and URL query strings are stripped. Stack traces,
client IP addresses, user agents and user ids are never sent at all. Only turn
this on when your institution is comfortable with off-site error visibility.

## Where you watch the fleet

Fleet monitoring lives in the **AHG Workbench** admin area. There an operator
sees every connected install - its version, when it was last seen, whether it
is online or has gone quiet - and recent errors across the whole fleet. The
Workbench also raises a notification when a new install joins, when an install
reports critical errors, or when an install goes silent.

A simple fallback dashboard is also available directly at
`central.theahg.co.za`.

## The settings

| Setting | What it does |
|---|---|
| Enable AHG Central Integration | Master switch for all AHG Central traffic. |
| Sync Error Logs to AHG Central | Opt-in switch for hourly error-log sync. Off by default. |
| AHG Central API URL | The AHG Central address. Pre-filled by the deployment. |
| API Key | The fleet key. Pre-filled by the deployment. |
| Site ID | This install's identifier. Leave blank to derive it from the hostname. |

Use the **Test Connection** button to confirm the install can reach AHG Central
before saving.
