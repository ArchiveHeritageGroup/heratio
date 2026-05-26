# Article 30 - Record of Processing Activities

Article 30 of the EU General Data Protection Regulation (and its UK GDPR analogue) requires every controller to maintain a written register of all processing activities that involve personal data. The register must list, for each activity, the purpose, lawful basis, categories of data and subjects, recipients, retention period, security measures, and any transfers outside the EEA together with safeguards used.

Heratio ships a regulator-aligned register under **Admin -> Privacy -> Article 30**. Five default activities are auto-seeded:

1. User authentication
2. Archival cataloguing
3. AI inference logging
4. Audit trail
5. Email notifications

Each row is editable - add your organisation's specifics (DPO contact, exact retention policy, security controls) and create new rows for any additional processing you carry out (newsletter, fundraising, donor management, etc.).

## Lawful basis

Pick one of the six GDPR Article 6 bases:

- `consent` - the data subject has explicitly opted in
- `contract` - processing is necessary to perform a contract with the subject
- `legal_obligation` - processing is required by law
- `vital_interests` - processing protects a vital interest of the subject or another person
- `public_task` - processing is in the public interest or in exercise of official authority (commonly used for public archives)
- `legitimate_interests` - the controller has a documented legitimate interest that is not overridden by the subject's rights

## Cross-border transfers

If the activity transfers personal data outside the EEA, tick **Transfers personal data outside the EEA?** and record the safeguards used: standard contractual clauses (SCC), binding corporate rules (BCR), an adequacy decision, or a derogation.

## Export

Click **Export** to download the register in three formats:

- **JSON** - the full snapshot including `controller`, `generated_at` and `activity_count`. Best for regulator submissions and ingestion into compliance dashboards.
- **CSV** - one row per activity. Best for spreadsheets and procurement reviews.
- **Markdown** - a human-readable register suitable for board reports and DPIA appendices.

The same exports are available from the CLI:

```
php artisan privacy:article-30-export --format=json
php artisan privacy:article-30-export --format=csv --out=art30.csv
php artisan privacy:article-30-export --format=markdown --out=art30.md
```

## Editing default activities

The five seed rows are inserted with `INSERT IGNORE` on the unique `name` column. Editing a default activity in the admin UI does not affect the seed - your edits persist. To add another seed row, simply create a new activity.

## Deactivating an activity

Deleting an activity from the register marks it as inactive rather than removing the row. This preserves the historical record - regulators can ask "what processing activities did you have on 1 March last year?" and the auto-fill `updated_at` column tells the story.
