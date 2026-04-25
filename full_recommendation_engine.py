"""
Academic Path Recommendation Engine  –  Simulation-Ready
==========================================================
Supports:
  - Real SIS data  OR  simulated student snapshots (any semester cutoff)
  - Course graph (prerequisite-aware DAG)
  - Student feature vectors
  - Random Forest pass-probability predictor
  - LLM-ready structured output (Gemini 1.5 Flash)

Key design principle:
  Every function that touches a student's history accepts an optional
  `sim_max_semester` parameter.  Pass an integer (1–8) to restrict that
  student's visible record to courses <= that semester, simulating a
  student who has only completed that many semesters.
  Leave it None to use the full real record.
"""

import pandas as pd
import numpy as np
import networkx as nx
import json
import warnings
import pickle
from pathlib import Path

from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report

warnings.filterwarnings("ignore")

# ─────────────────────────────────────────────────────────────────────────────
# CONSTANTS
# ─────────────────────────────────────────────────────────────────────────────

SEMESTER_ORDER = {
    "1st fall": 1, "2nd spring": 2, "3rd fall": 3,  "4th spring": 4,
    "5th fall": 5, "6th spring": 6, "7th fall": 7,  "8th spring": 8,
}
SEMESTER_LABEL = {v: k for k, v in SEMESTER_ORDER.items()}

PASS_RESULTS   = {"Pass", "Distinction"}
FAIL_RESULTS   = {"Fail"}
WD_RESULTS     = {"Withdrawn"}

MAX_CREDITS    = 16      # hard credit cap per semester
RISK_THRESHOLD = 0.45   # P(pass) below this -> flag as risky


# ─────────────────────────────────────────────────────────────────────────────
# 1. DATA LOADING
# ─────────────────────────────────────────────────────────────────────────────

def load_data(data_dir: str = ".") -> dict:
    """
    Loads all four CSV files and returns a dict of DataFrames plus lookup maps.
    course_id in student_records is stored as the course NAME -- this normalises
    it to the short code (e.g. 'CMPE111') used everywhere else.
    """
    p = Path(data_dir)
    courses  = pd.read_csv(p / "courses.csv",         encoding="latin1")
    prereqs  = pd.read_csv(p / "prerequisites.csv")
    records  = pd.read_csv(p / "student_records.csv")
    students = pd.read_csv(p / "students.csv")

    name_to_id = dict(zip(courses["course_name"], courses["course_id"]))
    id_to_name = dict(zip(courses["course_id"], courses["course_name"]))

    # course_id is now a real code column — just clean whitespace
    records["course_id"] = records["course_id"].str.strip()
    records = records.dropna(subset=["course_id", "student_id"])
    records["student_id"] = records["student_id"].astype(int)
    # Drop rows whose course_id isn't in the catalogue (e.g. placeholder '0')
    valid_ids = set(courses["course_id"])
    records = records[records["course_id"].isin(valid_ids)]

    # Attach semester number to every record row for easy filtering
    course_sem_map = dict(zip(courses["course_id"], courses["semester"]))
    records["sem_num"] = records["course_id"].map(
        lambda c: SEMESTER_ORDER.get(course_sem_map.get(c, ""), 0)
    )

    GRADE_MAP = {
        "Pass": 2.0, "Distinction": 4.0,
        "Fail": 0.0, "Withdrawn": np.nan,
        "A": 4.0, "B": 3.0, "C": 2.0, "D": 1.0, "F": 0.0,
    }
    records["gpa_points"] = records["letter_grade"].map(GRADE_MAP)

    return {
        "courses":    courses,
        "prereqs":    prereqs,
        "records":    records,
        "students":   students,
        "name_to_id": name_to_id,
        "id_to_name": id_to_name,
    }


# ─────────────────────────────────────────────────────────────────────────────
# 2. SIMULATION HELPER  <-- the key addition
# ─────────────────────────────────────────────────────────────────────────────

def get_student_records(student_id: int,
                        records: pd.DataFrame,
                        sim_max_semester: int = None) -> pd.DataFrame:
    """
    Returns the visible academic record for a student.

    sim_max_semester:
        None  -> use every row in the dataset (real / full mode)
        int   -> restrict to courses whose semester number <= that value
                 e.g. sim_max_semester=4  simulates a student who has just
                 finished their 4th semester (end of year 2)

    This is the single choke-point for simulation -- all downstream
    functions call this instead of filtering records themselves.
    """
    sr = records[records["student_id"] == student_id].copy()
    if sim_max_semester is not None:
        sr = sr[sr["sem_num"] <= sim_max_semester]
    return sr


