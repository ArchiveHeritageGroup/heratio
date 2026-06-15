> Heratio Help Center article. Category: Research / Source Assessment.

# Trust Score

## A Guide for Researchers and Staff

---

## What is the Trust Score?

The Trust Score is a single number from 0 to 100 that summarises how reliable a source is. It is built from three things you can reason about: what kind of source it is, how complete the surviving record is, and what automated quality checks have found. The score is meant to help researchers weigh evidence, not to replace their own judgement.

Open it from any archival record:

```
Record page
   |
   v
Research Tools (sidebar)
   |
   v
Trust Score
```

The page is reached at `/research/{slug}`, where `{slug}` is the record's address.

---

## How the score is calculated

The score is the sum of three weighted components, then clamped to the 0 to 100 range:

```
Trust Score = Source Type + Completeness + Quality Metrics
              (max 40)      (max 30)        (max 30)
```

### Source Type (up to 40 points)

How close the source is to the events it documents.

| Source type | Points |
|-------------|--------|
| Primary    | 40 |
| Secondary  | 25 |
| Tertiary   | 10 |

### Completeness (up to 30 points)

How much of the original record survives.

| Completeness | Points |
|--------------|--------|
| Complete       | 30 |
| Partial        | 20 |
| Missing pages  | 15 |
| Redacted       | 15 |
| Fragment       | 10 |

### Quality Metrics (up to 30 points)

Automated quality signals recorded against the record (for example image legibility or transcription confidence). Each metric is a value between 0 and 1. The metrics are averaged, the average is multiplied by 30, and the result is clamped to the 0 to 30 range:

```
Quality Metrics = average(metric values) x 30
```

If no quality metrics exist yet, this component is 0 and the score rests on source type and completeness alone.

---

## Reading the score

The page shows the total in a coloured badge and breaks it into three bars - Source Quality, Completeness, and Verification (the quality-metric component) - plus a table of the individual quality metrics with their values and weights.

| Band | Score | Meaning |
|------|-------|---------|
| High trust     | 80 to 100 | Strong, well-evidenced source |
| Moderate trust | 50 to 79  | Usable, but check the gaps |
| Low trust      | 0 to 49   | Treat with caution; corroborate |

---

## Setting the inputs

The source type and completeness come from a researcher's **Source Assessment** for the record. To record or change an assessment, open the assessment form at `/research/assessment/{slug}`. There you set the source type, the source form, the completeness, and optional rationale and bias-context notes. The Trust Score recalculates from those values the next time it is viewed.

A list of all assessments across the collection is available at `/research/assessments`.

The quality metrics are written by other parts of the platform (for example image-quality or transcription services) and stored against the record; they appear in the Verification bar automatically as they are produced.

---

## Frequently asked questions

**Does the score change a record?** No. It is a read-only calculation over the assessment and any quality metrics.

**Why is my Verification bar empty?** No quality metrics have been recorded for that record yet. The score still works from source type and completeness.

**Can two records have the same score for different reasons?** Yes. Always read the three bars, not just the total - a high source-type score can offset a poor completeness score.

---

## References

- Source: `packages/ahg-research/` and `packages/ahg-information-object-manage/`
- Stored in: `research_source_assessment`, `research_quality_metric`
