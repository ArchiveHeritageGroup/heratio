# ahgLoanPlugin - Technical Documentation

## Overview

The `ahgLoanPlugin` is a shared loan management system for all GLAM (Galleries, Libraries, Archives, Museums) sectors. It provides a unified codebase with sector-specific adapters for customized behavior.

### Key Characteristics

- **Architecture**: Adapter pattern for sector-specific behavior
- **Database**: Laravel Query Builder (Illuminate\Database)
- **Namespace**: `AhgLoan\`
- **Dependencies**: atom-framework
- **Version**: 1.0.0

---

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           ahgLoanPlugin                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │                        Presentation Layer                            │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │   │
│  │  │   loan   │  │loanDash- │  │loanCalen-│  │   loanReports    │   │   │
│  │  │  module  │  │  board   │  │   dar    │  │     module       │   │   │
│  │  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────────┬─────────┘   │   │
│  └───────┼──────────────┼────────────┼─────────────────┼─────────────┘   │
│          │              │            │                 │                  │
│  ┌───────┴──────────────┴────────────┴─────────────────┴─────────────┐   │
│  │                         Service Layer                              │   │
│  │  ┌────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │   │
│  │  │  LoanService   │  │ConditionReport  │  │ FacilityReport   │  │   │
│  │  │    (Core)      │  │    Service       │  │    Service       │  │   │
│  │  └───────┬────────┘  └──────────────────┘  └──────────────────┘  │   │
│  │          │                                                         │   │
│  │  ┌───────┴────────┐  ┌──────────────────┐  ┌──────────────────┐  │   │
│  │  │CourierManage-  │  │  Notification    │  │   Calendar       │  │   │
│  │  │  mentService   │  │    Service       │  │    Service       │  │   │
│  │  └────────────────┘  └──────────────────┘  └──────────────────┘  │   │
│  └───────────────────────────────────────────────────────────────────┘   │
│                                      │                                    │
│  ┌───────────────────────────────────┴───────────────────────────────┐   │
│  │                         Adapter Layer                              │   │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌──────────┐ │   │
│  │  │   Museum    │  │   Gallery   │  │   Archive   │  │   DAM    │ │   │
│  │  │   Adapter   │  │   Adapter   │  │   Adapter   │  │  Adapter │ │   │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └──────────┘ │   │
│  └───────────────────────────────────────────────────────────────────┘   │
│                                      │                                    │
│  ┌───────────────────────────────────┴───────────────────────────────┐   │
│  │                         Data Layer                                 │   │
│  │              Laravel Query Builder (Illuminate\Database)           │   │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────────┐ │   │
│  │  │ahg_loan │ │ahg_loan_│ │ahg_loan_│ │ahg_loan_│ │ahg_loan_    │ │   │
│  │  │         │ │ object  │ │document │ │shipment │ │condition_   │ │   │
│  │  │         │ │         │ │         │ │         │ │report       │ │   │
│  │  └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────────┘ │   │
│  └───────────────────────────────────────────────────────────────────┘   │
│                                                                           │
└───────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_367ee8c0.png)
```

### Component Diagram

```
┌───────────────────────────────────────────────────────────────────────────┐
│                                                                           │
│    ┌─────────────────┐         ┌─────────────────────────────────────┐   │
│    │   Symfony 1.4   │         │         ahgLoanPlugin               │   │
│    │   (AtoM Base)   │         │                                     │   │
│    │                 │         │   ┌─────────────────────────────┐   │   │
│    │  ┌───────────┐  │  uses   │   │        LoanService          │   │   │
│    │  │  Actions  │──┼─────────┼──>│                             │   │   │
│    │  └───────────┘  │         │   │  - create()                 │   │   │
│    │                 │         │   │  - get()                    │   │   │
│    │  ┌───────────┐  │         │   │  - update()                 │   │   │
│    │  │ Templates │  │         │   │  - search()                 │   │   │
│    │  └───────────┘  │         │   │  - updateStatus()           │   │   │
│    │                 │         │   │                             │   │   │
│    └─────────────────┘         │   └──────────────┬──────────────┘   │   │
│                                │                  │                   │   │
│    ┌─────────────────┐         │                  │ uses              │   │
│    │  atom-framework │         │                  v                   │   │
│    │                 │         │   ┌─────────────────────────────┐   │   │
│    │  ┌───────────┐  │         │   │     SectorAdapter           │   │   │
│    │  │ Database  │  │         │   │     (Interface)             │   │   │
│    │  │Connection │──┼─────────┼──>│                             │   │   │
│    │  └───────────┘  │         │   │  - validateLoanData()       │   │   │
│    │                 │         │   │  - enrichLoanData()         │   │   │
│    │                 │         │   │  - getWorkflowStates()      │   │   │
│    └─────────────────┘         │   │                             │   │   │
│                                │   └──────────────┬──────────────┘   │   │
│                                │                  │                   │   │
│                                │       ┌──────────┼──────────┐       │   │
│                                │       │          │          │       │   │
│                                │       v          v          v       │   │
│                                │   ┌───────┐ ┌───────┐ ┌───────┐    │   │
│                                │   │Museum │ │Gallery│ │Archive│    │   │
│                                │   │Adapter│ │Adapter│ │Adapter│    │   │
│                                │   └───────┘ └───────┘ └───────┘    │   │
│                                │                                     │   │
│                                └─────────────────────────────────────┘   │
│                                                                           │
└───────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_595c24d5.png)
```