# ─────────────────────────────────────────────────────────────────────────────
# 3. COURSE GRAPH
# ─────────────────────────────────────────────────────────────────────────────

def build_course_graph(courses: pd.DataFrame,
                       prereqs: pd.DataFrame) -> nx.DiGraph:
    """
    Directed Acyclic Graph of courses.
      Node  = course_id with attributes (name, semester, credits, category)
      Edge  = prerequisite -> dependent  (A -> B: pass A before B)
    """
    G = nx.DiGraph()
    for _, row in courses.iterrows():
        G.add_node(row["course_id"],
                   name=row["course_name"],
                   semester=row["semester"],
                   credits=int(row["credits"]),
                   category=row["category"])
    for _, row in prereqs.iterrows():
        G.add_edge(row["prerequisite_course_id"], row["course_id"])
    return G


def get_available_courses(G: nx.DiGraph,
                          passed_ids: set,
                          already_taken: set) -> list:
    """Courses the student has not taken yet AND whose prereqs are all passed."""
    return [
        n for n in G.nodes()
        if n not in already_taken
        and all(p in passed_ids for p in G.predecessors(n))
    ]


def get_blocked_courses(G: nx.DiGraph,
                        passed_ids: set,
                        already_taken: set,
                        courses: pd.DataFrame) -> list:
    """Courses blocked by unmet prerequisites."""
    blocked = []
    id_to_name = dict(zip(courses["course_id"], courses["course_name"]))
    for n in G.nodes():
        if n in already_taken:
            continue
        missing = [p for p in G.predecessors(n) if p not in passed_ids]
        if missing:
            blocked.append({
                "course_id":       n,
                "course_name":     id_to_name.get(n, n),
                "missing_prereqs": [id_to_name.get(m, m) for m in missing],
            })
    return blocked


# ─────────────────────────────────────────────────────────────────────────────
# 4. STUDENT FEATURE VECTOR
# ─────────────────────────────────────────────────────────────────────────────

def build_student_features(student_id: int,
                            records: pd.DataFrame,
                            students: pd.DataFrame,
                            sim_max_semester: int = None) -> dict:
    """
    Builds a feature dictionary for a student.
    Pass sim_max_semester to simulate a student at a specific semester cutoff.
    """
    sr   = get_student_records(student_id, records, sim_max_semester)
    info = students[students["student_id"] == student_id]

    if sr.empty:
        return {}

    passed    = sr[sr["final_result"].isin(PASS_RESULTS)]
    failed    = sr[sr["final_result"].isin(FAIL_RESULTS)]
    withdrawn = sr[sr["final_result"].isin(WD_RESULTS)]
    scores    = sr[sr["score"].notna()]

    gpa = passed["gpa_points"].mean() if not passed.empty else 0.0
    inferred_sem = int(sr["sem_num"].max()) if not sr.empty else 0

    return {
        # identifiers
        "student_id":          student_id,
        "sim_max_semester":    sim_max_semester,

        # demographics
        "enrollment_year":     int(info["enrollment_year"].values[0]) if not info.empty else None,
        "gender":              info["gender"].values[0] if not info.empty else None,
        "age":                 int(info["age"].values[0]) if not info.empty else None,

        # academic summary
        "courses_taken":       len(sr),
        "courses_passed":      len(passed),
        "courses_failed":      len(failed),
        "courses_withdrawn":   len(withdrawn),
        "pass_rate":           len(passed) / len(sr) if sr.shape[0] else 0.0,
        "fail_rate":           len(failed) / len(sr) if sr.shape[0] else 0.0,
        "gpa":                 round(gpa, 3),
        "avg_score":           round(scores["score"].mean(), 2) if not scores.empty else 0.0,
        "std_score":           round(scores["score"].std(),  2) if len(scores) > 1  else 0.0,
        "min_score":           float(scores["score"].min())     if not scores.empty else 0.0,
        "max_score":           float(scores["score"].max())     if not scores.empty else 0.0,
        "inferred_semester":   inferred_sem,

        # course sets (used by graph logic, stripped before ML)
        "passed_ids":          set(passed["course_id"].unique()),
        "failed_ids":          set(failed["course_id"].unique()),
        "withdrawn_ids":       set(withdrawn["course_id"].unique()),
        "all_taken_ids":       set(sr["course_id"].unique()),
    }


