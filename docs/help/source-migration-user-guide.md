> Heratio Help Center article. Category: Integration.

# Migrating a Catalogue from Another System

## What this feature does

Source migration brings a catalogue exported from another archival system into Heratio, and tells you exactly what will be lost or altered before a single record is written. It reads the export, rebuilds the hierarchy from the reference numbers, and produces a data-loss register. A person reviews that register and answers any open questions. Only then can the import run.

Axiell Calm is the one source system with a mapping today. The mapping has been tested with sample files rather than a real Calm export, so always run a preflight on a copy of your own data and read the register closely before committing.

There is no screen for this yet. An administrator with server access runs it from the command line.

## How a migration runs

Every migration passes through the same stages: **preflight**, **acknowledge**, **commit**. Preflight touches only its own tracking table, so you can run it as often as you like. Acknowledgement records who accepted the register and how each open question was answered. Commit creates the records.

All imported records arrive as **Draft**, so nothing becomes public until someone publishes it.

## Step 1: Prepare the export

Export the catalogue as CSV or Excel, including the `RefNo` and `AltRefNo` columns that identify a Calm file. Place it somewhere the server can read and use the full path, because the commit step reads the same file again from that location. Do not edit or move it between preflight and commit.

To check that Heratio recognises the file:

```bash
sudo -u www-data php artisan ahg:source-migrate detect --file=/path/to/export.csv
```

Detection reads CSV-style text only. For an Excel file, skip this step and pass `--mapping=calm` to the preflight.

## Step 2: Run the preflight

```bash
sudo -u www-data php artisan ahg:source-migrate preflight --file=/path/to/export.csv --mapping=calm --repository=123 --parent=456 --out=/path/to/register.json
```

`--repository` and `--parent` are optional ids for the holding repository and for the description the import should sit beneath. With no parent, records go in at the top level. `--out` saves the full register as JSON, which is worth keeping with your migration file.

The command prints a run number and a table of every column that is not a clean one-to-one mapping. Read the **Action** column carefully:

| Action | Meaning |
| --- | --- |
| dropped | The values will not be imported. |
| flattened | The value is kept, but loses some structure, such as a closure date that becomes text in the access conditions. |
| folded | Several source columns share one Heratio field, and each value keeps its source label. |

The output also names any record types left out, such as Calm persons, places or accessions rows, and it lists open decisions and blockers. The JSON register adds detail the screen omits, including how many rows were shorter than the headings and how many dates could be normalised.

To view a run again later:

```bash
sudo -u www-data php artisan ahg:source-migrate show --run=12
```

## Step 3: Clear any blockers

A run with blockers has the status **rejected** and cannot go further. Fix the cause in the source file or in Heratio, then run a new preflight.

| Blocker | What to do |
| --- | --- |
| `missing_reference_column`, `duplicate_column` | Correct the column headings in the export. |
| `malformed_reference`, `duplicate_reference`, `self_reference`, `cycle` | Correct the reference or parent values. |
| `malformed_row` | The row has more cells than headings. Quote the value containing the delimiter. |
| `unknown_record_type`, `unmapped_level` | Add the value to the Calm mappings in the Dropdown Manager. |
| `level_term_missing` | Add the matching term to the levels of description. |
| `authority_type_conflict` | One name is used as both a person and a corporate body. Correct the source. |
| `ambiguous_authority` | Merge or rename the competing authority records, or fix the spelling. |
| `parent_not_in_tree`, `repository_not_found` | Check the id you passed. |
| `already_imported` | An earlier committed run already holds this reference. |

## Step 4: Acknowledge the register

The person acknowledging must have import rights over the target repository and parent. Answer every open decision on the command line:

```bash
sudo -u www-data php artisan ahg:source-migrate acknowledge --run=12 --user=7 --decide=level_piece=fold_to_item --decide=date_policy=keep_both
```

`--user` is that person's user id. There are two possible decisions, and each appears only if your file needs it.

For `level_piece`, which arises because Calm's Piece level has no direct equivalent, `fold_to_item` imports those rows as Item and keeps the original level as a property. `create_piece_term` adds a Piece term to your levels of description instead.

For `date_policy`, `keep_both` keeps the date as written alongside normalised start and end dates, which makes date searching work. `display_only` keeps just the date as written.

## Step 5: Commit

```bash
sudo -u www-data php artisan ahg:source-migrate commit --run=12
```

Before writing, Heratio re-reads the file and rebuilds the register. If the file, the mapping or the relevant catalogue data has changed since acknowledgement, the commit is refused and you must run a new preflight. The load is a single transaction, so a failure leaves no partial import behind.

When it finishes, review the new Draft records and publish them in the normal way. Any hierarchy level the export implied but did not contain is created for you and carries a general note saying so.

## Troubleshooting

"No known source matches this file" means the required columns were not found, or the file is Excel. Check the headings, or pass `--mapping=calm`.

"The file, mapping or catalogue changed since the register was acknowledged" is a safeguard working as designed. Run a new preflight, review it and acknowledge the new run.

"This user may not acknowledge a migration" means the user id is missing or wrong, or that person lacks import rights on the repository or parent.

A run marked **failed** cannot be resumed. Use `show` to confirm the run, correct the cause and start again with a new preflight.

If committed records do not appear in search, an administrator can rebuild the description index with `sudo -u www-data php artisan ahg:es-reindex --index=informationobject`. Remember too that they are Drafts, so anonymous visitors will not see them.
