import google.generativeai as genai

from engine.config import GEMINI_API_KEY

SYSTEM_PROMPT = '''\
You are an academic advisor AI for an AI Engineering department.
Recommend courses for the upcoming semester based on the student's academic
history, prerequisite completion, and predicted pass probability.

Rules:
- Only recommend courses whose prerequisites are fully passed.
- Flag retake courses (previously failed or withdrawn) and explain why a retry makes sense now.
- Warn about any course with predicted pass probability below 45%.
- Keep the explanation clear and encouraging (3-5 sentences).
- End with one personalised motivational sentence.
- IMPORTANT: Do NOT recommend or mention the Summer Training course under any circumstances,
  unless the student has completed all required major courses of Year 3 (i.e., their current
  semester position is 6 or higher). If this condition is not met and Summer Training appears
  in the recommended list, skip it entirely without explanation.
'''

def build_llm_prompt(rec: dict) -> dict:
    '''
    Returns {"system": ..., "user": ...} ready to POST to Gemini 1.5 Flash
    '''
    p       = rec['student_profile']
    mode    = rec['mode']
    sim_tag = (f'  [Simulation: record restricted to semester <= {rec["sim_max_semester"]}]\n'
               if mode == 'simulation' else '')

    courses_txt = "\n".join(
        f'  * {c["course_name"]} [{c["course_id"]}] | {c["credits"]} cr '
        f'| {c["category"]} | P(pass): {c["p_pass"]*100:.0f}%'
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

    # Determine if the student is eligible for Summer Training
    summer_eligibility = (
        "ELIGIBLE (student has completed Year 3 major courses)"
        if p["inferred_semester"] >= 6
        else "NOT ELIGIBLE (student has not yet completed all Year 3 major courses — do NOT recommend Summer Training)"
    )

    user_msg = f"""\
Student ID     : {rec['student_id']}
{sim_tag}GPA            : {p['gpa']}   |   Avg score : {p['avg_score']}
Pass rate      : {p['pass_rate']*100:.0f}%   |   Courses passed: {p['courses_passed']}
Failed         : {p['courses_failed']}         |   Withdrawn     : {p['courses_withdrawn']}
Current semester position: {p['inferred_semester']}
Summer Training eligibility: {summer_eligibility}

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

#----------------------------------------------------

def call_gemini(prompt: dict) -> str:
    genai.configure(api_key= GEMINI_API_KEY)
    model = genai.GenerativeModel(
        model_name = 'gemini-2.5-flash',
        system_instruction=prompt['system']
    )
    response = model.generate_content(prompt['user'])
    return response.text