> Heratio Help Center article. Category: User Guide.

# Choosing a description standard

An archival description in Heratio does not have to be ISAD(G). The same
record can be catalogued - and re-catalogued - in the standard that suits the
material: **ISAD(G)**, **Records in Contexts (RiC-O)**, **DACS**, **RAD**,
**MODS**, or **Dublin Core**. You pick the standard on the form itself, the
fields reshape to match, and the record keeps everything it already had.

Older releases only let you export a record as RAD or DACS, or flip a database
column by hand. That is gone. Selecting a standard is now a normal part of
cataloguing, on both the Add form and the edit form.

---

## Picking the standard while you catalogue

Open **Add** (`/informationobject/add`) or edit any description. At the top of
the form is an **Administration area**, and its first field is **Description
standard**. Choose one of the six standards there.

The moment you change it, the field set below reshapes - no page reload. RiC-O
frames the whole record as `rico:` elements; MODS shows MODS elements; DACS and
RAD show their national fields; Dublin Core narrows to the fifteen DC terms. A
line under the selector says exactly this, so nobody has to guess what the
dropdown does.

ISAD(G) is the default. Whatever you pick, **Save** writes through that
standard's own handler, and the record remembers the choice (its
`source_standard` and display standard). Come back to edit it later and you land
on the same standard, with the fields you filled in.

Nothing is thrown away when you switch. A value that has a home in both
standards carries across; anything specific to the standard you left simply
stops showing. The description underneath is still the one canonical record.

---

## The Administration area

Three things live here and stay put while the description fields swap around
them:

| Field | What it does |
| --- | --- |
| **Description standard** | The selector described above - the single source of the record's standard. |
| **Publication status** | Draft or Published. Drafts stay hidden from anonymous visitors. |
| **Source language** | The record's language of description. |

Because this block sits outside the part that swaps, your publication status and
the standard selector never disappear when you change standards.

---

## Level of description is scoped to the sector

The **Level of description** dropdown only offers the levels that make sense for
the record's sector. An archival description gives you Fonds, Subfonds,
Collection, Series, Subseries, File, Item, Part and Record group. A museum
object, a library item, a gallery artwork or a DAM asset each get their own
list instead. You will not see "Fonds" offered on a museum object or "Specimen"
on an archival series.

This holds whichever standard you switch to - RiC-O, DACS and RAD all inherit
the same sector filter as plain ISAD(G).

---

## Opening the form already set to a standard

You can link straight to a pre-set form:

```
/informationobject/add?standard=ric
/informationobject/add?standard=dacs&parent=1234
```

- `standard=` takes a **code** - `isad`, `ric`, `dacs`, `rad`, `mods`, `dc`.
  Codes are portable: the same link works on every Heratio server.
- `parent=` (or `parent_id=`) makes the new description a child of that record.

A numeric `standardId=` is also accepted, for links arriving from another
system. Prefer the code form for anything you build yourself - internal term ids
differ from one database to the next, so a numeric link is not portable.

When the form opens from one of these links, the correct field set is already
loaded. The same is true after a failed save: if validation sends you back, the
standard you chose is still selected and its fields are still shown.

---

## Creating a child in a chosen standard

On any archival record page, the **Add new** button carries a caret (the small
arrow beside it). Open it and you get **Add child description in** followed by
the six standards, with **RiC-O** first. Pick one and the new description opens
in that standard, already parented to the record you were looking at.

The plain **Add new** button still makes an ordinary ISAD(G) child, so the quick
path is unchanged.

---

## The six standards at a glance

| Standard | Origin | Good for |
| --- | --- | --- |
| **ISAD(G)** | International Council on Archives | General multilevel archival description; the default. |
| **RiC-O 1.0** | International Council on Archives | Relationship-rich description - agents, activities, instantiations and events as first-class things. See the RiC user guide. |
| **DACS** | United States | US archival practice. |
| **RAD** | Canada | Canadian archival practice. |
| **MODS 3.x** | Library of Congress | Bibliographic / library-leaning material. |
| **Dublin Core** | DCMI | Simple, fifteen-element records and lightweight interchange. |

Each standard is delivered as its own package, so a client install can ship
with only the ones it needs. If a package is absent, its option simply does not
appear and the form falls back to ISAD(G) - nothing breaks.

---

## Exporting and importing a single standard

Cataloguing in a standard and exchanging that standard as a file are separate
jobs. Exports live on the metadata-export dashboard at
`/admin/metadata-export/index`: pick the standard, pick the record, download.

```
GET /admin/metadata-export/download/{format}?io={information_object_id}
```

`{format}` is `dcterms`, `mods`, `rad`, `dacs`, or `ric` (RiC-O as Turtle;
`ric.rdf` for RDF/XML). The existing Dublin Core, EAD, EAC and MARC exports are
unchanged.

RAD and DACS XML can be brought back in:

```
POST /admin/metadata-export/import/{format}
```

with an uploaded `xml_file` or an `xml` body field. By default you get a JSON
preview - every parsed record, the information_object it matched (or null), and
any warnings. Add `dryRun=0` (or `commit=1`) to write the parsed values into the
standard's sidecar. Matching is by identifier: RAD reads `<identifier>`, DACS
tries `<recordIdentifier>` then `<referenceCode>`. Records that match nothing
are reported and left uncommitted on purpose - RAD/DACS import enriches records
that already exist, it does not bulk-load new ones. Use MARC or EAD for that.

---

## See also

- **Records in Contexts (RiC)** - cataloguing in RiC-O, the Instantiation and
  Event editors, and the per-record RiC view.
- **Terms, Taxonomies & SKOS** - where the Level-of-description and other
  controlled vocabularies come from.