def vectorise_for_ml(features: dict) -> np.ndarray:
    """Flat numeric array for the ML model (excludes set fields)."""
    numeric = [
        "enrollment_year", "age",
        "courses_taken", "courses_passed", "courses_failed", "courses_withdrawn",
        "pass_rate", "fail_rate", "gpa",
        "avg_score", "std_score", "min_score", "max_score", "inferred_semester",
    ]
    gender = 1 if features.get("gender") == "M" else 0
    return np.array(
        [features.get(k, 0) or 0 for k in numeric] + [gender],
        dtype=float
    )


# ─────────────────────────────────────────────────────────────────────────────
# 5. PASS PREDICTOR  (Random Forest)
# ─────────────────────────────────────────────────────────────────────────────

def build_training_dataset(records: pd.DataFrame,
                            students: pd.DataFrame,
                            courses: pd.DataFrame):
    """
    Build (X, y) from full historical data.
    Training ALWAYS uses the full record (sim_max_semester=None) so the
    model learns from all available signal.
    """
    course_sem_map  = dict(zip(courses["course_id"], courses["semester"]))
    course_cred_map = dict(zip(courses["course_id"], courses["credits"]))
    course_cat_map  = dict(zip(courses["course_id"], courses["category"]))
    cat_enc = {"core": 0, "elective": 1}

    all_X, all_y = [], []

    for sid in records["student_id"].unique():
        feats = build_student_features(sid, records, students, sim_max_semester=None)
        if not feats:
            continue
        x_student = vectorise_for_ml(feats)

        sr = records[records["student_id"] == sid]
        for _, row in sr.iterrows():
            cid    = row["course_id"]
            result = row["final_result"]
            if result not in (PASS_RESULTS | FAIL_RESULTS | WD_RESULTS):
                continue
            y        = 1 if result in PASS_RESULTS else 0
            sem_num  = SEMESTER_ORDER.get(course_sem_map.get(cid, ""), 0)
            credits  = int(course_cred_map.get(cid, 3))
            cat      = cat_enc.get(course_cat_map.get(cid, "core"), 0)
            x_course = np.array([sem_num, credits, cat], dtype=float)
            all_X.append(np.concatenate([x_student, x_course]))
            all_y.append(y)

    return np.array(all_X), np.array(all_y)


def train_pass_predictor(X: np.ndarray, y: np.ndarray):
    X_tr, X_te, y_tr, y_te = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )
    clf = RandomForestClassifier(
        n_estimators=200, max_depth=8, min_samples_leaf=10,
        class_weight="balanced", random_state=42, n_jobs=-1,
    )
    clf.fit(X_tr, y_tr)
    report = classification_report(y_te, clf.predict(X_te),
                                   target_names=["Fail/WD", "Pass"])
    return clf, report


def predict_pass_probability(clf,
                              student_features: dict,
                              course_id: str,
                              courses: pd.DataFrame) -> float:
    course_sem_map  = dict(zip(courses["course_id"], courses["semester"]))
    course_cred_map = dict(zip(courses["course_id"], courses["credits"]))
    course_cat_map  = dict(zip(courses["course_id"], courses["category"]))
    cat_enc = {"core": 0, "elective": 1}

    sem_num = SEMESTER_ORDER.get(course_sem_map.get(course_id, ""), 0)
    credits = int(course_cred_map.get(course_id, 3))
    cat     = cat_enc.get(course_cat_map.get(course_id, "core"), 0)

    x = np.concatenate([
        vectorise_for_ml(student_features),
        np.array([sem_num, credits, cat], dtype=float),
    ]).reshape(1, -1)

    return round(float(clf.predict_proba(x)[0][1]), 3)


# ─────────────────────────────────────────────────────────────────────────────
# 6. RECOMMENDATION ENGINE
# ─────────────────────────────────────────────────────────────────────────────

