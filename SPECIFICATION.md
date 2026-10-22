# CBT SYSTEM MVP ARCHITECTURAL SPECIFICATION & BLUEPRINT
**Project**: Quizz (Reliable & Local-First Computer-Based Testing)  
**Location**: `/home/apriv4/Projects/quizz`  
**Target Architecture**: Laravel 13 + Inertia.js 3 + Vue 3.5 (TypeScript) + Tailwind CSS v4 + Spatie Permission (UUID)

---

## 1. EXECUTIVE SUMMARY & CORE PRINCIPLES

This document serves as the absolute single source of truth for rebuilding the CBT (Computer-Based Testing) application. The system is designed to fulfill three critical operational requirements:
1. **High Concurrency Reliability**: Capable of serving hundreds to thousands of concurrent test-takers without locking database rows or causing database CPU spikes upon simultaneous exam starts.
2. **Local-First Zero Data-Loss Resilience**: An exam taking engine that guarantees zero lost answers even if school/lab Wi-Fi drops completely, student laptops run out of battery, or browsers accidentally refresh.
3. **Clean, Extensible & Pragmatic Architecture**: Strictly avoiding microservice/queue bloat and overengineering. The database consists of only 5 core UUID domain tables, while supporting question types extensibility (Multiple Choice and Essay for MVP; matching, true/false for future releases) via structured JSON fields.

---

## 2. TECHNOLOGY STACK & ENVIRONMENT

| Layer | Technology | Details |
| :--- | :--- | :--- |
| **Backend Framework** | Laravel 13.x | PHP 8.3+, Laravel Fortify for authentication |
| **Frontend Framework** | Vue 3.5 | `<script setup>`, TypeScript, Composition API |
| **Monolith Bridge** | Inertia.js 3.x | `@inertiajs/vue3`, seamless client-side SPA routing |
| **Styling & Icons** | Tailwind CSS v4 | `@tailwindcss/vite`, `@lucide/vue`, `reka-ui` (in `resources/js/components/ui/`) |
| **Frontend Utilities** | `@vueuse/core` | `useStorage` (localStorage), `useNetwork` (online status), debounce helpers |
| **Authorization** | Spatie Laravel Permission | `spatie/laravel-permission` configured for UUIDs |
| **Database** | PostgreSQL / MySQL / SQLite | Primary Keys use **UUID v4** across all domain tables |
| **Cache Engine** | Redis-ready | Native Laravel `Cache::remember()` abstraction |
| **Testing** | Pest PHP 4 | `pestphp/pest` for critical business path testing |

### Explicit Exclusions for MVP (Post-MVP Roadmap):
- **NO Rich-Text / Markdown / Media Uploads**: Question text and option texts are pure plain text.
- **NO Excel/CSV Exports**: Scores and evaluation tables are displayed directly in clean web UI data tables.
- **NO Complex Proctoring**: No webcam recording or aggressive OS screen locks.

---

## 3. ARCHITECTURAL PATTERNS & CODE STRUCTURE CONVENTIONS

The project strictly follows layered separation of concerns:

```text
app/
├── Http/
│   ├── Controllers/             # HTTP endpoints only. Delegates logic, returns Inertia or JSON.
│   ├── Requests/                # DEDICATED FormRequests. NO inline $request->validate() in controllers.
│   └── Middleware/              # HandleInertiaRequests (shares auth.permissions & roles).
├── Services/                    # Orchestrates business logic and multi-repository workflows.
│   ├── ExamService.php
│   └── CbtEngineService.php
├── Actions/                     # Single-purpose invokable classes for complex/critical operations.
│   ├── StartExamAction.php
│   ├── SaveStudentAnswerAction.php
│   ├── SubmitExamAction.php
│   ├── GradeMultipleChoiceAction.php
│   ├── ResetStudentSessionAction.php
│   └── ExtendTimeAction.php
├── Repositories/
│   ├── Contracts/               # Interfaces defining data access methods.
│   │   ├── ExamRepositoryInterface.php
│   │   ├── QuestionRepositoryInterface.php
│   │   └── AttemptRepositoryInterface.php
│   └── Eloquent/                # Concrete Eloquent implementations.
│       ├── ExamRepository.php
│       ├── QuestionRepository.php
│       └── AttemptRepository.php
├── Enums/                       # Strictly typed PHP 8.3 backed enums.
│   ├── ExamStatus.php           # draft, published, archived
│   ├── AttemptStatus.php        # in_progress, submitted, timed_out, reset
│   └── QuestionType.php         # multiple_choice, essay
└── Models/                      # Eloquent models using HasUuids and HasRoles.
```

---

## 4. ROLE & PERMISSION MANAGEMENT (SPATIE + UUID)

