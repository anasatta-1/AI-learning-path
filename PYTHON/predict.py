import sys
import json
import os
from engine.config import DATA_DIR_env, MODEL_DIR_env
from engine.data import load_data
from engine.model import load_model
from engine.recommender import recommend_courses
from engine.llm import build_llm_prompt, call_gemini

def main():
    # Read input from STDIN
    try:
        input_data = sys.stdin.read()
        if not input_data:
            print(json.dumps({"error": "No input provided"}))
            return
        
        data = json.loads(input_data)
        student_id = data.get('student_id')
        semester = data.get('semester')

        if student_id is None:
            print(json.dumps({"error": "Missing student_id"}))
            return

        # Load data and model
        # Note: We use the directories from config.py which loads from .env
        db = load_data(DATA_DIR_env)
        clf, G = load_model(MODEL_DIR_env)

        # Run recommendation
        # student_id must be int for the engine
        rec = recommend_courses(
            int(student_id), G, clf, 
            db['records'], db['students'], db['courses'], 
            sim_max_semester=semester
        )

        if "error" in rec:
            print(json.dumps(rec))
            return

        # Build prompt and call Gemini for explanation
        explanation = ""
        try:
            prompt = build_llm_prompt(rec)
            explanation = call_gemini(prompt)
        except Exception as gemini_err:
            # If Gemini fails, we still want to return the recommendations
            explanation = f"Reasoning currently unavailable (AI Error: {str(gemini_err)})"

        # Combine results
        result = {
            "recommended_courses": rec.get("recommended_courses", []),
            "explanation": explanation,
            "student_profile": rec.get("student_profile", {})
        }

        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()