---

## Entity Relationship Diagram (ERD)

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                    LOAN MODULE ERD                                       │
└─────────────────────────────────────────────────────────────────────────────────────────┘

                              ┌──────────────────────┐
                              │   information_object │
                              │      (AtoM Core)     │
                              ├──────────────────────┤
                              │ PK id                │
                              │    identifier        │
                              │    ...               │
                              └──────────┬───────────┘
                                         │
                                         │ references
                                         │
┌────────────────────┐                   │                    ┌────────────────────┐
│ ahg_loan_courier   │                   │                    │ahg_loan_facility   │
├────────────────────┤                   │                    │    _report         │
│ PK id              │                   │                    ├────────────────────┤
│    company_name    │                   │                    │ PK id              │
│    contact_name    │                   │                    │ FK loan_id ────────┼───┐
│    contact_email   │                   │                    │    venue_name      │   │
│    is_art_specialist│                  │                    │    has_climate_ctrl│   │
│    has_climate_ctrl│                   │                    │    has_security    │   │
│    insurance_coverage│                 │                    │    overall_rating  │   │
│    is_active       │                   │                    │    approved        │   │
└────────┬───────────┘                   │                    └────────────────────┘   │
         │                               │                                              │
         │ references                    │                                              │
         │                               │                                              │
         │    ┌──────────────────────────┴───────────────────────────┐                 │
         │    │                      ahg_loan                         │                 │
         │    ├───────────────────────────────────────────────────────┤                 │
         │    │ PK id                                                 │                 │
         │    │    loan_number (UNIQUE)                               │<────────────────┘
         │    │    loan_type (out/in)                                 │
         │    │    sector (museum/gallery/archive/dam)                │
         │    │    purpose                                            │
         │    │    partner_institution                                │
         │    │    partner_contact_name                               │
         │    │    partner_contact_email                              │
         │    │    request_date                                       │
         │    │    start_date                                         │
         │    │    end_date                                           │
         │    │    return_date                                        │
         │    │    insurance_type                                     │
         │    │    insurance_value                                    │
         │    │    status                                             │
         │    │    sector_data (JSON)                                 │
         │    │ FK created_by                                         │
         │    └───────────────────────────┬───────────────────────────┘
         │                                │
         │                    ┌───────────┼───────────┬───────────┬───────────┐
         │                    │           │           │           │           │
         │                    v           v           v           v           v
         │    ┌───────────────────┐ ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────────┐
         │    │  ahg_loan_object  │ │ahg_loan_  │ │ahg_loan_  │ │ahg_loan_  │ │ahg_loan_status│
         │    ├───────────────────┤ │ document  │ │ extension │ │  cost     │ │   _history    │
         │    │ PK id             │ ├───────────┤ ├───────────┤ ├───────────┤ ├───────────────┤
         │    │ FK loan_id        │ │ PK id     │ │ PK id     │ │ PK id     │ │ PK id         │
         │    │ FK info_object_id │ │ FK loan_id│ │ FK loan_id│ │ FK loan_id│ │ FK loan_id    │
         │    │    object_title   │ │ doc_type  │ │ prev_date │ │ cost_type │ │ from_status   │
         │    │    object_identifier││ file_path│ │ new_date  │ │ amount    │ │ to_status     │
         │    │    insurance_value│ │ file_name │ │ reason    │ │ vendor    │ │ changed_by    │
         │    │    status         │ │ uploaded_by││ approved_by││ paid      │ │ comment       │
         │    └─────────┬─────────┘ └───────────┘ └───────────┘ └───────────┘ └───────────────┘
         │              │
         │              │ references
         │              v
         │    ┌───────────────────────┐         ┌───────────────────────┐
         │    │ahg_loan_condition     │         │ahg_loan_condition     │
         │    │      _report          │         │      _image           │
         │    ├───────────────────────┤         ├───────────────────────┤
         │    │ PK id                 │    1:N  │ PK id                 │
         │    │ FK loan_id            │<────────│ FK condition_report_id│
         │    │ FK loan_object_id     │         │    file_path          │
         │    │    report_type        │         │    image_type         │
         │    │    overall_condition  │         │    caption            │
         │    │    has_damage         │         │    annotation_data    │
         │    │    examination_date   │         └───────────────────────┘
         │    └───────────────────────┘
         │
         │    ┌───────────────────────┐         ┌───────────────────────┐
         └───>│  ahg_loan_shipment    │         │ahg_loan_shipment_event│
              ├───────────────────────┤         ├───────────────────────┤
              │ PK id                 │    1:N  │ PK id                 │
              │ FK loan_id            │<────────│ FK shipment_id        │
              │ FK courier_id         │         │    event_time         │
              │    shipment_type      │         │    event_type         │
              │    tracking_number    │         │    location           │
              │    status             │         │    description        │
              │    scheduled_delivery │         └───────────────────────┘
              │    actual_delivery    │
              └───────────────────────┘


