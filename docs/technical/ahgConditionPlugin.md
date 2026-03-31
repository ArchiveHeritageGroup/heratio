# ahgConditionPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** Conservation  
**Dependencies:** atom-framework

---

## Overview

Condition assessment and conservation tracking for archival and museum collections. Supports standardized condition reporting, treatment tracking, and environmental monitoring.

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│         condition_assessment            │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK object_id INT                       │──────┐
│    object_type ENUM                     │      │
│                                         │      │
│ -- ASSESSMENT DETAILS --                │      │
│    assessment_date DATE                 │      │
│    assessor_id INT                      │      │
│    assessment_type ENUM                 │      │
│                                         │      │
│ -- CONDITION RATINGS --                 │      │
│    overall_condition ENUM               │      │
│    structural_condition ENUM            │      │
│    surface_condition ENUM               │      │
│    media_condition ENUM                 │      │
│    housing_condition ENUM               │      │
│                                         │      │
│ -- SCORES --                            │      │
│    condition_score INT (1-100)          │      │
│    risk_score INT (1-10)                │      │
│    priority_score INT (1-10)            │      │
│                                         │      │
│ -- DETAILS --                           │      │
│    condition_description TEXT           │      │
│    damage_types JSON                    │      │
│    damage_locations JSON                │      │
│    environmental_factors JSON           │      │
│                                         │      │
│ -- RECOMMENDATIONS --                   │      │
│    treatment_needed TINYINT             │      │
│    treatment_priority ENUM              │      │
│    treatment_recommendations TEXT       │      │
│    handling_instructions TEXT           │      │
│    storage_recommendations TEXT         │      │
│    exhibition_restrictions TEXT         │      │
│                                         │      │
│ -- IMAGES --                            │      │
│    images JSON                          │      │
│                                         │      │
│    next_assessment_date DATE            │      │
│    created_at TIMESTAMP                 │      │
│    updated_at TIMESTAMP                 │      │
└─────────────────────────────────────────┘      │
              │                                   │
              │ 1:N                               │
              ▼                                   │
┌─────────────────────────────────────────┐      │
│       condition_treatment               │      │
├─────────────────────────────────────────┤      │
│ PK id INT                              │      │
│ FK assessment_id INT                   │──────┤
│ FK object_id INT                       │──────┘
│    treatment_date DATE                  │
│    conservator_id INT                   │
│    treatment_type ENUM                  │
│    description TEXT                     │
│    materials_used TEXT                  │
│    time_spent_hours DECIMAL             │
│    cost DECIMAL                         │
│    before_images JSON                   │
│    after_images JSON                    │
│    outcome ENUM                         │
│    follow_up_needed TINYINT             │
│    follow_up_date DATE                  │
│    notes TEXT                           │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│       condition_monitoring              │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK object_id INT                       │
│    monitoring_date DATETIME             │
│    location_id INT                      │
│    temperature DECIMAL                  │
│    humidity DECIMAL                     │
│    light_level DECIMAL                  │
│    uv_level DECIMAL                     │
│    notes TEXT                           │
│    alert_triggered TINYINT              │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘
```

---

## Condition Ratings

| Rating | Score Range | Description |
|--------|-------------|-------------|
| excellent | 90-100 | No visible damage |
| good | 70-89 | Minor wear, stable |
| fair | 50-69 | Moderate damage, needs attention |
| poor | 30-49 | Significant damage |
| critical | 0-29 | Severe damage, urgent treatment |

---

## Damage Types

| Category | Types |
|----------|-------|
| Physical | tear, fold, crease, loss, abrasion |
| Chemical | foxing, acidification, fading, discoloration |
| Biological | mold, insect, rodent |
| Environmental | water, fire, light, pollution |
| Structural | binding, spine, cover, pages |

---

## Service Methods

### ConditionService

```php
namespace ahgConditionPlugin\Service;

class ConditionService
{
    // Assessments
    public function createAssessment(int $objectId, string $type, array $data): int
    public function updateAssessment(int $id, array $data): bool
    public function getAssessment(int $id): ?array
    public function getLatestAssessment(int $objectId): ?array
    public function getAssessmentHistory(int $objectId): Collection
    public function listAssessments(array $filters): Collection
    
    // Scores
    public function calculateConditionScore(array $ratings): int
    public function calculateRiskScore(array $factors): int
    public function calculatePriorityScore(int $condition, int $risk, array $factors): int
    
    // Treatments
    public function createTreatment(int $assessmentId, array $data): int
    public function updateTreatment(int $id, array $data): bool
    public function getTreatmentHistory(int $objectId): Collection
    public function getPendingTreatments(): Collection
    
    // Monitoring
    public function recordReading(int $objectId, array $data): int
    public function getMonitoringHistory(int $objectId, array $filters): Collection
    public function checkThresholds(array $reading): array
    
    // Reports
    public function getConditionSummary(): array
    public function getAtRiskItems(int $threshold = 50): Collection
    public function getDueForAssessment(int $days = 30): Collection
    public function getConservationWorkload(): array
}
```

---

## Assessment Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                  CONDITION ASSESSMENT WORKFLOW                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   ┌─────────────┐                                              │
│   │   Select    │                                              │
│   │   Object    │                                              │
│   └─────────────┘                                              │
│         │                                                       │
│         ▼                                                       │
│   ┌─────────────┐                                              │
│   │   Visual    │  Document current state                      │
│   │ Examination │  Take photographs                            │
│   └─────────────┘                                              │
│         │                                                       │
│         ▼                                                       │
│   ┌─────────────┐                                              │
│   │   Record    │  Condition ratings                           │
│   │   Findings  │  Damage types/locations                      │
│   └─────────────┘                                              │
│         │                                                       │
│         ▼                                                       │
│   ┌─────────────┐                                              │
│   │  Calculate  │  Condition score                             │
│   │   Scores    │  Risk score                                  │
│   │             │  Priority score                              │
│   └─────────────┘                                              │
│         │                                                       │
│         ▼                                                       │
│   ┌─────────────┐                                              │
│   │   Make      │  Treatment needed?                           │
│   │Recommendations                                             │
│   └─────────────┘                                              │
│         │                                                       │
│    ┌────┴────┐                                                 │
│    ▼         ▼                                                 │
│  ┌─────┐  ┌─────────┐                                         │
│  │ No  │  │  Yes    │──▶ Create treatment plan                │
│  └─────┘  └─────────┘                                         │
│    │                                                            │
│    ▼                                                            │
│   ┌─────────────┐                                              │
│   │   Schedule  │                                              │
│   │    Next     │                                              │
│   │ Assessment  │                                              │
│   └─────────────┘                                              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

*Part of the AtoM AHG Framework*
