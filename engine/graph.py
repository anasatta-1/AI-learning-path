import pandas as pd
import networkx as nx

#---------------------------------------
def build_course_graph(courses: pd.DataFrame,
                       prereqs: pd.DataFrame) -> nx.DiGraph:
    '''
    Graph of courses
    Node  = course_id with attributes (name, semester, credits, category)
    Edge  = prerequisite -> dependent  (A -> B: pass A before B)
    '''
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
#---------------------------------------

def get_available_courses(G: nx.DiGraph,
                          passed_ids: set,
                          already_taken: set) -> list:
    '''Courses the student has not taken yet AND whose prereqs are all passed'''
    return [
        n for n in G.nodes()
        if n not in already_taken
        and all(p in passed_ids for p in G.predecessors(n))
    ]
#---------------------------------------

def get_blocked_courses(G: nx.DiGraph,
                        passed_ids: set,
                        already_taken: set,
                        courses: pd.DataFrame) -> list:
    '''Courses blocked by prerequisites'''
    blocked = []
    id_to_name = dict(zip(courses['course_id'] , courses['course_name']))
    for n in G.nodes():
        if n in already_taken:
            continue
        missing = [p for p in G.predecessors(n) if p not in passed_ids]
        if missing:
            blocked.append({
                "course_id":       n,
                "course_name":     id_to_name.get(n, n),
                "missing_prereqs": [id_to_name.get(m, m) for m in missing]
                })
    return blocked
#---------------------------------------
def validate_graph(G: nx.DiGraph):
    if not nx.is_directed_acyclic_graph(G):
        cycles = list(nx.find_cycle(G))
        raise ValueError(f'Cycle detected in prerequisites: {cycles}')
    print(f' -> Graph valid: {G.number_of_nodes()} nodes, {G.number_of_edges()} edges, no cycles')

#---------------------------------------