┌───────────────────────────────────────────────────────────────────────────────────────────┐
│                              NOTIFICATION SUBSYSTEM                                        │
└───────────────────────────────────────────────────────────────────────────────────────────┘

              ┌─────────────────────────────┐         ┌─────────────────────────────┐
              │ahg_loan_notification_template│         │  ahg_loan_notification_log  │
              ├─────────────────────────────┤         ├─────────────────────────────┤
              │ PK id                       │    1:N  │ PK id                       │
              │    code (UNIQUE)            │<────────│ FK template_id              │
              │    name                     │         │ FK loan_id                  │
              │    subject_template         │         │    recipient_email          │
              │    body_template            │         │    subject                  │
              │    trigger_event            │         │    body                     │
              │    trigger_days_before      │         │    status                   │
              │    is_active                │         │    sent_at                  │
              └─────────────────────────────┘         └─────────────────────────────┘
![wireframe](./images/wireframes/wireframe_f13b33fc.png)
```

---

## Data Flow Diagrams

### Loan Creation Flow

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              LOAN CREATION DATA FLOW                                     │
└─────────────────────────────────────────────────────────────────────────────────────────┘

     ┌─────────┐                                                           ┌─────────────┐
     │  User   │                                                           │  Database   │
     └────┬────┘                                                           └──────┬──────┘
          │                                                                       │
          │  1. Submit loan form                                                  │
          │──────────────────────────>┌─────────────────┐                        │
          │                           │  loanActions    │                        │
          │                           │  ::executeAdd() │                        │
          │                           └────────┬────────┘                        │
          │                                    │                                  │
          │                                    │ 2. Get sector adapter            │
          │                                    │──────────────────────────>       │
          │                           ┌────────┴────────┐                        │
          │                           │ AdapterFactory  │                        │
          │                           │ ::create()      │                        │
          │                           └────────┬────────┘                        │
          │                                    │                                  │
          │                                    │ 3. Return MuseumAdapter         │
          │                           <────────┴─────────────────────────         │
          │                           ┌────────────────────┐                     │
          │                           │   MuseumAdapter    │                     │
          │                           └────────┬───────────┘                     │
          │                                    │                                  │
          │                                    │ 4. Validate loan data            │
          │                                    │──────────────────────────>       │
          │                           ┌────────┴────────┐                        │
          │                           │ LoanService     │                        │
          │                           │ ::create()      │                        │
          │                           └────────┬────────┘                        │
          │                                    │                                  │
          │                                    │ 5. Generate loan number          │
          │                                    │   (MUS-LO-2026-0001)             │
          │                                    │                                  │
          │                                    │ 6. INSERT into ahg_loan          │
          │                                    │────────────────────────────────>│
          │                                    │                                  │
          │                                    │ 7. Return loan_id                │
          │                                    │<────────────────────────────────│
          │                                    │                                  │
          │                                    │ 8. For each object:              │
          │                                    │    INSERT into ahg_loan_object   │
          │                                    │────────────────────────────────>│
          │                                    │                                  │
          │                                    │ 9. Trigger onLoanCreated()       │
          │                                    │──────────────────────────>       │
          │                           ┌────────┴────────┐                        │
          │                           │   MuseumAdapter │                        │
          │                           │::onLoanCreated()│                        │
          │                           └────────┬────────┘                        │
          │                                    │                                  │
          │  10. Redirect to loan view         │                                  │
          │<───────────────────────────────────│                                  │
          │                                    │                                  │
          v                                    v                                  v
![wireframe](./images/wireframes/wireframe_4104f655.png)
```

