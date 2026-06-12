# Research Team & Collaborators register

The Research Team & Collaborators register is part of the Research Operating System. For each research project it records who is on the team and in what capacity - each contributor's name, role, affiliation, email and ORCID iD - so a project's people are documented in one place, alongside its Data Management Plan, outputs, ethics record and funding.

This is the broader contributor list: co-investigators, students, partners, technicians and external collaborators. It documents everyone who contributes to the work, and it sits alongside - it does not replace - the project's single owner that the research portal already carries.

## What it records

Each team member on a project captures:

- Name - the contributor's name.
- Role - principal investigator, co-investigator, researcher, student, advisor, partner, technician, or other. The role list is informed by the international CRediT (Contributor Roles Taxonomy) and common project-team roles.
- Affiliation - the institution or organisation, recorded as free text.
- Email - a contact email.
- ORCID iD - an international persistent identifier for a researcher.
- Project lead - a flag that highlights a person (for example the principal investigator) as a lead.
- Contribution note - a free-text description of what the person contributed.
- Joined and left dates, and an involvement status (active, inactive, or former).

The role and status choices are drawn from the Dropdown Manager, so an administrator can extend them without code changes. No country or institution is assumed or defaulted - the register works across jurisdictions, and affiliation is free text rather than a fixed list.

## ORCID iDs

An ORCID iD is an international, registry-neutral persistent identifier for a researcher. You enter the iD only - the 16-character form `0000-0000-0000-0000`, where the last character may be a digit or the letter X. You can paste the full `https://orcid.org/...` link and the register will keep just the iD.

The iD is stored in its bare form and rendered as a link to `https://orcid.org/{orcid}`, so a reader can open the person's ORCID record. The register never looks an ORCID up online or fetches anything from orcid.org - it only checks that what you entered has the correct format. A blank ORCID is allowed; a value that is present must be a valid iD.

## Roles and contributions

The role is a single value chosen from the dropdown, informed by the international CRediT contributor-roles taxonomy. The contribution note is free text, so you can describe a person's contribution in whatever detail you need - and, if you wish, in CRediT terms such as conceptualisation, methodology, software, validation, investigation, writing, or supervision. CRediT is a recognised reference here; the register does not force its categories.

## Leads

Any member can be flagged as a project lead, which highlights them in the team summary. A person whose role is principal investigator is always treated as a lead.

## Summary and export

The project summary shows the total number of team members, the number who are currently active, counts by role and by status, and the list of leads (highlighted). A machine-readable JSON export of a project's team is available - each member with their role (code and human label), affiliation, email, ORCID as both the bare iD and a resolvable `https://orcid.org/...` URL, lead flag, contribution note, involvement period and status, plus a summary block with the counts by role and status and the leads.

## Notes

- Entries are scoped to a project and to the researcher; you manage the team of projects you belong to.
- The register is read and written only through its own table - it does not change any catalogue record or the project owner.
- It is jurisdiction-neutral; no country, institution or registry is assumed or defaulted. ORCID and CRediT are international references.
