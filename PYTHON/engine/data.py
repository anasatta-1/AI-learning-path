import pandas as pd
import numpy as np
from pathlib import Path

from engine.constants import SEMESTER_ORDER
from engine.config import DATA_DIR_env

#---------------------------------------
def load_data(data_dir: str = DATA_DIR_env) -> dict:
    p = Path(data_dir)
    courses  = pd.read_csv(p / 'courses.csv', encoding='latin1')
    prereqs  = pd.read_csv(p / 'prerequisites.csv')
    records  = pd.read_csv(p / 'student_records.csv')
    students = pd.read_csv(p / 'students.csv')

    name_to_id = dict(zip(courses['course_name'] , courses['course_id']))
    id_to_name = dict(zip(courses['course_id'] , courses['course_name']))

    # course_id is now a real code column — just clean whitespace
    records['course_id'] = records['course_id'].str.strip()
    records = records.dropna(subset=['course_id' , 'student_id'])
    records['student_id'] = records['student_id'].astype(int)
    # Drop rows whose course_id isn't in the catalogue (e.g. placeholder '0')
    valid_ids = set(courses['course_id'])
    records = records[records['course_id'].isin(valid_ids)]

    # Attach semester number to every record row for easy filtering
    course_sem_map = dict(zip(courses['course_id'], courses['semester']))
    records['sem_num'] = records['course_id'].map(
        lambda c: SEMESTER_ORDER.get(course_sem_map.get(c, ''), 0)
    )

    GRADE_MAP = {
        'Pass': 2.0, 'Distinction': 4.0,
        'Fail': 0.0, 'Withdrawn': np.nan,
        'A': 4.0, 'B': 3.0, 'C': 2.0, 'D': 1.0, 'F': 0.0,
    }
    records['gpa_points'] = records['letter_grade'].map(GRADE_MAP)

    return {
        'courses':    courses,
        'prereqs':    prereqs,
        'records':    records,
        'students':   students,
        'name_to_id': name_to_id,
        'id_to_name': id_to_name,
    }
#---------------------------------------

def get_student_records(student_id: int,
                        records: pd.DataFrame,
                        sim_max_semester: int = None) -> pd.DataFrame:
    sr = records[records['student_id'] == student_id].copy()
    if sim_max_semester is not None:
        sr = sr[sr['sem_num'] <= sim_max_semester]
    return sr
#---------------------------------------