# RiC-O Occupation

## What it is

An **Occupation** (`rico:Occupation`) is a role, profession, or position held by
an actor over a time-span. It maps to ISAAR(CPF) section 5.2.6 and is one of the
agent-related entities defined by Records in Contexts (RiC-O).

In Heratio, occupations are stored in the `ric_occupation` table and linked back
to the actor that holds them. When an agent is serialized as RiC-O JSON-LD the
occupations are emitted as `rico:hasOrHadOccupation` nodes.

## When to add an occupation vs an event

- **Use an Occupation** when you want to record *what role someone held* and
  *for how long*. Occupations describe an enduring relationship between an actor
  and a function/position (e.g. "Conservator at the National Archives,
  1998–2014").

- **Use an Activity / Event** when you want to record a *specific dated action*
  carried out by an actor. Activities describe discrete things that happened
  (e.g. "Restored the 1899 Mafeking diary on 12 March 2002").

A useful rule of thumb: if it would appear on a CV as a job title, it's an
**Occupation**. If it would appear on a CV as a project, exhibit, or
publication, it's an **Activity**.

## Sample uses

1. **Conservator at an institution.** Title "Senior Conservator", start
   1998-04-01, end 2014-11-30, *Currently held* unchecked. Description: "Lead
   paper conservator responsible for the colonial-era manuscript collection."

2. **Curator (ongoing).** Title "Curator of Photography", start 2020-01-15,
   no end date, *Currently held* checked. Linked to the curator's actor record
   so the RiC-O graph exposes the current role to federated linked-data
   harvesters.

## Where to find it

Admin → RiC → Occupations (`/admin/ric/occupations`).
