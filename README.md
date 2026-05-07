# Learning Path AI: Predictive Academic Advisor

Learning Path AI is a sophisticated student course tracking and recommendation platform designed for academic environments. It combines traditional student information systems (SIS) data with advanced machine learning and Large Language Models (LLM) to provide data-driven, personalized academic advising.

---

## 🌟 Core Features

- **Predictive Recommendation Engine**: Utilizes a Random Forest classifier to predict a student's probability of passing a specific course based on their historical performance and demographics.
- **Curriculum Graph Management**: Manages complex course prerequisites using a Directed Acyclic Graph (DAG) powered by NetworkX, ensuring all recommendations are academically valid.
- **LLM-Powered Advising**: Leverages Gemini 1.5 Flash to generate natural language explanations, academic standing assessments, and motivational content for each recommendation.
- **Simulation Mode**: A unique feature allowing students to "time travel" by entering a completed semester count (0–8). This simulates their academic profile at a specific point in time to see relevant future recommendations.
- **Modern Elegant Reporting**: Generates a professional, minimalist PDF report of the learning path using `jsPDF`.
- **Synchronized Theme System**: Features a smooth, transition-aware Light and Dark mode that persists across the entire session.

---

## 🛠️ Technology Stack

### Frontend & Backend (Web)
- **PHP 8.x**: Core application logic and session management. Follows **PSR-12** coding standards.
- **MySQL**: Relational database for storing student records, course catalogs, and academic history.
- **Vanilla JavaScript**: Client-side logic for dynamic UI updates and theme synchronization.
- **Modern CSS**: Custom design system using CSS variables and smooth transitions.

### AI & Data Engine (Python)
- **Scikit-Learn**: Used for the Random Forest classification model.
- **NetworkX**: Handles the complex curriculum prerequisite graph.
- **Google Generative AI**: Interface for the Gemini 1.5 Flash API.
- **Pandas/NumPy**: Data manipulation and feature engineering.

---

## 📂 Project Structure

```text
├── api/
│   ├── python_bridge.php    # PHP-to-Python execution bridge
│   └── recommend.php       # Main recommendation API endpoint
├── PYTHON/
│   ├── engine/
│   │   ├── config.py       # Python environment configuration
│   │   ├── llm.py          # Gemini API integration
│   │   └── recommender.py  # Core recommendation logic
│   ├── data/               # Model training datasets
│   ├── models/             # Pre-trained .pkl model files
│   └── full_recommendation_engine.py  # Comprehensive engine logic
├── config.php              # Application-wide configuration
├── index.php               # Main dashboard & Course History
├── results.php             # AI Analysis & PDF Report generation
└── style.css               # Unified theme-aware design system
```

---

## 🚀 Setup & Installation

### 1. Database Configuration
- Import `create_tables.sql` into your MySQL environment.
- Populate initial data using `insert_sample_data.sql` or the provided CSV files.
- Update `config.php` with your database credentials.

### 2. Python Environment
- Navigate to the `PYTHON/` directory.
- Install dependencies: `pip install -r requirements.txt`.
- Ensure your `PYTHON/.env` file contains a valid `GEMINI_API_KEY`.

### 3. Web Server
- Deploy to a PHP-enabled web server (e.g., XAMPP, Apache, Nginx).
- Ensure the `PYTHON_EXECUTABLE` path in `config.php` points to your Python 3 installation.

---

## ⚖️ Standards & Compliance

This project aims for high code quality and maintainability:
- **PHP**: Adheres to the **PSR-12** Extended Coding Style Guide for all core logic.
- **AI Ethics**: Recommendation logic is designed to be transparent, flagging high-risk courses and providing clear reasoning via the LLM layer.

---

## 📄 License
This project is part of a Capstone academic requirement.
