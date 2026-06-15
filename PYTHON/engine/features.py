import pandas as pd
import numpy as np

from engine.data import get_student_records
from engine.constants import PASS_RESULTS , FAIL_RESULTS , WD_RESULTS

#-----------------------------------
def build_student_features(student_id: int,
                            records: pd.DataFrame,
                            students: pd.DataFrame,
                            sim_max_semester: int = None) -> dict:
    sr = get_student_records(student_id , records , sim_max_semester)
    info = students[students['student_id'] == student_id]

    if sr.empty:
        return {}
    
    passed    = sr[sr['final_result'].isin(PASS_RESULTS)]
    failed    = sr[sr['final_result'].isin(FAIL_RESULTS)]
    withdrawn = sr[sr['final_result'].isin(WD_RESULTS)]
    scores    = sr[sr['score'].notna()]

    gpa = passed['gpa_points'].mean() if not passed.empty else 0.0
    inferred_sem = int(sr['sem_num'].max()) if not sr.empty else 0

    return {
        # identifiers
        'student_id': student_id,
        'sim_max_semester': sim_max_semester,

        # demographics
        'enrollment_year': int(info['enrollment_year'].values[0]) if not info.empty else None,
        'gender': info['gender'].values[0] if not info.empty else None,
        'age': int(info['age'].values[0]) if not info.empty else None,

        # academic summary
        'courses_taken': len(sr),
        'courses_passed': len(passed),
        'courses_failed': len(failed),
        'courses_withdrawn': len(withdrawn),
        'pass_rate': len(passed) / len(sr) if sr.shape[0] else 0.0,
        'fail_rate': len(failed) / len(sr) if sr.shape[0] else 0.0,
        'gpa': round(gpa, 3),
        'avg_score': round(scores['score'].mean(), 2) if not scores.empty else 0.0,
        'std_score': round(scores['score'].std(),  2) if len(scores) > 1  else 0.0,
        'min_score': float(scores['score'].min())     if not scores.empty else 0.0,
        'max_score': float(scores['score'].max())     if not scores.empty else 0.0,
        'inferred_semester': inferred_sem,

        # course sets (used by graph logic, stripped before ML)
        'passed_ids': set(passed['course_id'].unique()),
        'failed_ids': set(failed['course_id'].unique()),
        'withdrawn_ids': set(withdrawn['course_id'].unique()),
        'all_taken_ids': set(sr['course_id'].unique()),
    }
#-----------------------------------

def vectorise_for_ml(features: dict) -> np.ndarray:
    '''Flat numeric array for the ML model (excludes set fields)'''
    numeric = [
        'enrollment_year', 'age',
        'courses_taken', 'courses_passed', 'courses_failed', 'courses_withdrawn',
        'pass_rate', 'fail_rate', 'gpa',
        'avg_score', 'std_score', 'min_score', 'max_score', 'inferred_semester',
    ]
    gender = 1 if features.get('gender') == 'M' else 0
    return np.array(
        [features.get(k, 0) or 0 for k in numeric] + [gender],
        dtype=float
    )
#-----------------------------------