def recommend_courses(student_id: int,
                      G: nx.DiGraph,
                      clf,
                      records: pd.DataFrame,
                      students: pd.DataFrame,
                      courses: pd.DataFrame,
                      sim_max_semester: int = None,
                      top_n: int = 6) -> dict:
    """
    Full recommendation pipeline for one student.

    sim_max_semester
        None -> use real / full records
        int  -> simulate a student who has only finished that many semesters
    """
    feats = build_student_features(student_id, records, students, sim_max_semester)
    if not feats:
        return {"error": f"No records found for student {student_id}"}

    passed_ids    = feats["passed_ids"]
    all_taken     = feats["all_taken_ids"]
    failed_ids    = feats["failed_ids"]
    withdrawn_ids = feats["withdrawn_ids"]

    available = get_available_courses(G, passed_ids, all_taken)
    retry_candidates = [
        cid for cid in (failed_ids | withdrawn_ids)
        if all(p in passed_ids for p in G.predecessors(cid))
    ]
    all_candidates = list(set(available + retry_candidates))

    course_sem_map  = dict(zip(courses["course_id"], courses["semester"]))
    course_cred_map = dict(zip(courses["course_id"], courses["credits"]))
    course_cat_map  = dict(zip(courses["course_id"], courses["category"]))
    id_to_name      = dict(zip(courses["course_id"], courses["course_name"]))

    scored = []
    for cid in all_candidates:
        p_pass   = predict_pass_probability(clf, feats, cid, courses)
        is_retry = cid in (failed_ids | withdrawn_ids)
        is_core  = course_cat_map.get(cid, "core") == "core"
        sem_num  = SEMESTER_ORDER.get(course_sem_map.get(cid, ""), 0)
        sem_diff = abs(sem_num - feats["inferred_semester"])

        priority = (
            p_pass * 0.50
            + (0.20 if is_core  else 0.00)
            + (0.15 if is_retry else 0.00)
            + 0.15 / (1 + sem_diff)
        )

        scored.append({
            "course_id":     cid,
            "course_name":   id_to_name.get(cid, cid),
            "semester_slot": course_sem_map.get(cid, ""),
            "credits":       int(course_cred_map.get(cid, 3)),
            "category":      course_cat_map.get(cid, ""),
            "p_pass":        p_pass,
            "priority":      round(priority, 4),
            "is_retry":      is_retry,
            "risk":          p_pass < RISK_THRESHOLD,
        })

    scored.sort(key=lambda x: x["priority"], reverse=True)

    selected, total_credits = [], 0
    for c in scored:
        if total_credits + c["credits"] > MAX_CREDITS:
            continue
        selected.append(c)
        total_credits += c["credits"]
        if len(selected) >= top_n:
            break

    return {
        "student_id":                   student_id,
        "sim_max_semester":             sim_max_semester,
        "mode":                         "simulation" if sim_max_semester is not None else "real",
        "student_profile": {
            "gpa":               feats["gpa"],
            "avg_score":         feats["avg_score"],
            "pass_rate":         feats["pass_rate"],
            "courses_passed":    feats["courses_passed"],
            "courses_failed":    feats["courses_failed"],
            "courses_withdrawn": feats["courses_withdrawn"],
            "inferred_semester": feats["inferred_semester"],
        },
        "recommended_courses":          selected,
        "total_recommended_credits":    total_credits,
        "courses_to_retry":             [c for c in selected if c["is_retry"]],
        "risky_courses":                [c for c in selected if c["risk"]],
        "blocked_upcoming":             get_blocked_courses(G, passed_ids, all_taken, courses)[:5],
    }


# ─────────────────────────────────────────────────────────────────────────────
# 7. LLM PROMPT BUILDER
# ─────────────────────────────────────────────────────────────────────────────

SYSTEM_PROMPT = """\
You are an academic advisor AI for an AI Engineering department.
Recommend courses for the upcoming semester based on the student's academic
history, prerequisite completion, and predicted pass probability.

Rules:
- Only recommend courses whose prerequisites are fully passed.
- Flag retake courses (previously failed or withdrawn) and explain why a retry makes sense now.
- Warn about any course with predicted pass probability below 45%.
- Keep the explanation clear and encouraging (3-5 sentences).
- End with one personalised motivational sentence.
"""

