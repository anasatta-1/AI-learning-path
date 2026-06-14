# Learning Path AI Project Report

Generated from a full local repository inspection on 2026-06-14.

## 1. Executive Summary

This project is a PHP/MySQL web application backed by a Python recommendation engine for academic course advising. A student logs in with a `student_id`, optionally enters a simulated number of completed semesters, reviews their completed course history, then opens a results page. The results page calls a PHP API endpoint, which either calls a remote AI model URL or spawns the local Python predictor. The Python side loads CSV data and pre-trained model artifacts, uses a prerequisite graph plus a Random Forest pass-probability model to rank valid course recommendations, then asks Gemini to generate a natural-language advising explanation.

The repository has two clear generations of implementation:

1. Current integrated flow:
   - `index.php`
   - `results.php`
   - `api/recommend.php`
   - `api/python_bridge.php`
   - `PYTHON/predict.py`
   - `PYTHON/engine/*`
   - `create_tables.sql`
   - CSV data files

2. Older or support artifacts:
   - root `recommend.php` and `recommend_gemini.php`, older direct PHP-to-Gemini endpoints
   - `app.js` and `students.js`, old static mock frontend path
   - file named `sql`, older incompatible schema
   - `softwaresubsystem.readme`, older simplified README
   - `full_recommendation_engine.py` and `PYTHON/notebooks/demo.ipynb`, earlier monolithic/notebook versions of the engine
   - generated SQL, pickle files, and Python bytecode caches

The active system is functional in design, but it has several high-impact configuration and data risks:

- `config.php` contains a real-looking Gemini API key in source.
- The PHP config points `PYTHON_MODEL_PATH` to `__DIR__ . '/python/predict.py'`, but the repository folder is named `PYTHON`, uppercase. This can break on case-sensitive systems.
- The Python engine requires `.env` values for `DATA_DIR`, `MODEL_DIR`, and `GEMINI_API_KEY`, but no `.env` is present in the repository listing.
- Root CSV data and `PYTHON/data` are not identical. Most notably root `prerequisites.csv` has 6 edges, while `PYTHON/data/prerequisites.csv` has 29 edges.
- `PYTHON/data/prerequisites.csv` includes prerequisite IDs with data-quality issues: `MATH102 ` has a trailing space and `CMPE321` appears although it is not in `courses.csv`. NetworkX will silently add those as extra graph nodes.
- `results.php` says "Best Recommended Courses" but the Python engine can return up to 6 courses; the old direct Gemini prompt requested top 3.
- The Python process prints errors as JSON to stdout without a non-zero exit code in some failure cases, so PHP may treat a model error as a normal response until recommendation parsing fails.

## 2. Repository Inventory

Top-level files:

- `.gitignore`: ignores `/vendor/`, `config.php`, and `.env`. `config.php` comments say it should not be committed, but the file is present in this working tree.
- `README.md`: main project description, setup, architecture summary, and claimed standards.
- `softwaresubsystem.readme`: older simplified description of a static mock learning-path app.
- `config.php`: PHP constants for database, Gemini fallback, remote model settings, and local Python bridge.
- `db.php`: shared MySQL connection helper.
- `index.php`: login and course-history page.
- `results.php`: recommendation results page, API fetch, and PDF generation.
- `recommend.php`: legacy root-level Gemini recommendation endpoint.
- `recommend_gemini.php`: duplicate of legacy root-level Gemini endpoint.
- `style.css`: shared dark/light theme and app styling.
- `app.js`: old mock-data table rendering and mock PDF generation.
- `students.js`: old mock student records for `app.js`.
- `create_tables.sql`: current MySQL schema matching the CSV files.
- `insert_courses.sql`: course-only seed insert script.
- `insert_sample_data.sql`: generated full seed script, about 1.8 MB.
- `generate_inserts.php`: script that regenerates `insert_sample_data.sql` from CSVs.
- `sql`: older incompatible schema file.
- `courses.csv`: root course catalog, 44 rows.
- `prerequisites.csv`: root prerequisite list, 6 rows.
- `students.csv`: root students table data, 4000 rows.
- `student_records.csv`: root transcript/history data, 25020 rows.

