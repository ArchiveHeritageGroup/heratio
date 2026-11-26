# Document field reader: which documents it reads

The document field reader (Admin > AI > Document fields, `/admin/ai/document-fields/{slug}`, and the automatic SharePoint read) takes invoice, purchase order and delivery note fields from a record's PDF through the AI gateway. Every value it keeps must appear in the document. The archivist reviews the values before they count.

## What it reads (since 26 Nov 2026)

- **Only documents headed as an invoice, purchase order or delivery note.** The heading must be in the first lines of the text: "INVOICE" or "TAX INVOICE", "PURCHASE ORDER", or "DELIVERY NOTE". Other documents are not sent to the model at all. The page says "Not read: the top of the document is not headed as an invoice, purchase order or delivery note". A SharePoint file is still filed by its name, marked Incomplete.
- **A date must be one date.** A period such as "10 to 21 November 2026" is dropped and listed with the other dropped values.
- **An invoice is matched only against an order that has a total.** If several records hold the same order number, the first one that also has a total is used. A record that only quotes the number is passed over. If there is none, the invoice shows "not found".

## Why

On 26 Nov 2026 a contractor induction form quoted order PO-77826 and its supplier. Every value the model gave was in the text, so the form was stored as purchase order PO-77826, with no total. An invoice quoting that order could then have been compared against the form instead of the real order.

A record read wrongly before this change keeps its values until someone rejects them on its Document fields page.

## Code and tests

- `packages/ahg-ai-services/src/Services/DocumentFieldExtractor.php`: `heading()`, `extract()`, `match()`.
- `packages/ahg-ai-services/tests/Unit/DocumentFieldRulesTest.php`, `packages/ahg-ai-services/tests/Feature/DocumentFieldExtractorTest.php`.
