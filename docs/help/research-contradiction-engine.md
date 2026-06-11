# Contradiction Engine

The Contradiction Engine scans a research project's **claim ledger** for
contradictions that no one is holding in working memory. As a project grows, it
becomes impossible for any one person to remember every claim and how they relate.
The engine surfaces the pairs that disagree so you can reconcile them.

## Where to find it

Open a project, then go to its **Contradiction Engine** report at
`/research/projects/{id}/contradictions`.

## How it works

Press **Run scan** to check the whole ledger. The scan reads only your existing
claims and their evidence; it never changes a claim. It looks for:

- **Opposing status** - two claims about the same topic where one is accepted and
  the other is rejected or contested.
- **Shared source conflict** - one source cited to *support* one claim but to
  *oppose* another. A single source cannot do both, so one reading is wrong.
- **Confidence drop** - a claim that has weakened (revised into a contested or
  rejected status, or whose recorded confidence no longer matches its stated
  confidence level).
- **Definition drift** - the same key term leading two claims that point in
  opposite directions but otherwise share little wording, suggesting the term is
  being used in two different senses.

Each finding shows the two claims side by side with links straight back to the
Claim Ledger, a severity badge (high / medium / low), and its current status.

## Acting on a finding

For every finding you can:

- **Resolve** - you have reconciled the contradiction.
- **Dismiss** - it is not a real contradiction.
- **Reopen** - bring back a resolved or dismissed finding.

Running the scan again is safe: existing findings are refreshed in place rather
than duplicated, and a finding you dismissed or resolved is **not** silently
reopened.

## Optional AI pass

If AI services are enabled on your instance, an **AI deepen (gateway)** button
appears. This is **optional and only runs when you press it** - never
automatically. It sends the project's claims to the AHG AI gateway for a semantic
pass that can catch contradictions the rule-based scan misses. Any contradiction
the AI finds is clearly labelled **AI - via gateway**.

## Empty state

If the ledger is internally consistent, or you have not scanned yet, the report
shows **No contradictions detected** with a button to run a scan.