`api/`:

- `api/recommend.php`: current JSON API endpoint used by `results.php`.
- `api/python_bridge.php`: process bridge from PHP to Python via JSON stdin/stdout.
- `api/mock_model.deprecated.php`: old fake recommendation service.

`scratch/`:

- `scratch/test_bridge.php`: manual PHP test harness for the Python bridge.

`PYTHON/`:

- `PYTHON/predict.py`: JSON stdin/stdout entry point for PHP.
- `PYTHON/train.py`: trains the Random Forest and prerequisite graph pickles.
- `PYTHON/main.py`: local demo runner.
- `PYTHON/full_recommendation_engine.py`: monolithic version of the modular engine.
- `PYTHON/requirements.txt`: Python dependencies.
- `PYTHON/data/*`: Python-side CSV data copies.
- `PYTHON/models/pass_predictor.pkl`: trained Random Forest model artifact.
- `PYTHON/models/course_graph.pkl`: pickled NetworkX graph artifact.
- `PYTHON/notebooks/demo.ipynb`: exploratory/training/demo notebook.
- `PYTHON/engine/*`: modular Python recommendation package.
- `PYTHON/engine/__pycache__/*`: generated Python bytecode caches.

## 3. Runtime Architecture

The intended runtime path is:

1. Browser opens `index.php`.
2. `index.php` starts a PHP session and loads `db.php`.
3. User submits a student ID and optional completed semester count.
4. `index.php` verifies the student exists in MySQL.
5. Valid student ID and optional simulation semester are stored in `$_SESSION`.
6. The page displays course history grouped by academic year and semester.
7. User clicks "See Results".
8. Browser opens `results.php?student_id=...`.
9. `results.php` uses JavaScript `fetch()` to call `api/recommend.php?student_id=...`.
10. If session simulation mode exists, `results.php` appends `semesters_completed=...`.
11. `api/recommend.php` validates the student, computes the completed semester, and prepares a model payload.
12. If `AI_MODEL_URL` is configured, PHP POSTs JSON to that remote endpoint.
13. Otherwise, PHP includes `api/python_bridge.php` and calls `call_python_model()`.
14. `api/python_bridge.php` spawns `PYTHON/predict.py`, writes JSON to stdin, and reads JSON from stdout.
15. `PYTHON/predict.py` loads CSV data, model pickle, and graph pickle, produces recommendations, and asks Gemini for an explanation.
16. PHP normalizes Python output into frontend shape: `courses` and `reason`.
17. PHP deletes previous cached recommendations for the student and inserts the new course IDs/reason into `ai_recommendations`.
18. `results.php` renders student info, recommended courses, explanation text, and a jsPDF download button.

## 4. PHP Layer

### `config.php`

Defines application-wide constants:

- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
- `GEMINI_API_KEY`, `GEMINI_MODEL`
- `AI_MODEL_URL`, `AI_MODEL_TIMEOUT`, `AI_MODEL_API_KEY`
- `PYTHON_EXECUTABLE`, `PYTHON_MODEL_PATH`

If `AI_MODEL_URL` is empty, the app uses the local Python bridge. The configured `PYTHON_MODEL_PATH` uses `/python/predict.py`, but the actual directory is `PYTHON`. Windows is case-insensitive, so it may work locally; Linux/macOS deployment can fail.

Security note: `GEMINI_API_KEY` is hard-coded. It should be moved out of source control and rotated.

### `db.php`

Loads `config.php`, creates a `mysqli` connection, aborts on connection error, sets `utf8mb4`, and returns the connection. It emits a JSON error even when called by an HTML page such as `index.php`, which is acceptable for API contexts but a little awkward for browser pages.

### `index.php`

Responsibilities:

- Starts session.
- Opens DB connection.
- Handles login form POST.
- Verifies submitted `student_id` against `students`.
- Stores `$_SESSION['student_id']`.
- Stores optional `$_SESSION['semesters_completed']`.
- Handles logout with `?logout=1`.
- Fetches student profile and joined course history.
- Groups course history by year and semester.
- Renders login modal if no active student.
- Renders course history if student exists.
- Provides "See Results" link to `results.php`.
- Includes inline theme toggle logic using `localStorage`.

