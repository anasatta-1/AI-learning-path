import pickle
from engine.config import DATA_DIR_env , MODEL_DIR_env
from engine.data import load_data
from engine.graph import build_course_graph , validate_graph
from engine.model import build_training_dataset , train_pass_predictor

if __name__ == '__main__':
    # DATA_DIR = DATA_DIR_env
    # MODEL_DIR = MODEL_DIR_env

    print('Loading data ...')
    db = load_data(DATA_DIR_env)

    print('Building course graph ...')
    G = build_course_graph(db['courses'] , db['prereqs'])
    validate_graph(G)
    print(f'  -> {G.number_of_nodes()} nodes, {G.number_of_edges()} edges')

    print('Building training dataset ...')
    X , y = build_training_dataset(db['records'], db['students'], db['courses'])
    print(f'  -> {X.shape[0]} samples, {X.shape[1]} features '
          f'| pass rate: {y.mean()*100:.1f}%')
    
    print('Training Random Forest ...')
    clf, report = train_pass_predictor(X, y)
    print(report)

    print('Saving model and graph ...')
    with open(f'{MODEL_DIR_env}/pass_predictor.pkl', 'wb') as f:
        pickle.dump(clf, f)
    with open(f'{MODEL_DIR_env}/course_graph.pkl', 'wb') as f:
        pickle.dump(G, f)

    print('Done. Run main.py to use the system.')