### Status Transition Flow

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              STATUS TRANSITION FLOW                                      │
└─────────────────────────────────────────────────────────────────────────────────────────┘

     ┌─────────┐     ┌──────────────┐     ┌─────────────┐     ┌────────────┐
     │  User   │     │ LoanService  │     │SectorAdapter│     │  Database  │
     └────┬────┘     └──────┬───────┘     └──────┬──────┘     └─────┬──────┘
          │                 │                    │                   │
          │ updateStatus    │                    │                   │
          │ (loanId,        │                    │                   │
          │  'approved',    │                    │                   │
          │  userId)        │                    │                   │
          │────────────────>│                    │                   │
          │                 │                    │                   │
          │                 │ 1. Get loan        │                   │
          │                 │──────────────────────────────────────>│
          │                 │                    │                   │
          │                 │ 2. Return loan data│                   │
          │                 │<──────────────────────────────────────│
          │                 │                    │                   │
          │                 │ 3. Validate transition                 │
          │                 │    (draft -> approved)                 │
          │                 │                    │                   │
          │                 │ 4. UPDATE ahg_loan │                   │
          │                 │    SET status='approved'               │
          │                 │──────────────────────────────────────>│
          │                 │                    │                   │
          │                 │ 5. INSERT into     │                   │
          │                 │    ahg_loan_status_history             │
          │                 │──────────────────────────────────────>│
          │                 │                    │                   │
          │                 │ 6. onStatusChanged │                   │
          │                 │───────────────────>│                   │
          │                 │                    │                   │
          │                 │                    │ 7. Sector-specific│
          │                 │                    │    actions        │
          │                 │                    │    (e.g., notify  │
          │                 │                    │     curator)      │
          │                 │                    │                   │
          │ 8. Return true  │                    │                   │
          │<────────────────│                    │                   │
          │                 │                    │                   │
          v                 v                    v                   v
![wireframe](./images/wireframes/wireframe_fa277c14.png)
```

---

## Workflow State Machines

### Museum Loan Out Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                        MUSEUM LOAN OUT WORKFLOW (Spectrum 5.0)                           │
└─────────────────────────────────────────────────────────────────────────────────────────┘

                                    ┌─────────┐
                                    │  draft  │
                                    └────┬────┘
                                         │ submit
                                         v
                                   ┌───────────┐
                              ┌────│ submitted │────┐
                              │    └─────┬─────┘    │
                        reject│          │ review   │ request_info
                              │          v          │
                              │   ┌─────────────┐   │
                              │   │under_review │───┘
                              │   └──────┬──────┘
                              │          │
                    ┌─────────┴──────────┼──────────┐
                    │                    │          │
                    v              approve│          │ reject
              ┌───────────┐              │          │
              │ cancelled │              v          v
              └───────────┘        ┌──────────┐  ┌──────────┐
                                   │ approved │  │ rejected │
                                   └────┬─────┘  └──────────┘
                                        │ send_agreement
                                        v
                                ┌─────────────────┐
                                │agreement_pending│
                                └────────┬────────┘
                                         │ sign_agreement
                                         v
                                ┌─────────────────┐
                                │ agreement_signed│
                                └────────┬────────┘
                                         │ request_insurance
                                         v
                                ┌─────────────────┐
                                │insurance_pending│
                                └────────┬────────┘
                                         │ confirm_insurance
                                         v
                                ┌──────────────────┐
                                │insurance_confirmed│
                                └────────┬─────────┘
                                         │ start_condition
                                         v
                                ┌─────────────────┐
                                │ condition_check │
                                └────────┬────────┘
                                         │ start_packing
                                         v
                                   ┌─────────┐
                                   │ packing │
                                   └────┬────┘
                                        │ dispatch
                                        v
                                  ┌───────────┐
                                  │dispatched │
                                  └─────┬─────┘
                                        │ in_transit
                                        v
                                  ┌───────────┐
                                  │ in_transit│
                                  └─────┬─────┘
                                        │ confirm_receipt
                                        v
                                   ┌──────────┐
                                   │ received │
                                   └────┬─────┘
                                        │ put_on_display
                                        v
                                  ┌────────────┐
                                  │ on_display │
                                  └─────┬──────┘
                                        │ initiate_return
                                        v
                              ┌──────────────────┐
                              │ return_initiated │
                              └────────┬─────────┘
                                       │ dispatch_return
                                       v
                              ┌──────────────────┐
                              │return_in_transit │
                              └────────┬─────────┘
                                       │ receive_return
                                       v
                                  ┌──────────┐
                                  │ returned │
                                  └────┬─────┘
                                       │ close
                                       v
                                   ┌────────┐
                                   │ closed │
                                   └────────┘
![wireframe](./images/wireframes/wireframe_cdf6ba11.png)
```