Important detail: grouping expects `courses.semester` to be numeric because it casts `semester` to integer and calculates year/season. Current CSV/schema uses labels such as `1st fall`, `2nd spring`. In PHP, `(int) '1st fall'` becomes `1`, but `(int) '2nd spring'` becomes `2`, so this happens to work for the current labels. If labels changed to non-leading-number text, grouping would fail.

The page only displays course ID, semester, and letter grade. It fetches course name but does not display it.

### `results.php`

Responsibilities:

- Starts session.
- Renders the recommendation UI shell.
- Initializes the theme from `localStorage`.
- Fetches recommendation JSON from `api/recommend.php`.
- Reads `student_id` from the URL, falling back to `1001`.
- Adds `semesters_completed` from PHP session if available.
- Shows loading, error, or results state.
- Fills student name, student number, advisor, recommendation table, and reason text.
- Generates a PDF using jsPDF.

The frontend expects the API response shape:

```json
{
  "student": { "name": "...", "number": "...", "advisor": "..." },
  "courses": [{ "code": "...", "name": "..." }],
  "reason": "..."
}
```

The PDF is generated entirely client-side. It uses a professional single-page report style with title, student profile, recommended courses, advisor insights, and footer. It does not include detailed probabilities, risk flags, retry flags, or blocked courses, even though the Python engine can produce those internally.

### `api/recommend.php`

This is the active recommendation API.

Steps:

1. Sets JSON response headers and permissive CORS.
2. Loads `config.php` and `db.php`.
3. Validates `student_id` from query string.
4. Fetches the student row.
5. Queries distinct course semester labels for completed student records.
6. Maps semester labels to integer positions 1 through 8.
7. Allows `semesters_completed` query override.
8. Fetches completed course rows for response/debug use.
9. Builds payload:

```php
[
  'student_id' => $studentId,
  'semester' => $completedSemesters,
]
```

10. Calls remote model if `AI_MODEL_URL` is set, otherwise local Python bridge.
11. Converts Python `recommended_courses` into frontend `courses`.
12. Takes reason from `reason` or `explanation`.
13. Rejects empty recommendation lists.
14. Deletes previous cached recommendations for the student.
15. Inserts new records into `ai_recommendations`.
16. Returns JSON to the page.

The comments say "completed_semesters", but the actual local payload key is `semester`. That matches `PYTHON/predict.py`, so the code works locally.

### `api/python_bridge.php`

Spawns a Python process:

```text
python <PYTHON_MODEL_PATH>
```

It writes JSON payload to stdin, reads stdout and stderr, waits for the process, and decodes stdout as JSON. If the exit code is non-zero or JSON decoding fails, it throws an exception.

This is a simple and useful bridge. It does not enforce a timeout, so a hung Python process or long Gemini call could hang the PHP request.

### Legacy PHP files

`recommend.php` and `recommend_gemini.php` are older direct Gemini endpoints. They:

- Fetch completed and available courses from MySQL.
- Build a textual prompt.
- Call Gemini directly from PHP via curl.
- Expect Gemini to return JSON containing top 3 courses and a reason.
- Cache those results in `ai_recommendations`.

These files do not use the Python Random Forest or prerequisite graph. They are not the path used by `results.php`, which fetches `api/recommend.php`.

`api/mock_model.deprecated.php` simulates a model endpoint with hard-coded next-semester recommendations. It expects POST JSON using `completed_semesters`, while the current local bridge uses `semester`.

`scratch/test_bridge.php` is a manual PHP CLI test for `call_python_model()` using student `1001` and semester `4`.

## 5. Frontend and Styling

### `style.css`

The design system uses CSS custom properties:

- Dark theme defaults under `:root`.
- Light theme under `[data-theme="light"]`.
- Shared variables for background, surface, border, text, muted text, accent, grade colors, overlays, modal background, input background, and transitions.

Major UI pieces:

- fixed circular theme switch
- centered `.container`
- header and student chip
- course-history preview card
- year/semester accordion groups
- responsive table wrapper
- grade styling
- buttons and action rows
- empty state
- login modal and form