All features are strictly permission-based.

### 4.1. Roles
- `admin`: Super administrator (manages users, settings).
- `teacher`: Exam creator, proctor, and essay grader.
- `student`: Test-taker.

### 4.2. Granular Permissions
- `exams.view`: View exam list and exam details.
- `exams.create`: Create new exams and manage questions.
- `exams.edit`: Update draft exams.
- `exams.delete`: Delete/archive exams.
- `exams.monitor`: Access real-time student monitoring board.
- `exams.extend_time`: Emergency proctor tool to add minutes to specific attempts.
- `exams.reset_session`: Emergency proctor tool to unlock crashed attempts.
- `exams.grade`: Manually score essay questions.
- `exams.take`: Start and submit exam attempts.

### 4.3. Dual-Layer Permission Enforcement
- **Backend**: Enforced in dedicated FormRequests (`authorize()` method) and Controller routes (`middleware('permission:...')`).
- **Frontend**: Permissions are shared in `HandleInertiaRequests.php` via `auth.permissions: string[]`.
- **Vue Composable**: A helper `can(permission: string): boolean` is available across all Vue components to conditionally render UI controls (`v-if="can('exams.create')"`).

---

## 5. DATABASE SCHEMA (5 UUID-BASED DOMAIN TABLES)

All primary keys are UUIDs (`$table->uuid('id')->primary()`) and foreign keys use `foreignUuid()`.

```sql
-- 1. users
CREATE TABLE users (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- 2. exams
CREATE TABLE exams (
    id UUID PRIMARY KEY,
    teacher_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    duration_minutes INT NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    token VARCHAR(6) NOT NULL,
    is_randomize BOOLEAN DEFAULT FALSE,
    status VARCHAR(20) NOT NULL DEFAULT 'draft', -- draft, published, archived
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
CREATE INDEX idx_exams_token_status ON exams(token, status);

-- 3. questions
CREATE TABLE questions (
    id UUID PRIMARY KEY,
    exam_id UUID NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
    type VARCHAR(30) NOT NULL, -- multiple_choice, essay
    question_text TEXT NOT NULL,
    options JSONB NULL,        -- e.g. [{"key": "A", "text": "Option 1"}, ...]
    answer_key JSONB NULL,     -- e.g. "A" for MC, null/rubric for essay
    score_weight DOUBLE PRECISION DEFAULT 1.0,
    "order" INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
CREATE INDEX idx_questions_exam_id ON questions(exam_id);

-- 4. exam_attempts
CREATE TABLE exam_attempts (
    id UUID PRIMARY KEY,
    exam_id UUID NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
    student_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    started_at TIMESTAMP NOT NULL,
    submitted_at TIMESTAMP NULL,
    extra_time_minutes INT DEFAULT 0,
    total_score DOUBLE PRECISION NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress', -- in_progress, submitted, timed_out, reset
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
CREATE INDEX idx_attempts_exam_student ON exam_attempts(exam_id, student_id);

-- 5. student_answers
CREATE TABLE student_answers (
    id UUID PRIMARY KEY,
    attempt_id UUID NOT NULL REFERENCES exam_attempts(id) ON DELETE CASCADE,
    question_id UUID NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
    answer_content TEXT NULL,
    is_flagged BOOLEAN DEFAULT FALSE,
    score_awarded DOUBLE PRECISION NULL,
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    CONSTRAINT uq_attempt_question UNIQUE (attempt_id, question_id)
);
```

---

## 6. CBT ENGINE: LOCAL-FIRST & CONCURRENCY MECHANICS

### 6.1. Eliminating the Network Bottleneck
- **Never fetch questions per click**: At exam start, the full question set is fetched once and stored in Vue reactive state and backed up in `localStorage` (`@vueuse/core` `useStorage`).
- Navigating between question numbers (1, 2, ..., N) is completely client-side in-memory (0ms latency, zero server requests).

### 6.2. Instant Local Persistence + Debounced Sync
1. **Selecting Multiple Choice (0ms)**:
   - Updates Vue state and commits to `localStorage` immediately.
   - Dispatches background `POST /api/exams/{attemptId}/answers` with payload `{ question_id, answer_content: "A" }`.
2. **Typing Essay**:
   - Commits to `localStorage` on every keystroke.
   - Background API sync is debounced by 1.5 seconds of idle time.
3. **Atomic Upsert on Backend**:
   - The backend runs an idempotent `updateOrCreate` on `student_answers` matching `(attempt_id, question_id)`.
   - Never locks entire attempt rows; fast and collision-free.