### Gallery Loan Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              GALLERY LOAN WORKFLOW                                       │
└─────────────────────────────────────────────────────────────────────────────────────────┘

                         ┌─────────┐
                         │  draft  │
                         └────┬────┘
                              │ inquire
                              v
                         ┌─────────┐
                    ┌────│ inquiry │────┐
                    │    └────┬────┘    │
              decline│        │request  │ decline
                    │        v         │
                    │   ┌───────────┐  │
                    │   │ requested │──┘
                    │   └─────┬─────┘
                    │         │
                    │    ┌────┴────┐
                    v    │         │
              ┌──────────┐   approve│
              │ declined │         │
              └──────────┘         v
                            ┌──────────┐
                            │ approved │
                            └────┬─────┘
                                 │ sign_agreement
                                 v
                            ┌─────────┐
                            │ agreed  │
                            └────┬────┘
                                 │ prepare
                                 v
                           ┌───────────┐
                           │ preparing │
                           └─────┬─────┘
                                 │ dispatch
                                 v
                         ┌───────────────┐
                         │in_transit_out │
                         └───────┬───────┘
                                 │ receive
                                 v
                            ┌─────────┐
                            │ on_loan │
                            └────┬────┘
                    ┌────────────┼────────────┐
                    │            │            │
              display│      mark_sold    initiate_return
                    v            │            │
              ┌───────────┐      │            │
              │on_display │──────┤            │
              └───────────┘      │            │
                                 v            v
                            ┌────────┐  ┌─────────────────┐
                            │  sold  │  │in_transit_return│
                            └────────┘  └────────┬────────┘
                                                 │ complete_return
                                                 v
                                            ┌──────────┐
                                            │ returned │
                                            └──────────┘
![wireframe](./images/wireframes/wireframe_d6dc36fa.png)
```

### DAM License Workflow

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              DAM LICENSE WORKFLOW                                        │
└─────────────────────────────────────────────────────────────────────────────────────────┘

                              ┌─────────┐
                              │  draft  │
                              └────┬────┘
                                   │ submit
                                   v
                             ┌───────────┐
                        ┌────│ requested │────┐
                        │    └─────┬─────┘    │
                  reject│          │ review   │
                        │          v          │
                        │   ┌─────────────┐   │
                        └───│under_review │───┘
                            └──────┬──────┘
                                   │
                          ┌────────┴────────┐
                          │                 │
                    approve                 reject
                          │                 │
                          v                 v
                    ┌──────────┐      ┌──────────┐
                    │ approved │      │ rejected │
                    └────┬─────┘      └──────────┘
                         │ send_agreement
                         v
                 ┌─────────────────┐
                 │agreement_pending│
                 └───────┬─────────┘
                         │
               ┌─────────┴─────────┐
               │                   │
        skip_payment        agreement_signed
               │                   │
               │                   v
               │          ┌────────────────┐
               │          │payment_pending │
               │          └───────┬────────┘
               │                  │ payment_received
               │                  │
               └────────┬─────────┘
                        │
                        v
                   ┌─────────┐
              ┌────│ active  │────┐
              │    └────┬────┘    │
        revoke│         │         │ expire
              │         │         │
              v         │         v
         ┌─────────┐    │    ┌─────────┐
         │ revoked │    │    │ expired │
         └─────────┘    │    └────┬────┘
                        │         │ renew
                        │         │
                        └────<────┘
![wireframe](./images/wireframes/wireframe_e829f2e0.png)
```