### `app.js` and `students.js`

These belong to an older mock-only version of the app. `students.js` defines:

- `window.STUDENT_RECORDS`
- `window.CURRENT_STUDENT_ID`

`app.js`:

- filters those mock records by current student
- renders them into `#courses-tbody`
- handles empty state
- generates a mock PDF with hard-coded student profile and course recommendations

The current `index.php` does not include `students.js`, `app.js`, or a `courses-tbody` element, so this path is effectively unused.

## 6. Database and Data

### Current schema: `create_tables.sql`

Creates database `capstonef` and these tables:

- `courses`
- `students`
- `prerequisites`
- `student_records`
- `ai_recommendations`

`courses`:

- primary key `course_id`
- stores `course_name`, `description`, `credits`, `category`, `semester`

`students`:

- primary key `student_id`
- stores `enrollment_year`, `gender`, `age`

`prerequisites`:

- composite primary key `(course_id, prerequisite_course_id)`
- both columns reference `courses.course_id`

`student_records`:

- auto-increment `record_id`
- `student_id`
- `course_id`
- optional `course_name`
- `final_result`
- `score`
- `letter_grade`

`ai_recommendations`:

- auto-increment `rec_id`
- `student_id`
- `course_id`
- `recommendation_reason`
- `status`: `pending`, `accepted`, `rejected`
- `created_at`

### Older schema: `sql`

This file defines a different system:

- `students` has first name, last name, advisor name.
- `student_records` has `semester_taken` and `numeric_score`.
- `ai_recommendations` uses `recommendation_id` and statuses `approved_by_advisor`, `rejected`.

It does not match the current CSVs or current PHP queries, so it should be treated as obsolete unless intentionally revived.

### CSV datasets

Root CSV files:

- `courses.csv`: 44 rows, columns `course_id,course_name,description,credits,category,semester`
- `prerequisites.csv`: 6 rows, columns `course_id,prerequisite_course_id`
- `students.csv`: 4000 rows, columns `student_id,enrollment_year,gender,age`
- `student_records.csv`: 25020 rows, columns `student_id,course_name,course_id,final_result,score,letter_grade`

Python CSV files:

- `PYTHON/data/courses.csv`: 44 rows, same header as root
- `PYTHON/data/prerequisites.csv`: 29 rows, more complete than root
- `PYTHON/data/students.csv`: 4000 rows, same header as root
- `PYTHON/data/student_records.csv`: 24464 rows, cleaned/changed scores and grades compared with root
- `PYTHON/data/student_records_old.csv`: 25020 rows, same row count as root

The model training notebook shows `PYTHON/data/student_records.csv` produced 24464 model samples and a 77.1% pass rate.

### `generate_inserts.php`

Generates `insert_sample_data.sql` from root CSV files. It:

- Opens output file.
- Writes database selection and table truncation statements.
- Reads `courses.csv` into one insert block.
- Reads `students.csv` in batches of 500.
- Reads `prerequisites.csv` into one insert block.
- Reads `student_records.csv` in batches of 500.
- Skips invalid records with empty student ID or course ID `0`.

The script uses `addslashes()` for SQL escaping rather than prepared statements because it is generating a static SQL file. For a seed-file generator this is understandable, but it is not robust SQL serialization.

## 7. Python Recommendation Engine

The modular engine lives under `PYTHON/engine`.

### `engine/config.py`

Loads `.env` via `python-dotenv` and reads:

- `GEMINI_API_KEY`
- `DATA_DIR`
- `MODEL_DIR`

No default fallback paths are defined. If `.env` is missing or incomplete, `Path(None)` or model loading can fail.

### `engine/constants.py`

Defines shared constants:

- `SEMESTER_ORDER`: maps labels like `1st fall` to integers 1 through 8.
- `SEMESTER_LABEL`: reverse mapping.
- `PASS_RESULTS`: `Pass`, `Distinction`
- `FAIL_RESULTS`: `Fail`
- `WD_RESULTS`: `Withdrawn`
- `MAX_CREDITS`: 16
- `RISK_THRESHOLD`: 0.45