### 6.3. Offline Sync Queue
- When background sync fails (HTTP timeout, offline event):
  - Mark answer item status as `pending_sync`.
  - Push question ID to `syncQueue` in `localStorage`.
  - `@vueuse/core` `useNetwork` detects when `isOnline` returns to `true`.
  - An auto-sync watcher flushes the `syncQueue` sequentially to the server.
  - UI Badge indicates: *Synced (Green Cloud)* vs *Saved Locally (Amber Cloud)*.

### 6.4. Server-Authoritative Time & Proctor Tools
- Time limit calculation:
  $$\text{Deadline} = \text{started\_at} + (\text{duration\_minutes} + \text{extra\_time\_minutes})$$
- Frontend countdown timer is purely visual; server enforces the true deadline on submit.
- **Proctor Emergency Actions**:
  - `ExtendTimeAction`: Adds minutes to `extra_time_minutes` when a student experiences device/hardware delay.
  - `ResetStudentSessionAction`: Unlocks a student session if their workstation crashes, allowing seamless re-entry without losing previously synced answers.

---

## 7. DUAL SETUP & ENVIRONMENT INSTRUCTIONS

### Approach A: Conventional Local Setup (Windows / Laragon / Linux)
1. Ensure PHP 8.3+, Composer 2.x, Node.js 20+, and PostgreSQL (or MySQL) are installed.
2. Configure `.env`:
   ```env
   DB_CONNECTION=pgsql # or mysql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=quizz_db
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```
3. Run setup commands:
   ```bash
   composer install
   php artisan key:generate
   php artisan migrate --seed
   npm install
   npm run dev
   # In another terminal:
   php artisan serve
   ```

### Approach B: Docker Containerized Setup
Run with minimal `docker-compose.yml`:
```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
```

---

## 8. STEP-BY-STEP PHASED IMPLEMENTATION ROADMAP

> [!IMPORTANT]
> The agent MUST implement one phase at a time and stop at the end of each phase to await user approval (`"continue"` or `"lanjut"`).

### Phase 1: Foundation, Spatie Roles/Permissions, Migrations & Models
- Install `spatie/laravel-permission` with UUID configuration.
- Adjust `users` and session migrations to support UUIDs (`HasUuids`).
- Create Enums: `ExamStatus`, `AttemptStatus`, `QuestionType`.
- Create database migrations for `exams`, `questions`, `exam_attempts`, and `student_answers`.
- Create Eloquent Models with relationships, casts, and fillables.
- Create `RolePermissionSeeder` and `DatabaseSeeder` with Admin, Teacher, and Student credentials.
- Share permissions in `HandleInertiaRequests` and update `resources/js/types/auth.ts`.
- *(Stop and wait for "continue")*

### Phase 2: Repository Contracts, Implementations & Service Layer
- Create `ExamRepositoryInterface`, `QuestionRepositoryInterface`, `AttemptRepositoryInterface`.
- Implement concrete Eloquent repositories.
- Register bindings in `AppServiceProvider`.
- Implement base services: `ExamService` and `CbtEngineService`.
- *(Stop and wait for "continue")*

### Phase 3: Core CBT Backend API, Dedicated FormRequests & Actions
- Create FormRequests in `app/Http/Requests/` with `authorize()` permission checks.
- Implement Actions: `StartExamAction`, `SaveStudentAnswerAction` (Atomic Upsert), `SubmitExamAction`, `GradeMultipleChoiceAction`, `ResetStudentSessionAction`, and `ExtendTimeAction`.
- Implement exam question caching abstraction.
- Create API/Web routes and Controllers.
- *(Stop and wait for "continue")*

### Phase 4: Student Exam Frontend (Inertia Vue 3 Local-First Engine)
- Token entry and confirmation view.
- Exam workspace: Single-Page question navigation (0ms), flagged toggle, server-synced countdown timer.
- Local-first persistence via `@vueuse/core` `useStorage`, background sync queue, and offline/online badge.
- Exam submission confirmation dialog.
- *(Stop and wait for "continue")*

### Phase 5: Teacher Management & Monitoring Frontend (Inertia Vue 3)
- Exam CRUD and token generation (permission-checked with `can('exams.create')`).
- Question authoring (Multiple-Choice A-E & Essay plain-text).
- Live exam monitoring dashboard (student status, "Extend Time", "Reset Session").
- Essay manual grading view and score recap table.
- *(Stop and wait for "continue")*

### Phase 6: Critical Pest Tests & Final Verification
- Write and execute the 5 critical Pest PHP tests:
  1. `PermissionAuthorizationTest`
  2. `TokenValidationTest`
  3. `AtomicAnswerUpsertTest`
  4. `AutoGradingMultipleChoiceTest`
  5. `ProctorInterventionTest`
- Verify all tests pass (`php artisan test`).
- Provide verified setup guide.

