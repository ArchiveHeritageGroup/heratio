# Training Curriculum & LMS

The Training module is a generic, reusable learning-management system in the research portal. Any organisation configures its own **courses**, **roles/audiences**, **languages** and **pass marks** — nothing about a specific institution is built in.

Open it from the research portal sidebar → **Training** (`/research/training`).

## Build a course

1. **New Course** — set the title, description, **audience/role**, **language**, and **pass mark %** (default 80).
2. **Add modules** — each module is a step in the curriculum. A module can pull its content from a **curriculum lecture** (built in the Lectures builder, `type=curriculum`) or carry its own Markdown content. Modules are ordered.
3. **Assessment** — open *Assessment* and add multiple-choice questions (2–4 options each, mark the correct one). Optionally set an assessment pass mark that overrides the course default.
4. **Publish** the course when ready.

## Enrol learners and track progress

- On the course page, **enrol** a learner (name + optional email).
- Each enrolment has a **Learn** view: the learner works through the modules, marking each complete. Progress is tracked and shown as a bar.
- Once **all modules are complete**, the **assessment** unlocks.

## Assessment, pass mark and certification

- The learner takes the assessment; it is scored automatically.
- When the score meets the **pass mark** *and* all modules are complete, the enrolment is marked **completed** and a **certificate** (with a unique number and the score) is issued. The certificate is viewable and printable.
- The learner's best score is retained across attempts.

## Roles / cohorts / languages

All of these are **per-deployment configuration**, not hard-coded: define whatever roles/audiences you need, set each course's language, and enrol any cohort. A sysadmin course is just a course with an admin-oriented curriculum.

## Notes

- Course content reuses the **Lecture builder** curriculum lectures (#1105) — author once, sequence into one or more courses.
- This is Phase 1 (curriculum, modules, assessment, enrolment, progress, certification). Planned Phase 2: reporting dashboards, SCORM 1.2 / xAPI export, and virtual-classroom (Zoom/Meet/Teams) attendance adapters.

Tracked as issue #1099.