### `engine/data.py`

Loads CSV files into pandas DataFrames:

- `courses.csv` using Latin-1 encoding.
- `prerequisites.csv`
- `student_records.csv`
- `students.csv`

It creates `name_to_id` and `id_to_name` dictionaries.

It cleans `records.course_id` by stripping whitespace, drops rows missing `course_id` or `student_id`, casts `student_id` to `int`, filters out records whose `course_id` is not present in the course catalog, and adds:

- `sem_num`: numeric semester derived from the course catalog
- `gpa_points`: mapped from `letter_grade`

Important mapping behavior:

- `Pass` maps to `2.0`
- `Distinction` maps to `4.0`
- `Fail` maps to `0.0`
- `Withdrawn` maps to `NaN`
- `A/B/C/D/F` map to 4/3/2/1/0

The `get_student_records()` helper is the simulation choke point. It filters a student's records by `sem_num <= sim_max_semester` when simulation mode is active.

### `engine/features.py`

Builds a student's feature dictionary from visible records:

- demographics: enrollment year, gender, age
- counts: taken, passed, failed, withdrawn
- rates: pass rate, fail rate
- performance: GPA, average score, score stddev, min score, max score
- inferred semester
- sets of passed, failed, withdrawn, and all taken course IDs

`vectorise_for_ml()` converts the dictionary into numeric model input:

1. enrollment year
2. age
3. courses taken
4. courses passed
5. courses failed
6. courses withdrawn
7. pass rate
8. fail rate
9. GPA
10. average score
11. score standard deviation
12. min score
13. max score
14. inferred semester
15. gender encoded as 1 for male, 0 otherwise

The final model feature vector adds three course features, making 18 total features.

### `engine/graph.py`

Builds a directed course graph with NetworkX:

- Node: course ID, with name, semester, credits, category.
- Edge: prerequisite course to dependent course.

`get_available_courses()` returns not-yet-taken courses for which all graph predecessors are in the student's passed set.

`get_blocked_courses()` returns not-yet-taken courses with missing prerequisites.

`validate_graph()` checks the graph is a DAG and raises a cycle error if needed.

Data-quality note: NetworkX adds nodes implicitly when adding an edge for an unknown course ID. In `PYTHON/data/prerequisites.csv`, `CMPE321` and `MATH102 ` can become graph nodes without course catalog metadata.

### `engine/model.py`

`load_model()` loads:

- `pass_predictor.pkl`
- `course_graph.pkl`

from `MODEL_DIR`.

`build_training_dataset()` creates model rows. For each student, it builds full-record student features, then for each student record it concatenates student features with course features:

- semester number
- credits
- category encoded as core 0, elective 1

Target `y` is 1 for `Pass` or `Distinction`, 0 for `Fail` or `Withdrawn`.

`train_pass_predictor()` trains:

- `RandomForestClassifier`
- `n_estimators=200`
- `max_depth=8`
- `min_samples_leaf=10`
- `class_weight='balanced'`
- `random_state=42`
- `n_jobs=-1`

It uses an 80/20 stratified split and returns a `classification_report`.

`predict_pass_probability()` builds the same 18-feature vector for one student-course pair and returns `clf.predict_proba(...)[0][1]` rounded to 3 decimals.

### `engine/recommender.py`

This is the core ranking pipeline.

For one student:

1. Build student features, respecting optional simulation semester.
2. Pull passed, failed, withdrawn, and all-taken course sets.
3. Get available not-yet-taken courses whose prerequisites are satisfied.
4. Add retry candidates from failed/withdrawn courses whose prerequisites are satisfied.
5. Score every candidate with pass probability.
6. Compute priority:

```text
priority =
  p_pass * 0.50
  + 0.20 if core course
  + 0.15 if retry
  + 0.15 / (1 + abs(course_semester - inferred_student_semester))
```

7. Sort by priority descending.
8. Select courses until either `top_n` is reached or the 16-credit cap would be exceeded.
9. Return student profile, selected courses, total credits, retry courses, risky courses, and the first five blocked upcoming courses.

