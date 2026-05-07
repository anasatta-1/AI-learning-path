import pandas as pd
import networkx as nx

from engine.graph import get_available_courses , get_blocked_courses
from engine.features import build_student_features
from engine.model import predict_pass_probability
from engine.constants import SEMESTER_ORDER , MAX_CREDITS , RISK_THRESHOLD

def recommend_courses(student_id: int,
                      G: nx.DiGraph,
                      clf,
                      records: pd.DataFrame,
                      students: pd.DataFrame,
                      courses: pd.DataFrame,
                      sim_max_semester: int = None,
                      top_n: int = 6) -> dict:
    feats = build_student_features(student_id , records , students , sim_max_semester)
    if not feats:
        return {'error': f'No records found for student {student_id}'}
    
    passed_ids = feats['passed_ids']
    all_taken = feats['all_taken_ids']
    failed_ids = feats['failed_ids']
    withdrawn_ids = feats['withdrawn_ids']

    available = get_available_courses(G , passed_ids , all_taken)
    retry_candidates = [
        cid for cid in (failed_ids | withdrawn_ids)
        if all(p in passed_ids for p in G.predecessors(cid))
    ]
    all_candidates = list(set(available + retry_candidates))

    course_sem_map = dict(zip(courses["course_id"], courses["semester"]))
    course_cred_map = dict(zip(courses["course_id"], courses["credits"]))
    course_cat_map = dict(zip(courses["course_id"], courses["category"]))
    id_to_name = dict(zip(courses["course_id"], courses["course_name"]))

    scored = []
    for cid in all_candidates:
        p_pass = predict_pass_probability(clf , feats , cid , courses)
        is_retry = cid in (failed_ids | withdrawn_ids)
        is_core  = course_cat_map.get(cid, 'core') == 'core'
        sem_num  = SEMESTER_ORDER.get(course_sem_map.get(cid, ''), 0)
        sem_diff = abs(sem_num - feats['inferred_semester'])

        priority = (
            p_pass * 0.50
            + (0.20 if is_core  else 0.00)
            + (0.15 if is_retry else 0.00)
            + 0.15 / (1 + sem_diff)
        )

        scored.append({
            'course_id': cid,
            'course_name': id_to_name.get(cid, cid),
            'semester_slot': course_sem_map.get(cid, ""),
            'credits': int(course_cred_map.get(cid, 3)),
            'category': course_cat_map.get(cid, ""),
            'p_pass': p_pass,
            'priority': round(priority, 4),
            'is_retry': is_retry,
            'risk': p_pass < RISK_THRESHOLD,
        })

    scored.sort(key=lambda x: x['priority'], reverse=True)

    selected, total_credits = [], 0
    for c in scored:
        if total_credits + c['credits'] > MAX_CREDITS:
            continue
        selected.append(c)
        total_credits += c['credits']
        if len(selected) >= top_n:
            break

    return {
        'student_id': student_id,
        'sim_max_semester': sim_max_semester,
        'mode': 'simulation' if sim_max_semester is not None else 'real',
        'student_profile': {
            'gpa': feats['gpa'],
            'avg_score': feats['avg_score'],
            'pass_rate': feats['pass_rate'],
            'courses_passed': feats['courses_passed'],
            'courses_failed': feats['courses_failed'],
            'courses_withdrawn': feats['courses_withdrawn'],
            'inferred_semester': feats['inferred_semester'],
        },
        'recommended_courses': selected,
        'total_recommended_credits': total_credits,
        'courses_to_retry': [c for c in selected if c['is_retry']],
        'risky_courses': [c for c in selected if c['risk']],
        'blocked_upcoming': get_blocked_courses(G, passed_ids, all_taken, courses)[:5],
    }