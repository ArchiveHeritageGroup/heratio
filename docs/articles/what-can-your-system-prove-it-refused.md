# What Can Your System Prove It Refused?

Two projects came across my desk this month. They were built by different people for different reasons, and they stop in exactly the same place.

The first is Heimel, an open-source control plane that went public on 11 September under Apache-2.0. Its model is a gate that sits immediately before effect. An intent arrives, current authority is resolved at that moment rather than inherited from an earlier approval, and a bound permit is either issued or it is not. Among its stated rules: an ALLOW must be tied to attributable evidence before execution authority is released. No recorded authorization, no authorization.

The second is an argument now moving through the AI governance conversation, that observability is not governance. A system that weighs evidence and produces a verdict has controlled nothing if execution proceeds regardless. On that view the maturity question is not what your AI can do, nor even what it can explain. It is what it can refuse to execute.

That is the right question, and it is a considerable improvement on most of what passes for AI governance. I want to put a second question next to it.

What can your system prove it refused, eighteen months from now, to somebody who was not in the room?

## A refusal is evidentially strange

An action leaves the world altered. Money moved, a claim was paid, a file was written, a door opened. Even where the logging is poor, the consequence itself is a kind of witness, and a competent investigator can work backwards from it.

A refusal leaves nothing. That is the whole point of it. Nothing moved, nothing was paid, no door opened, and the world afterwards is indistinguishable from the world in which the request was never made at all. The evidence of a refusal exists only if somebody decided in advance to create it.

Which means restraint is the hardest thing an organisation ever has to demonstrate, and it is the thing most architectures forget to record. We are all quite good at logging what the system did. The record of what it declined to do, on what grounds, against which version of the policy and on whose authority, tends to be an afterthought when it exists at all.

The question always arrives later, and rarely from a friendly direction. A regulator asking why the system kept approving these and stopped approving those. A court asking whether the constraint was actually in force on the day in question or was added afterwards. An internal review asking whether a refusal was principled or was a bug nobody noticed for six weeks. In all three cases, the burden sits on the organisation, and an assurance that the control was operating is not evidence that it operated.

## Fresh at the gate, durable afterwards

Here is the tension that makes this genuinely hard rather than merely neglected.

Authority resolution wants to be fresh. That is the insight both of those projects are built on, and it is correct. An approval granted in March is not a licence to act in September, because the risk, the policy, the delegation and the evidence have all moved since. Every consequential action deserves to be checked against the state of the world as it is now.

Evidence wants the opposite. It has to survive. It must remain intelligible and verifiable long after the policy version that produced it was superseded, after the schema changed, after the signing keys rotated, after the model was retired and quite possibly after the vendor ceased trading. Replaying candidate policy changes against recorded execution frames is an excellent discipline, and it is worth exactly as much as those frames' readability a decade from now.

That second problem is not an authorization problem. It is a preservation problem, and it is one that archives and records professionals have been working on since long before any of this.

We know what happens to evidence over time, because we spend our working lives with the consequences. Formats become unreadable. Custody breaks and cannot be reconstructed. Context detaches from content, leaving a technically perfect record that no longer means anything, because the codes, the version and the authority behind it have been lost. Integrity has to be demonstrable rather than assumed, which is why fixity checking exists. And records routinely outlive the systems that produced them, which is why the ones that survive usefully are the ones that were designed to be readable outside their original application.

None of that is in the gate. All of it determines whether the gate can be proven to have worked.

## Three questions worth asking

If you are buying, building or reviewing one of these architectures, the useful questions are not about the decision logic. They are about what is left behind.

Is a denial recorded with the same rigour as an approval, carrying the same binding to evidence, policy version and authority? If the answer is that denials go to the application log, you have a system that can refuse and cannot prove it.

Can the evidence be read, and its integrity verified, without the system that created it, by someone with no access to your infrastructure? That is the test a court applies, and it is a higher bar than an internal audit trail.

How long is the evidence kept, who decided that, and does it match how long the consequence can be challenged? Retention driven by storage cost rather than by liability is the most common failure here, and the mismatch usually surfaces at the worst possible moment.

## The part worth keeping

The refusal question is a real advance on the state of the field, and the people asking it are the ones to watch.

But a control you cannot evidence afterwards is not a control. It is a belief about how the system behaved, and beliefs do not survive contact with a regulator. Governance is made real at the gate and made provable in the archive, and at the moment the conversation is almost entirely about the gate.