The default `top_n` is 6. Because 0-credit courses count as zero, they can be included without affecting the 16-credit cap.

### `engine/llm.py`

Builds a Gemini prompt from recommendation output and calls Gemini.

The system prompt instructs the model to:

- act as an academic advisor for an AI Engineering department
- recommend based on history, prerequisites, and pass probability
- only recommend courses whose prerequisites are fully passed
- flag retakes
- warn for pass probability below 45%
- keep explanation short and encouraging
- avoid recommending Summer Training unless inferred semester is 6 or higher

Important nuance: the Summer Training rule is only in the LLM explanation prompt. The actual recommender can still select `AIEN300`; the prompt tells Gemini not to mention it if the student is not eligible. That means the frontend table may still display it because the table uses the raw course list from Python.

`call_gemini()` uses `google.generativeai`, model name `gemini-2.5-flash`, and the `.env` API key.

### `PYTHON/predict.py`

This is the PHP-facing Python entry point.

It:

- reads JSON from stdin
- extracts `student_id` and `semester`
- loads data from `DATA_DIR`
- loads model and graph from `MODEL_DIR`
- calls `recommend_courses()`
- builds an LLM prompt
- calls Gemini for an explanation
- returns JSON:

```json
{
  "recommended_courses": [],
  "explanation": "...",
  "student_profile": {}
}
```

If Gemini fails, it still returns recommendations with an explanation like `Reasoning currently unavailable`. If broader exceptions happen, it prints `{"error": "..."}` but does not exit non-zero.

### `PYTHON/train.py`

Training script:

1. Loads data.
2. Builds prerequisite graph.
3. Validates graph as acyclic.
4. Builds training dataset.
5. Trains Random Forest.
6. Saves model and graph pickle files into `MODEL_DIR`.

### `PYTHON/main.py`

Small demo script that loads data/model, recommends for student `1001` with `sim_max_semester=4`, builds a prompt, calls Gemini, and prints the explanation.

### `PYTHON/full_recommendation_engine.py`

A monolithic version of the modular engine. It contains constants, data loading, simulation helper, graph logic, feature engineering, model training, recommendation ranking, LLM prompt creation, and a demo `__main__` block. It is useful as documentation/history, but the active PHP bridge imports the modular package instead.

### `PYTHON/notebooks/demo.ipynb`

Notebook version of the training and demo flow. Its captured output shows:

- graph built with 46 nodes and 29 edges
- training dataset with 24464 samples and 18 features
- pass rate 77.1%
- Random Forest report:
  - fail/withdrawn precision 0.56, recall 0.83, F1 0.67
  - pass precision 0.94, recall 0.81, F1 0.87
  - accuracy 0.81

The notebook also demonstrates recommendations for student `1001` in simulation and full-record modes. It exposes the data-quality issue where `CMPE321` and `MATH102 ` appear as graph nodes/courses even though they are not valid catalog entries.

## 8. Model Artifacts and Generated Files

### Pickle files

- `PYTHON/models/pass_predictor.pkl`: serialized Random Forest classifier, about 5.5 MB.
- `PYTHON/models/course_graph.pkl`: serialized NetworkX course graph, about 4 KB.

They are loaded at runtime by `engine/model.py`. Pickle files should only be loaded from trusted sources.

### Python bytecode

`PYTHON/engine/__pycache__/*` files are generated `.pyc` caches for Python 3.12 and 3.14. They are not source and can be regenerated.

### `insert_sample_data.sql`

Generated by `generate_inserts.php` from root CSVs. It truncates tables and inserts all seed data. Its size comes mainly from 4000 students and 25020 student records.

## 9. End-to-End Data Contract

Browser to PHP:

- `index.php` login POST:
  - `student_id`
  - optional `semesters_completed`
- `results.php` API fetch query:
  - `student_id`
  - optional `semesters_completed`

PHP API to Python:

```json
{
  "student_id": "1001",
  "semester": 4
}
```

Python to PHP:

