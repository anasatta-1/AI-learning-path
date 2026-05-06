import pickle

from engine.config import DATA_DIR_env , MODEL_DIR_env
from engine.data import load_data
from engine.model import load_model
from engine.recommender import recommend_courses
from engine.llm import build_llm_prompt , call_gemini

if __name__ == '__main__':
    print('Loading data....')
    db = load_data(DATA_DIR_env)

    print('Loading saved model....')
    clf , G = load_model(MODEL_DIR_env)

    # The application
    rec = recommend_courses(
        1001, G, clf, 
        db['records'], db['students'], db['courses'], 
        sim_max_semester=4
        )
    prompt = build_llm_prompt(rec)
    explanation = call_gemini(prompt)
    print(explanation)