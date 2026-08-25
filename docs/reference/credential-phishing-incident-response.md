# Credential phishing: recognising the gate, and the response that worked

Date: 2026-08-24
Author: Dr Johan Pieterse
Status: incident closed, no compromise; written up as a repeatable procedure

An AHG staff member opened a link that arrived in a business thread from what
appeared to be a prospective client, was shown a "verify you are human" gate,
and entered Microsoft credentials into the page behind it. The credentials were
captured. Sign-in logs confirmed they were never used. This is what the thing
was, how it was cleared, and the several places the obvious response turns out
to be wrong.

## The lure

`radiuspine.de/981gnarl/`. The domain registration changed hands sixteen days
before the incident, it sits behind Cloudflare, it has no MX record, and its
document root returns 404. Only the one obscure path exists, which is how these
are handed out - one path per victim, so a takedown of one does not burn the
rest.

The page itself is a fake loading screen. "Almost there. The next page is being
prepared for you", a spinner, and a bare copyright line. It carries a single
script.

## What the spinner is actually doing

The script is a fingerprinter, not a loader. In the three seconds it makes you
wait it enumerates every property of `navigator`, `screen`, `window` and
`document`, reads the GPU vendor and model through
`WEBGL_debug_renderer_info`, collects local LAN addresses via a WebRTC STUN
request, records the timezone offset, and checks both whether the page is
inside an iframe and what `console.toString()` returns. Those last two are
sandbox and headless-browser detection. It then auto-submits the lot as a
hidden POST form back to the same URL, and the server decides from that profile
what to serve next: the credential form to a real visitor on a real desktop,
nothing at all to a scanner.

Two details mark it as deliberate rather than incidental. The string
`WEBGL_debug_renderer_info` is assembled from fragments at runtime, as is the
STUN URL, which defeats static scanning and which no legitimate script has any
reason to do. And the file is dressed as ordinary front-end work: a webpack
style hashed filename, an MIT licence banner, a sourcemap comment.

## Recognising one before you click

No MX record on the sending domain. A registration or ownership change within
the last month. A root that 404s while a random-word path resolves. A page
whose only content is a spinner and whose only asset is one script. A CAPTCHA
that does not actually test anything.

The two variants differ in what happens next, and it matters. If the page tells
you to press Win+R and paste something, that is ClickFix, and the payoff is an
infostealer running on your machine. If it shows a Microsoft login form, it is
credential theft, and the machine is usually never touched. The response to the
first is an endpoint response; the response to the second is an identity
response. Do not run the wrong one.

## The response sequence

Order matters here, because two of these steps undo each other if reversed.

Revoke sessions first. This is the only step that is remediation; everything
else is either forensics or hygiene. A phishing proxy that relays your login in
real time captures the session cookie Microsoft issues back, and that cookie
does not care that you have since changed the password. In the portal it is one
click at `myaccount.microsoft.com/device-list`, "Sign out everywhere". Then
rotate the password, in that order, so nothing issued earlier survives. Rotate
from a device that did not open the link.

Then audit the authentication methods, because registering their own
authenticator is the first persistence move these operators make. Enumerate
every method type rather than just the authenticator ones - a planted
`temporaryAccessPass` is a password-equivalent bypass credential and will not
show up if you only look at push MFA. Watch in particular for a second
registration carrying the same device name as a real one. The display name is
supplied by whoever registers it and is not verified, so duplicating a genuine
device name is exactly how a competent operator hides.

Then the mailbox: inbox rules, forwarding, and mailbox permissions. A rule
forwarding to an external SMTP address is the finding. A disabled rule pointing
at the mailbox's own legacy DN is not - that exfiltrates nothing.

Then OAuth consents, which are the one thing session revocation does not touch.
An app holding `Mail.ReadWrite`, `Mail.Send` and `offline_access` has its own
refresh token and survives every password change you make. Resolve each grant
to a real application name before judging it, and check `appOwnerOrganizationId`
against your own tenant and against the Microsoft first-party tenants.

Finally the sign-in logs, which answer the only question that really matters:
did anyone use what they took.

## Where this bites you

**The sign-in log window closes behind you.** Roughly fifty of your own clicks
during remediation will push the window you actually need off the page. Filter
by time in the Entra sign-in log rather than scrolling the personal view, and
expand entries to see IP addresses, because the collapsed view shows only a
city and an AiTM relay geolocates plausibly.

**An empty audit query is not a clean result.** If
`UnifiedAuditLogIngestionEnabled` is false, `Search-UnifiedAuditLog` returns
nothing for everything, and reads as an all-clear. Check that flag before
believing any audit search. Enabling it needs
`Enable-OrganizationCustomization` first, which is one-way and which Microsoft
will refuse outright while the tenant is mid-upgrade. Entra sign-in logs are
collected separately and stay reliable regardless, which is why they were the
usable evidence here.

**A v4-only sinkhole is not a block.** Adding `0.0.0.0 domain` to a hosts file
leaves AAAA resolution untouched, and a Cloudflare-fronted domain serves IPv6
happily. The entry looks like a control and is not one. Both lines are needed:

```
0.0.0.0 example.invalid
:: example.invalid
```

**The PowerShell route costs more than it returns.** `Revoke-MgUserSignInSession`
lives in `Microsoft.Graph.Users.Actions`, not `Microsoft.Graph.Users`;
`Get-MgAuditLogSignIn` lives in `Microsoft.Graph.Reports`; the revoke call
needs the `User.RevokeSessions.All` scope, which means reconnecting because an
existing token keeps the scopes it was issued with, and that scope needs admin
consent. `Connect-ExchangeOnline` can crash inside the WAM broker, for which
`-Device` is the workaround. Every one of those checks has a browser equivalent
that needs no module and no scope. Use the portal during an incident and save
the shell for afterwards.

**The nginx bot blocker cannot help.** It maps `$http_user_agent` on inbound
requests. A domain your staff visit is outbound traffic that nginx never sees.
Adding the domain there produces a control that looks real and does nothing,
which is worse than not adding it. The blocks that work are a Defender for
Endpoint indicator for fleet coverage, a tenant URL block in Defender for
Office so the same lure cannot be reopened from mail, and DNS at the router for
the network. A hosts entry covers exactly one machine.

## When it arrives through a business thread

Delivery through a live client or prospect thread is the case that costs money,
and it is worth separating from ordinary spam. Either the correspondent's
mailbox is genuinely compromised, in which case they have an incident they do
not know about and need telling by voice on a number found independently, or
the sender is spoofed, in which case somebody chose a name from your pipeline
and the approach was targeted. The message headers separate the two: a real
compromised account passes DKIM, a spoof usually fails alignment.

Either way, treat every payment instruction in that thread as suspect.
Redirected banking details on a genuine quote or invoice is the standard payoff
for this class of compromise, and bid and tender work is precisely the target
profile. Verify by voice, on a number already held, never one supplied in the
correspondence. And do not reply to the thread - if the account is compromised,
the reply reaches the wrong reader.

Message trace retains ten days, which is the window for finding out who else
received it.

## Residual exposure

Rotating the password does not unmake the copy they took. Anywhere that
password was reused stays exposed indefinitely, and a banked credential list
gets sold and used long after the incident feels closed. Password reuse is the
part of this that outlives the response.
