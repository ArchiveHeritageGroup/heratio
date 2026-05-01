# OpenRiC Website Critique and Action List

## Overview

This document captures a structured critique of **openric.org** based on a live review of the public site and linked repository, together with a practical action list for improvement.

Overall assessment: **the site is now credible, technically serious, and significantly stronger than an earlier concept-stage presentation**. It presents OpenRiC as an **implementation-neutral specification** with a supporting ecosystem that includes specification pages, architecture, guides, live demo surfaces, an API explorer, and a conformance suite.

---

## Overall Assessment

OpenRiC is now positioned clearly as an **open specification** for mapping archival description into RiC and serving it over HTTP, rather than as a vague product concept. That is a strong strategic improvement.

The website communicates a technically coherent ecosystem consisting of:

- The specification.
- Supporting documentation.
- A reference API.
- Demo and browse surfaces.
- An API explorer.
- A conformance suite.
- Architecture guidance.
- Related implementation context.

This gives the initiative real technical weight.

The main remaining challenge is no longer whether the project appears substantial. The challenge is now **message calibration and user journey design**:
- Make the version story consistent.
- Make the OpenRiC / Heratio boundary simpler.
- Make the site easier for first-time visitors who are not already immersed in RiC, linked data, and API terminology.

---

## What Is Working Well

### 1. Stronger positioning

The site now explains that OpenRiC is **not a product**, but an **open specification**. That distinction is one of the strongest elements on the site because it reduces confusion and gives the project standards credibility.

### 2. Real ecosystem presentation

The site no longer relies only on descriptive language. It presents actual ecosystem components such as the spec, architecture, guides, viewer/demo surfaces, API explorer, and conformance tooling. This makes the initiative feel tangible.

### 3. Technical seriousness

The tone and structure are appropriate for a standards-oriented and implementation-facing initiative. References to JSON Schema, SHACL, HTTP API behaviour, conformance, and versioning contribute to trust among technical audiences.

### 4. Better than a brochure site

The live surfaces make the project feel more mature than a simple marketing page. That is a major strength and should be preserved.

---

## Main Issues Identified

### 1. Homepage density is still too high for first-time visitors

The homepage is technically strong, but it remains heavy for readers who are not already comfortable with RiC, JSON-LD, SHACL, conformance vocabulary, and API-centred architecture.

#### Why this matters

A standards-oriented audience may understand it immediately, but:
- archivists,
- institutional decision-makers,
- funders,
- board members,
- and non-technical partners

may struggle to understand the practical value quickly.

#### Recommendation

Add a plain-language explanatory block directly beneath the hero section.

Suggested direction:

> OpenRiC is an open specification that helps archival systems publish, exchange, validate, and explore RiC-based archival data in a consistent way.

Then add entry paths such as:
- I am an archivist.
- I am a developer.
- I am evaluating the specification.
- I am looking for implementation guidance.

---

### 2. Version messaging is inconsistent

The visible versioning signals appear to conflict across the site. If one area indicates v0.1.0 while another indicates v0.2.0, users will question which version is canonical.

#### Why this matters

Version inconsistency creates immediate trust friction, especially for a project presenting itself as a specification.

#### Recommendation

Decide on a single public versioning story and apply it consistently across:
- global header or badge,
- homepage hero,
- specification page,
- roadmap or release pages,
- repository release metadata.

If there is a distinction between:
- stable specification version, and
- working or candidate next version,

state that explicitly.

Example approach:

- Stable specification: v0.1.0.
- Current working draft: v0.2.0 candidate.

or

- Current public release: v0.2.0.
- Prior release: v0.1.0.

But do not allow mixed signals without explanation.

---

### 3. OpenRiC and Heratio still need a simpler boundary explanation

The relationship exists on the site, but it is not simplified enough into one repeatable sentence. Readers may still confuse:
- OpenRiC the specification,
- OpenRiC the surrounding ecosystem,
- the reference API,
- and Heratio the operational platform.

#### Why this matters

If users do not understand the boundary, they may misclassify OpenRiC as either:
- merely a feature of Heratio, or
- an entire operational platform by itself.

#### Recommendation

Add a compact comparison panel on the homepage or architecture page:

- **OpenRiC** - the open specification and interoperability contract.
- **Reference API** - an implementation of the specification.
- **Viewer / tools** - client applications that consume the specification.
- **Heratio** - an operational GLAM platform that can publish to and use OpenRiC.

This should be short, visual, and repeated consistently.

---

### 4. Public credibility signals need strengthening beyond the website

The website looks more mature than the surrounding public repository footprint.

#### Why this matters

Technical evaluators often move quickly from the site to the repository. If the repository looks sparse or under-signalled, confidence drops.

#### Recommendation

Improve public repository hygiene:
- Add a concise repository description.
- Add the project website.
- Add GitHub topics.
- Publish formal GitHub releases aligned to version tags.
- Add a clearer README opening.
- Surface example implementation or sample data.
- Use pinned repositories if the ecosystem spans multiple repos.

---

### 5. The tools are present, but the evaluation journey is not explicit enough

A visitor can access guides, browse/demo, API explorer, conformance tooling, and architecture pages. That is excellent. However, the site still expects users to infer their own path.

#### Why this matters

A strong project can still underperform if the review path is unclear.

#### Recommendation

Create a visible “Evaluate OpenRiC” flow:

1. Read the 2-minute overview.
2. Open the live browse demo.
3. View a graph or entity example.
4. Inspect the API in the explorer.
5. Run or review the conformance probe.
6. Read the getting-started guide.

This turns the site into a guided evaluation experience rather than a collection of pages.

---

## Priority Assessment

### High priority

