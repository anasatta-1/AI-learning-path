import pandas as pd
import numpy as np

import pickle
from pathlib import Path

from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report

from engine.features import build_student_features , vectorise_for_ml
from engine.constants import PASS_RESULTS , FAIL_RESULTS , WD_RESULTS , SEMESTER_ORDER

#---------------------------------------
def load_model(model_dir: str):
    '''
    loads saved model and graph from disk
    Raises a clear error if train.py has not been run yet
    '''
    clf_path = Path(model_dir) / 'pass_predictor.pkl'
    graph_path = Path(model_dir) / 'course_graph.pkl'

    if not clf_path.exists() or not graph_path.exists():
        raise FileNotFoundError(
            'Saved model not found. Run train.py first'
        )
    
    with open(clf_path , 'rb') as f:
        clf = pickle.load(f)
    with open(graph_path , 'rb') as f:
        G = pickle.load(f)

    print('Model and graph loaded successfully')
    return clf , G

#---------------------------------------
def build_training_dataset(records: pd.DataFrame,
                           students: pd.DataFrame,
                           courses: pd.DataFrame):
    '''
    Build (X, y) from full historical data.
    Training ALWAYS uses the full record (sim_max_semester=None) so the
    model learns from all available signal
    '''

    course_sem_map = dict(zip(courses['course_id'] , courses['semester']))
    course_cred_map = dict(zip(courses['course_id'] , courses['credits']))
    course_cat_map = dict(zip(courses['course_id'] , courses['category']))
    cat_enc = {'core': 0 , 'elective': 1}

    all_X , all_y = [] , []

    for sid in records['student_id'].unique():
        feats = build_student_features(sid , records , students , sim_max_semester = None)
        if not feats:
            continue
        x_student = vectorise_for_ml(feats)

        sr = records[records['student_id'] == sid]
        for _, row in sr.iterrows():
            cid = row['course_id']
            result = row['final_result']
            if result not in (PASS_RESULTS | FAIL_RESULTS | WD_RESULTS):
                continue
            y = 1 if result in PASS_RESULTS else 0
            sem_num = SEMESTER_ORDER.get(course_sem_map.get(cid, ""), 0)
            credits = int(course_cred_map.get(cid, 3))
            cat = cat_enc.get(course_cat_map.get(cid, 'core'), 0)
            x_course = np.array([sem_num, credits, cat], dtype=float)
            all_X.append(np.concatenate([x_student, x_course]))
            all_y.append(y)
    
    return np.array(all_X) , np.array(all_y)
#---------------------------------------

def train_pass_predictor(X: np.ndarray, y: np.ndarray):
    X_tr, X_te, y_tr, y_te = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )
    clf = RandomForestClassifier(
        n_estimators=200, max_depth=8, min_samples_leaf=10,
        class_weight='balanced', random_state=42, n_jobs=-1,
    )
    clf.fit(X_tr, y_tr)
    report = classification_report(y_te, clf.predict(X_te),
                                   target_names=['Fail/WD', 'Pass'])
    return clf, report
#---------------------------------------

def predict_pass_probability(clf,
                              student_features: dict,
                              course_id: str,
                              courses: pd.DataFrame) -> float:
    course_sem_map = dict(zip(courses['course_id'], courses['semester']))
    course_cred_map = dict(zip(courses['course_id'], courses['credits']))
    course_cat_map = dict(zip(courses['course_id'], courses['category']))
    cat_enc = {'core': 0, 'elective': 1}

    sem_num = SEMESTER_ORDER.get(course_sem_map.get(course_id, ''), 0)
    credits = int(course_cred_map.get(course_id, 3))
    cat = cat_enc.get(course_cat_map.get(course_id, "core"), 0)

    x = np.concatenate([
        vectorise_for_ml(student_features),
        np.array([sem_num, credits, cat], dtype=float),
    ]).reshape(1, -1)

    return round(float(clf.predict_proba(x)[0][1]), 3)
#---------------------------------------