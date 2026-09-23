# Refusal, evidence, and the Heimel assessment (September 2026)

**Summary.** AI governance converged during 2026 on the question of what a system can refuse to execute. AHG's public position, published 23 September 2026, is that the question stops one step short: a refusal leaves no trace by its nature, so proving restraint later is the harder evidentiary problem, and it is a preservation problem rather than an authorization one. This note records the published article, the assessment of the Heimel project that it cites, and the LinkedIn publishing mechanics learned in the process.

## The article

*What can your system prove it refused?* - published 23 September 2026 at `https://heratio.org/articles/what-can-your-system-prove-it-refused`, and as a LinkedIn post. The `heratio.theahg.co.za` host 301-redirects to the canonical heratio.org address.

The argument in brief:

- The field's maturity question has moved from what an AI can do, or explain, to what it can refuse to execute. That is an advance. Observability without enforcement controls nothing.
- A refusal is evidentially strange. An action alters the world and an investigator can work backwards from the consequence; a refusal leaves a world indistinguishable from one in which the request was never made. Evidence of restraint exists only if somebody decided in advance to create it.
- Authority resolution wants to be fresh, because an approval granted in March is not a licence to act in September. Evidence wants the opposite: it must stay intelligible and verifiable after the policy version is superseded, the schema changes, the signing keys rotate, the model is retired and possibly the vendor fails.
- That second requirement is preservation work, and archives have been doing it far longer than this field has existed. Formats become unreadable, custody breaks, context detaches from content and leaves a technically perfect record that no longer means anything.
- Three questions for anyone buying or building one of these architectures: is a denial recorded as rigorously as an approval, bound to the same evidence, policy version and authority; can the evidence be read and its integrity verified without the system that created it; is it kept as long as the consequence can be challenged, and who decided that number.
- Closing position: a control you cannot evidence afterwards is not a control, it is a belief about how the system behaved. Governance is made real at the gate and provable in the archive.

Draft source in the Heratio repository at `docs/articles/what-can-your-system-prove-it-refused.md`.

## Heimel - assessed 23 September 2026

`https://github.com/Heimel-open/Heimel`, Apache-2.0, created 11 September 2026, sole author. Positions itself as authority infrastructure for consequential authority: a gate immediately before effect that resolves authority fresh and issues a bound permit. Two of its stated rules are directly relevant to recordkeeping - an ALLOW must be bound to attributable evidence before execution authority is released, and decision evidence is distinct from effect evidence.

Assessment, measured rather than read off the README:

- **The kernel is real.** `packages/kernel`, published as `heimel-kernel` 0.1.0, is 4,622 lines of pydantic-typed Python with a fail-closed discipline: candidates, policies and assessments are digest-sealed on entry and a mismatch raises rather than degrades. Cloned, installed into a clean virtualenv from source, and its own suite run: 117 tests pass in 6.5 seconds with nothing stubbed.
- **The rest is bulk.** Roughly 258,000 lines of Python across the repository, with 1,473 Python and 671 markdown files under `runtime/` alone, produced in 160 commits over twelve days by one author. The structural tell is a 60,523-byte module existing byte-identical in two separate packages.
- **No traction.** Six stars, two forks, one watcher. All seven open issues were opened by the author, four titled as accidental connector tests. One discussion, no replies.
- **Register.** The repository frames itself as a public attempt to kill its own core claim, with files inviting attack and directories named for certification and for a universe. Legitimate as a trial format, self-mythologising as description. Do not repeat its self-description unchecked.

**Position taken:** cite it, do not build on it. It is a public, licensed, verifiable instance of an independent party arriving at the same gate and stating the recordkeeping principle out loud, which makes it usable evidence that the field is converging there and leaving the preservation half open. As a dependency it would be reckless - twelve days old, one author, enormous surface, no stability commitment. Cite the code and the claims at a fixed commit rather than the project's future.

## LinkedIn publishing mechanics

Learned while publishing the article, and reusable for any AHG post.

- The feed post box caps at **3,000 characters**. The article editor caps at roughly **110,000**, so a thousand-word piece fits there untrimmed and only the feed post needs cutting.
- **Unicode "bold" and "italic" letters are not formatting.** They are mathematical-alphabet codepoints above the Basic Multilingual Plane, and the counter charges **two characters for each one**. A post measuring 2,915 characters of actual words counted as 3,297 once styled, which is the whole of a 300-character overage.
- Those characters also read as loose mathematical symbols to screen readers, and do not match text search or copy cleanly. On a post about evidence remaining readable, that is a poor look. Style headings sparingly if at all, and never style the substantive sentences.
- Practical target: draft to about 2,800 plain characters, leaving room for the article URL (a heratio.org article link runs about 62 characters) and two hashtags.
- Check that the article URL resolves before publishing a post that leads with it. The article page and the post were prepared in parallel here, and the link 404ed until the page went live.