def build_llm_prompt(rec: dict) -> dict:
    """
    Returns {"system": ..., "user": ...} ready to POST to Gemini 1.5 Flash.
    """
    p       = rec["student_profile"]
    mode    = rec["mode"]
    sim_tag = (f"  [Simulation: record restricted to semester <= {rec['sim_max_semester']}]\n"
               if mode == "simulation" else "")

    courses_txt = "\n".join(
        f"  * {c['course_name']} [{c['course_id']}] | {c['credits']} cr "
        f"| {c['category']} | P(pass): {c['p_pass']*100:.0f}%"
        + ("  [RETRY]"     if c["is_retry"] else "")
        + ("  [HIGH RISK]" if c["risk"]     else "")
        for c in rec["recommended_courses"]
    )

    blocked_txt = ""
    if rec["blocked_upcoming"]:
        lines = "\n".join(
            f"  * {b['course_name']} -- missing: {', '.join(b['missing_prereqs'])}"
            for b in rec["blocked_upcoming"]
        )
        blocked_txt = f"\nCourses still blocked (unmet prerequisites):\n{lines}\n"

    next_sem = p["inferred_semester"] + 1
    user_msg = f"""\
Student ID     : {rec['student_id']}
{sim_tag}GPA            : {p['gpa']}   |   Avg score : {p['avg_score']}
Pass rate      : {p['pass_rate']*100:.0f}%   |   Courses passed: {p['courses_passed']}
Failed         : {p['courses_failed']}         |   Withdrawn     : {p['courses_withdrawn']}
Current semester position: {p['inferred_semester']}

Recommended courses for semester {next_sem} (total {rec['total_recommended_credits']} credits):
{courses_txt}
{blocked_txt}
Please provide:
1. A brief assessment of the student's academic standing.
2. Why each recommended course was selected.
3. Any warnings about retakes or high-risk courses.
4. A personalised motivational closing sentence.
"""
    return {"system": SYSTEM_PROMPT, "user": user_msg.strip()}


# ─────────────────────────────────────────────────────────────────────────────
# 8.  MAIN  --  train, then demo both modes side by side
# ─────────────────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    DATA_DIR  = "/mnt/user-data/uploads"
    MODEL_DIR = "/home/claude"

    print("Loading data ...")
    db = load_data(DATA_DIR)

    print("Building course graph ...")
    G = build_course_graph(db["courses"], db["prereqs"])
    print(f"  -> {G.number_of_nodes()} nodes, {G.number_of_edges()} edges")

    print("Building training dataset ...")
    X, y = build_training_dataset(db["records"], db["students"], db["courses"])
    print(f"  -> {X.shape[0]} samples, {X.shape[1]} features "
          f"| pass rate: {y.mean()*100:.1f}%")

    print("Training Random Forest ...")
    clf, report = train_pass_predictor(X, y)
    print(report)

    with open(f"{MODEL_DIR}/pass_predictor.pkl", "wb") as f:
        pickle.dump(clf, f)
    with open(f"{MODEL_DIR}/course_graph.pkl", "wb") as f:
        pickle.dump(G, f)

    # ── Demo A: SIMULATION MODE  (student 1001, only first 4 semesters) ──
    print("\n" + "="*64)
    print("DEMO A -- Simulation mode  (student 1001, semesters 1-4)")
    print("="*64)
    rec_sim = recommend_courses(
        student_id=1001, G=G, clf=clf,
        records=db["records"], students=db["students"], courses=db["courses"],
        sim_max_semester=4,       # end of year 2
    )
    prompt_sim = build_llm_prompt(rec_sim)
    print(json.dumps(rec_sim, indent=2, default=str))
    print("\n-- LLM user message --")
    print(prompt_sim["user"])

    # ── Demo B: REAL / FULL MODE  (no cutoff) ─────────────────────────────
    print("\n" + "="*64)
    print("DEMO B -- Real mode  (student 1001, full record)")
    print("="*64)
    rec_real = recommend_courses(
        student_id=1001, G=G, clf=clf,
        records=db["records"], students=db["students"], courses=db["courses"],
        sim_max_semester=None,    # no restriction
    )
    prompt_real = build_llm_prompt(rec_real)
    print(json.dumps(rec_real, indent=2, default=str))
    print("\n-- LLM user message --")
    print(prompt_real["user"])