---

## Service Class Reference

### LoanService

**Namespace**: `AhgLoan\Services\Loan`

**Purpose**: Core loan CRUD operations with sector adapter support.

```php
class LoanService
{
    // Constants
    const TYPE_OUT = 'out';
    const TYPE_IN = 'in';
    const PURPOSES = [...];
    const INSURANCE_TYPES = [...];
    const STATUSES = [...];

    // Core Methods
    public function setSectorAdapter(SectorAdapterInterface $adapter): self;
    public function create(string $type, array $data, int $userId): int;
    public function get(int $loanId): ?array;
    public function getByNumber(string $loanNumber): ?array;
    public function update(int $loanId, array $data): bool;
    public function updateStatus(int $loanId, string $status, int $userId, ?string $comment): bool;

    // Object Management
    public function addObject(int $loanId, array $object): int;
    public function removeObject(int $loanId, int $objectId): bool;
    public function getObjects(int $loanId): array;

    // Search & Retrieval
    public function search(array $filters, int $limit, int $offset): array;
    public function getDueSoon(int $days = 30): array;
    public function getOverdue(): array;

    // Documents
    public function addDocument(int $loanId, string $type, string $path, array $metadata): int;
    public function getDocuments(int $loanId): array;

    // Extensions & Returns
    public function extend(int $loanId, string $newEndDate, ?string $reason, int $userId): bool;
    public function recordReturn(int $loanId, string $returnDate, ?string $notes, int $userId): bool;

    // Statistics
    public function getStatistics(): array;
}
```

### SectorAdapterInterface

**Namespace**: `AhgLoan\Adapters`

**Purpose**: Defines contract for sector-specific behavior.

```php
interface SectorAdapterInterface
{
    public function getSectorCode(): string;
    public function getSectorName(): string;
    public function getPurposes(): array;
    public function validateLoanData(array $data): array;
    public function enrichLoanData(array $data): array;
    public function enrichObjectData(array $data): array;
    public function onLoanCreated(int $loanId, array $data): void;
    public function onStatusChanged(int $loanId, string $prev, string $new): void;
    public function getWorkflowStates(): array;
    public function getWorkflowTransitions(): array;
    public function getDocumentTypes(): array;
    public function requiresConditionReport(): bool;
    public function requiresFacilityReport(): bool;
    public function getDefaultLoanDuration(): int;
}
```

### Supporting Services

| Service | Purpose |
|---------|---------|
| `FacilityReportService` | Borrower venue assessments |
| `ConditionReportService` | Object condition documentation |
| `CourierManagementService` | Transport and shipping |
| `LoanNotificationService` | Email alerts and reminders |
| `LoanCalendarService` | Calendar and scheduling |
| `LoanDashboardService` | Statistics and reporting |
| `LoanAgreementGenerator` | Document generation |

---

## Database Schema Details

### Core Tables

#### ahg_loan
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| loan_number | VARCHAR(50) | Unique identifier (e.g., MUS-LO-2026-0001) |
| loan_type | ENUM | 'out' or 'in' |
| sector | ENUM | museum/gallery/archive/dam |
| purpose | VARCHAR(100) | Loan purpose |
| partner_institution | VARCHAR(500) | Other party name |
| start_date | DATE | Loan period start |
| end_date | DATE | Loan period end |
| return_date | DATE | Actual return date |
| insurance_type | ENUM | Insurance arrangement |
| insurance_value | DECIMAL(15,2) | Total insured value |
| status | VARCHAR(50) | Current workflow status |
| sector_data | JSON | Sector-specific data |

#### ahg_loan_object
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| loan_id | BIGINT | FK to ahg_loan |
| information_object_id | INT | FK to AtoM object |
| external_object_id | VARCHAR | For non-AtoM objects |
| object_title | VARCHAR(500) | Cached title |
| insurance_value | DECIMAL(15,2) | Per-object value |
| status | ENUM | Object-level status |