1. Resolve version inconsistency across the site.
2. Add a plain-language explanation near the top of the homepage.
3. Add a simple and visual OpenRiC vs Heratio distinction.
4. Improve public release and repository signalling.

### Medium priority

5. Add role-based or audience-based pathways.
6. Add stronger proof-of-implementation content.
7. Refine top-level navigation for first-time visitors.

### Lower priority

8. Add visible governance and contribution structure.
9. Add an executive or institutional summary page.

---

## Detailed Action List

## Action 1: Fix version consistency

**Priority:** High  
**Pages affected:** Header, homepage, spec page, roadmap/release pages, repository

**Exact action:**
- Audit every visible version reference.
- Choose one canonical public version state.
- Update all badges, page titles, and references to match.
- If needed, distinguish between stable release and working draft.

**Success criteria:**
- A first-time visitor can tell immediately what the current version is.
- There are no conflicting visible version numbers across core pages.

---

## Action 2: Add a plain-language value statement under the homepage hero

**Priority:** High  
**Pages affected:** Homepage

**Exact action:**
Add a short explanation directly after the hero that answers:
- what OpenRiC is,
- why it matters,
- and who it is for.

**Suggested wording direction:**
OpenRiC is an open specification that helps archival systems publish, exchange, validate, and explore RiC-based archival data consistently.

**Success criteria:**
- A non-technical visitor can understand the core purpose in under 20 seconds.
- The first screen no longer depends entirely on specialist terminology.

---

## Action 3: Add an “OpenRiC / Reference API / Tools / Heratio” comparison block

**Priority:** High  
**Pages affected:** Homepage, architecture page

**Exact action:**
Create a simple comparison section with four labelled boxes or cards.

**Success criteria:**
- Users stop confusing the specification with the implementation or platform.
- The relationship between OpenRiC and Heratio becomes immediately legible.

---

## Action 4: Publish clearer release signals

**Priority:** High  
**Pages affected:** Repository, homepage, spec page

**Exact action:**
- Publish or formalise releases in the repository.
- Align tags and public release notes.
- Add release references to the site.
- Ensure the spec version is traceable and verifiable.

**Success criteria:**
- A reviewer can confirm the release state without ambiguity.
- Website and repository tell the same version story.

---

## Action 5: Build a guided evaluation journey

**Priority:** Medium  
**Pages affected:** Homepage, guides page

**Exact action:**
Add a visible section called something like:
- Start here.
- Evaluate OpenRiC.
- Explore the ecosystem.

Include a task-led sequence linking to:
- overview,
- live demo,
- graph or browse surface,
- API explorer,
- conformance suite,
- getting-started guide.

**Success criteria:**
- A new visitor has a clear review path.
- The site behaves more like a guided product/spec walkthrough.

---

## Action 6: Add audience-specific pathways

**Priority:** Medium  
**Pages affected:** Homepage, guides page

**Exact action:**
Create entry points such as:
- For archivists.
- For developers.
- For institutions.
- For standards and research users.

Each should lead to a short page or anchored section tailored to that audience.

**Success criteria:**
- Different user groups can reach relevant content faster.
- Bounce risk is reduced for non-technical users.

---

## Action 7: Add proof-of-implementation material

**Priority:** Medium  
**Pages affected:** Homepage, demo page, guides page, repository

**Exact action:**
Add:
- screenshots,
- example datasets,
- example outputs,
- entity examples,
- sample mappings,
- short end-to-end use case.

**Success criteria:**
- The project shows not just principles, but actual working results.
- Institutional reviewers can see evidence of practical implementation.

---

## Action 8: Improve navigation wording

**Priority:** Medium  
**Pages affected:** Global navigation

**Exact action:**
Review whether terms like:
- Spec,
- Architecture,
- Guides

are enough for all users, or whether a more welcoming top-level entry such as “Start here” should be added.

**Success criteria:**
- New users find the right page faster.
- Navigation supports both specialists and first-time visitors.

---

## Action 9: Add governance and contribution visibility

**Priority:** Low  
**Pages affected:** New governance page, repository, footer

**Exact action:**
Add a governance or contribution page describing:
- who stewards the initiative,
- how changes are proposed,
- how implementations contribute feedback,
- how external editors or collaborators can participate.

**Success criteria:**
- The project appears more open, structured, and durable.
- External collaborators can understand how to engage.

---

## Action 10: Add a short executive-facing page

**Priority:** Low  
**Pages affected:** New overview page or homepage-linked page

**Exact action:**
Create a concise page for decision-makers that explains:
- what OpenRiC is,
- why institutions may care,
- interoperability value,
- implementation flexibility,
- relation to operational platforms,
- standards and governance benefits.

**Success criteria:**
- Institutional and procurement readers can understand the initiative quickly.
- The site serves both technical and executive audiences.

---

## Suggested Immediate Next Sprint

A practical first sprint could focus on only the highest-value items:

1. Fix all version inconsistencies.
2. Add the plain-language explainer under the hero.
3. Add the OpenRiC vs Heratio comparison block.
4. Add a guided “Start here / Evaluate OpenRiC” section.
5. Improve repository release signalling.

These five changes would likely produce the fastest visible improvement in trust, clarity, and usability.

---

## Final Conclusion

OpenRiC now presents as a serious and credible specialist initiative. The core architecture and standards positioning are strong. The website has moved beyond idea-stage messaging and now shows a real ecosystem.

The main remaining work is to make that strength easier to understand:
- faster for first-time visitors,
- cleaner in version presentation,
- clearer in relation to Heratio,
- and stronger in public proof signals.

With those refinements, the site would project substantially greater maturity without requiring a major structural rebuild.
