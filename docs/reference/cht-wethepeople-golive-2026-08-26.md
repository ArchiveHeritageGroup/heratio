# Constitution Hill Trust: bringing We The People back online

Date: 2026-08-26
Author: Dr Johan Pieterse
Status: complete - three sites live on HTTPS; follow-ups listed at the end

Three Constitution Hill Trust websites were dark or unreachable after the client moved DNS to a new provider mid-way through a plugin-upgrade project. This is what was wrong, what fixed it, and the several places the obvious diagnosis was the wrong one.

## What was actually broken

The sites were not damaged. A working, updated copy of each existed the whole time on AHG-hosted infrastructure. What was missing was every layer between that copy and the public:

- Two of the three hostnames had **no DNS record at all** (NXDOMAIN) - the move to the new provider had dropped the subdomain records.
- The remaining hostname still pointed at the old host, which served a placeholder page and no valid certificate.
- The servers were configured as **staging**: nginx answered only to `.test` hostnames, behind basic-auth, `allow 127.0.0.1; deny all`, self-signed certificates and `X-Robots-Tag: noindex`.
- Inbound ports 80 and 443 were closed at the vendor firewall.

So "the client is down" was four separate problems stacked, none of them the application.

## The plugin freeze, and why it was a procurement problem

The main site had been stuck on early-2022 versions of its entire page-builder stack. The cause was not neglect: free plugins from the WordPress repository were current, while every **licensed** plugin was frozen. Expired subscriptions meant the update servers refused to serve them, and because the page builder's free and pro halves must move together, one lapsed licence pinned the whole ecosystem.

That left two actively-exploited vulnerabilities live for years - an authenticated arbitrary-file-upload leading to remote code execution, and a subscriber-to-administrator takeover. Both were closed by the upgrade. The lesson for the proposal: remediation here was a **licence renewal**, not labour, and no amount of consulting time would have moved those plugins without it.

## The network diagnosis that was wrong twice

Outbound HTTPS from the build server failed with `SSL_ERROR_SYSCALL` against several hosts, blocking plugin downloads, licence activation and a VPN install. Two plausible causes were investigated and both were wrong.

**It looked like an MTU black hole.** The uplink was PPPoE and the router advertised 1492 while the interface was set to 1500; a `ping -M do -s 1472` failed where 1400 succeeded. That is a textbook signature. Lowering the MTU appeared to help - one host started responding - so the diagnosis stuck for a while. It was wrong. Others still failed at 1400, and at MSS-clamped settings.

**It looked like IPv6.** Every failing hostname resolved to an AAAA record, and a broken v6 path produces exactly this. Disabling IPv6 changed nothing, and `curl -4` failed identically.

**A packet capture settled it.** The TCP handshake completed, the Client Hello went out at 517 bytes, and the far side **acknowledged it** - proving the packet arrived intact and that MTU was not the problem - and then, 238 milliseconds later, sent a polite `FIN`. Not a reset, not a timeout: a clean close after reading the requested hostname. That is a policy decision by a middlebox, not a network fault. The vendor was filtering egress by SNI, and only an allowlist could fix it.

The general lesson: when TLS fails but plain HTTP to the same host works, capture the packets before changing any interface settings. The shape of the failure names the cause. A FIN after the Client Hello is a filter; a silent stall is MTU; a reset is a firewall.

## Where the sites were actually hosted

A significant mid-course correction: the assumption was that the content had to be migrated to the client's new hosting. It did not. AHG hosts these sites; the new provider held only the DNS. Realising that turned a multi-hour data migration - a 2.5 GB file tree and a database that would not fit through the provider's import tool - into a configuration change on servers already running the current content.

Worth asking early on any engagement of this shape: **who actually hosts this, and what does the other account really control?**

## The database was 96% junk

The main database exported to 1.1 GB, which would not import through the provider's web tool. Two tables held a gigabyte of it, and the cause was **9,429 post revisions** - a page builder stores a complete copy of the page layout in every revision, and five years of editing had accumulated them unchecked.

Deleting revisions and optimising took the compressed dump from 153 MB to **4.1 MB**. Nothing of value was lost: revisions are editing history, not content. A cap of five revisions now prevents it recurring.

This is worth checking on any page-builder site that feels slow or backs up badly. The bloat is invisible in the admin interface and enormous on disk.

## Certificates when the front door only opens one way

With one public IP in front of two servers, and hostname-based routing required, the second server sits behind a reverse proxy on the first. That works, but it creates a redirect loop: the proxy terminates TLS and forwards over plain HTTP, the back-end sees an insecure request and redirects to HTTPS, and round it goes - fifty hops before the client gives up.

The fix is to make the back-end's redirect conditional on the forwarded protocol header rather than unconditional, so it only redirects requests that genuinely arrived over HTTP.

Certificate issuance hit a subtler problem. The standard HTTP validation method requires the certificate authority to connect **inbound** on port 80. It timed out, while ordinary South African traffic to the same URL worked - because the vendor's NAT rule was source-restricted, and the authority's validators are international. **DNS validation** sidesteps this entirely: ownership is proved by publishing TXT records, with no inbound connection at all. One certificate covering all four hostnames was issued that way.

The trade-off: manually-validated certificates **do not auto-renew**. Until the source restriction is lifted, renewal is a diarised manual task rather than a cron job. Verify with a renewal dry-run rather than assuming - the issuance succeeding says nothing about whether renewal will.

## The bug that fixed itself

One page rendered as a wall of unstyled navigation links. Considerable time went into a heading containing a base64 payload pasted from a design tool, on the assumption that this was the cause. It was not.

The real cause was a **major-version mismatch**: the free page builder had been deliberately held back while its pro counterpart moved forward four years, on the reasoning that the add-on suite declared incompatibility with the newer release. The older free version could not render templates the newer pro version had written, so it emitted the raw menu markup instead.

A later blanket update took the free plugin to the matching major version, and the page rendered correctly. The compromise made to reduce risk had been causing the visible breakage all along.

The lesson is not "always update everything" - holding back was a defensible call with a live outage and an add-on suite flagging incompatibility. It is that **a deliberate version mismatch is itself a change**, and its symptoms should be on the suspect list rather than treated as pre-existing damage.

## Follow-ups

- Certificate renewal is manual until the inbound source restriction is lifted; expiry is diarised.
- A licensed add-on remains unactivated because its licence is still bound to the old staging hostname - re-activate against the live domain.
- The archive server runs an end-of-life PHP release.
- An administrator account uses a default username, and a password exposed in an earlier chat still needs rotating.
- Verify the add-on suite's behaviour against the new page-builder major version across listing grids, filters and tabs.