### Loan Number Format

```
{SECTOR}-{TYPE}-{YEAR}-{SEQUENCE}

Examples:
  MUS-LO-2026-0001  (Museum Loan Out #1 of 2026)
  GAL-LI-2026-0012  (Gallery Loan In #12 of 2026)
  ARC-LO-2026-0003  (Archive Loan Out #3 of 2026)
  DAM-LI-2026-0045  (DAM License In #45 of 2026)
```

---

## Integration Points

### With AtoM Core

```php
// Referencing AtoM information_object
$loanService->addObject($loanId, [
    'information_object_id' => $atomObjectId,
    'object_title' => $object->getTitle(),
    'object_identifier' => $object->identifier,
]);
```

### With atom-framework

```php
// Using Laravel Query Builder via atom-framework
use AtomFramework\Database\Connection;

Connection::initialize($config);
$db = Connection::getInstance();

$loanService = new LoanService($db);
```

### With Other AHG Plugins

```php
// From ahgMuseumPlugin - using shared loan services
require_once sfConfig::get('sf_plugins_dir').'/ahgLoanPlugin/lib/Services/Loan/LoanService.php';
require_once sfConfig::get('sf_plugins_dir').'/ahgLoanPlugin/lib/Adapters/AdapterFactory.php';

use AhgLoan\Services\Loan\LoanService;
use AhgLoan\Adapters\AdapterFactory;

$loanService = new LoanService($db);
$loanService->setSectorAdapter(AdapterFactory::create('museum'));
```

---

## Configuration

### Plugin Settings

```php
// apps/qubit/config/config.php
sfConfig::set('app_loan_default_duration', 180);
sfConfig::set('app_loan_reminder_days', [30, 14, 7]);
sfConfig::set('app_loan_require_insurance', true);
sfConfig::set('app_loan_insurance_minimum', 10000);
```

### Notification Templates

Templates use `{{variable}}` syntax:
- `{{loan_number}}` - Loan reference number
- `{{partner_institution}}` - Other party name
- `{{partner_contact_name}}` - Contact person
- `{{start_date}}` - Loan start date
- `{{end_date}}` - Loan end date
- `{{institution_name}}` - Your institution

---

## Installation

### Database Setup

```bash
mysql -u root archive < /usr/share/nginx/archive/atom-ahg-plugins/ahgLoanPlugin/data/install.sql
```

### Enable Plugin

```sql
UPDATE atom_plugin
SET is_enabled = 1
WHERE name = 'ahgLoanPlugin';
```

### Clear Cache

```bash
php symfony cc
sudo systemctl restart php8.3-fpm
```

---

## API Reference

### REST Endpoints (if ahgAPIPlugin enabled)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/loans` | List loans |
| GET | `/api/loans/{id}` | Get loan details |
| POST | `/api/loans` | Create loan |
| PUT | `/api/loans/{id}` | Update loan |
| POST | `/api/loans/{id}/status` | Change status |
| GET | `/api/loans/{id}/objects` | Get loan objects |
| POST | `/api/loans/{id}/objects` | Add object |

---

## Testing

### Unit Test Example

```php
class LoanServiceTest extends TestCase
{
    public function testCreateLoan()
    {
        $service = new LoanService($this->db);
        $service->setSectorAdapter(new MuseumAdapter());

        $loanId = $service->create('out', [
            'partner_institution' => 'Test Museum',
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
        ], 1);

        $this->assertGreaterThan(0, $loanId);

        $loan = $service->get($loanId);
        $this->assertEquals('museum', $loan['sector']);
        $this->assertStringStartsWith('MUS-LO-', $loan['loan_number']);
    }
}
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Loan number not generating | Sequence conflict | Check for duplicate loan_numbers |
| Sector adapter not loading | Autoload issue | Ensure require_once paths correct |
| Status won't change | Invalid transition | Check workflow transition rules |
| Notifications not sending | Mail config | Verify PHP mail() or SMTP setup |

### Debug Mode

```php
// Enable logging
$service = new LoanService($db, $monologLogger);
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-19 | Initial release with all sectors |

---

*Technical Documentation*
*Author: Johan Pieterse (johan@theahg.co.za)*
*Last Updated: January 2026*