```json
{
  "recommended_courses": [
    {
      "course_id": "CMPE214",
      "course_name": "VISUAL PROGRAMMING",
      "semester_slot": "4th spring",
      "credits": 3,
      "category": "core",
      "p_pass": 0.727,
      "priority": 0.8635,
      "is_retry": true,
      "risk": false
    }
  ],
  "explanation": "...",
  "student_profile": {
    "gpa": 2.682,
    "avg_score": 71.87,
    "pass_rate": 0.9565,
    "courses_passed": 22,
    "courses_failed": 1,
    "courses_withdrawn": 0,
    "inferred_semester": 4
  }
}
```

PHP API to browser:

```json
{
  "student": {
    "name": "Student 1001",
    "number": "1001",
    "advisor": "N/A"
  },
  "completed_semesters": 4,
  "courses": [
    { "code": "CMPE214", "name": "VISUAL PROGRAMMING" }
  ],
  "reason": "...",
  "completed_courses": []
}
```

## 10. Recommendation Logic in Plain English

The system first decides what the student has already done. If simulation mode is enabled, it pretends future semesters do not exist and only looks at course records up to the chosen semester number.

From that visible record, it calculates academic statistics: pass rate, GPA, average score, failures, withdrawals, and inferred semester position. It also remembers which courses were passed, failed, withdrawn, or taken at all.

Then it uses the prerequisite graph. A course is available only if:

- the student has not already taken it, and
- every prerequisite node has been passed.

Previously failed or withdrawn courses can re-enter the candidate list as retry candidates if their prerequisites are satisfied.

Every candidate receives a predicted pass probability from the Random Forest. The final priority score blends:

- predicted success probability,
- preference for core courses,
- preference for retaking failed/withdrawn courses,
- closeness to the student's current semester position.

The recommender sorts candidates by priority and selects up to six courses while staying under the 16-credit limit.

Finally, Gemini turns the structured recommendation into human-readable advising prose.

## 11. Important Issues and Risks

### Security

- `config.php` contains a hard-coded Gemini API key. Rotate it and load it from environment variables.
- `Access-Control-Allow-Origin: *` on `api/recommend.php` allows any origin to call the endpoint.
- Pickle loading is unsafe if model files are ever replaced by untrusted content.
- `generate_inserts.php` writes SQL with `addslashes()` rather than robust SQL escaping.

### Configuration

- `PYTHON_MODEL_PATH` uses lowercase `python`, but folder is uppercase `PYTHON`.
- `.env` is required by Python but not documented with an example file in the repo.
- PHP and Python have separate Gemini configuration, which can drift.

### Data consistency

- Root and Python data copies differ.
- Root prerequisites have only 6 edges; Python prerequisites have 29.
- Python prerequisite data includes unknown or malformed IDs: `CMPE321`, `MATH102 `.
- `student_records.score` is a string in MySQL schema and can contain `#N/A`; Python expects numeric-ish values after pandas parsing.

### Product behavior

- The API can return up to 6 recommendations, but some UI copy and old docs mention top 3.
- Summer Training filtering is handled in the LLM prompt, not the actual recommendation list.
- The frontend does not show pass probabilities, credit totals, risk flags, retry flags, or blocked prerequisites.
- `results.php` falls back to student `1001` if no query parameter exists, which can mask missing navigation state.

### Error handling

- Python prints `{"error": "..."}` for broad exceptions but returns exit code 0.
- PHP bridge has no timeout.
- Database errors in `db.php` are always emitted as JSON, even from HTML pages.
- `api/recommend.php` does not validate that inserted recommendation course IDs exist beyond relying on DB foreign keys.

### Maintainability

- There are duplicate/legacy implementations that can confuse future changes.
- README has mojibake characters, likely encoding damage.
- Some comments mention older assumptions, such as numeric semesters or Gemini 1.5 Flash, while code uses Gemini 2.0/2.5 in different places.

## 12. Suggested Cleanup Roadmap

1. Move secrets out of `config.php`, rotate the exposed Gemini key, and add `.env.example`.
2. Fix `PYTHON_MODEL_PATH` casing and add explicit default paths or clear startup validation.
3. Choose one canonical data directory and remove or document duplicate CSV copies.
4. Clean `PYTHON/data/prerequisites.csv` by trimming IDs and removing/replacing unknown `CMPE321`.
5. Enforce Summer Training eligibility in `engine/recommender.py`, not only in the Gemini prompt.
6. Decide whether recommendations should be top 3 or top 6, then align API, UI, docs, and PDF.
7. Remove or archive old files: `app.js`, `students.js`, root `recommend.php`, `recommend_gemini.php`, `sql`, and old readme, if no longer needed.
8. Add tests for:
   - semester simulation filtering
   - prerequisite availability
   - retry candidate inclusion
   - credit cap selection
   - API output normalization
9. Add PHP/Python bridge timeout and make Python exit non-zero for fatal errors.
10. Surface richer model output in the UI: pass probabilities, risk flags, retry labels, credits, and blocked prerequisite notes.

## 13. File-by-File Role Table

| Path | Role | Active? |
| --- | --- | --- |
| `README.md` | Main project documentation | Yes |
| `softwaresubsystem.readme` | Older simplified docs | Legacy |
| `config.php` | PHP constants for DB, APIs, model bridge | Yes |
| `db.php` | Shared MySQL connection helper | Yes |
| `index.php` | Login and course history page | Yes |
| `results.php` | Results UI, API fetch, PDF generation | Yes |
| `style.css` | Shared theme and layout CSS | Yes |
| `api/recommend.php` | Current recommendation API | Yes |
| `api/python_bridge.php` | PHP process bridge to Python | Yes |
| `PYTHON/predict.py` | Python entry point for PHP | Yes |
| `PYTHON/engine/config.py` | Python environment config | Yes |
| `PYTHON/engine/constants.py` | Shared Python constants | Yes |
| `PYTHON/engine/data.py` | CSV loading and cleanup | Yes |
| `PYTHON/engine/features.py` | Student feature engineering | Yes |
| `PYTHON/engine/graph.py` | Course prerequisite graph helpers | Yes |
| `PYTHON/engine/model.py` | Model loading/training/prediction helpers | Yes |
| `PYTHON/engine/recommender.py` | Core recommendation ranking | Yes |
| `PYTHON/engine/llm.py` | Gemini prompt and call | Yes |
| `PYTHON/train.py` | Model/graph training script | Support |
| `PYTHON/main.py` | Local demo runner | Support |
| `PYTHON/requirements.txt` | Python dependencies | Yes |
| `PYTHON/models/*.pkl` | Runtime trained artifacts | Yes |
| `create_tables.sql` | Current DB schema | Yes |
| `generate_inserts.php` | Seed SQL generator | Support |
| `insert_sample_data.sql` | Generated full seed data | Support |
| `insert_courses.sql` | Course seed data only | Support |
| `courses.csv` | Root course data | Data |
| `prerequisites.csv` | Root prerequisite data | Data |
| `students.csv` | Root student data | Data |
| `student_records.csv` | Root record data | Data |
| `PYTHON/data/*.csv` | Python engine data | Data |
| `PYTHON/notebooks/demo.ipynb` | Exploration/training/demo notebook | Support |
| `PYTHON/full_recommendation_engine.py` | Monolithic historical engine | Legacy/support |
| `recommend.php` | Old direct Gemini endpoint | Legacy |
| `recommend_gemini.php` | Duplicate old direct Gemini endpoint | Legacy |
| `api/mock_model.deprecated.php` | Hard-coded mock endpoint | Legacy |
| `app.js` | Old static mock frontend logic | Legacy |
| `students.js` | Old static mock data | Legacy |
| `scratch/test_bridge.php` | Manual bridge test | Support |
| `sql` | Old incompatible schema | Legacy |
| `PYTHON/engine/__pycache__/*` | Generated Python bytecode | Generated |

## 14. Bottom Line

The project is an academic advising system with a sensible layered design: PHP/MySQL for web and records, Python for data science, NetworkX for prerequisite validity, Random Forest for pass probability, and Gemini for explanation. The core algorithm is understandable and modular. The biggest next step is not inventing more architecture; it is tightening configuration, removing stale duplicate paths, aligning the datasets, and enforcing policy rules inside deterministic code before the LLM explanation layer.
