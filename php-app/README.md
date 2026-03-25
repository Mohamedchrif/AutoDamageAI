# Modernized AutoDamg Architecture

You have successfully transitioned to a **Separation of Concerns (SoC)** architecture. The system is modularized into two independent parts: the `php-app` (Frontend & Business Logic) and `flask-ai` (AI Inference API).

## Quick Start Instructions

To run this system, you need both the MySQL/PHP server and the Flask AI server running simultaneously.

### 1. Database Setup
1. Open your MySQL client (e.g., phpMyAdmin or CLI).
2. Run the SQL script found in `database.sql` to create the `autodamg_db` database and the required tables (`users`, `analyses`).
3. Open `config.php` and update the database credentials (`$db_user` and `$db_pass`) to match your local MySQL setup.

### 2. Start the PHP Server (Frontend)
Navigate into the `php-app` directory and start a local PHP development server:
```bash
cd php-app
php -S 127.0.0.1:8000
```
Your frontend is now available at `http://127.0.0.1:8000/home.php`.

### 3. Start the Flask AI Server (Backend API)
Open a new terminal, navigate to the `flask-ai` directory, install requirements, and run the API:
```bash
cd flask-ai
pip install -r requirements.txt
python app.py
```
The Flask API operates exclusively in the background on port `5000` (`http://127.0.0.1:5000/predict`).

---

## Technical Flow Overview

1. **User Action**: The user visits `index.php` and uploads an image via the `<form>`.
2. **PHP Receives Image**: `upload.php` handles the `multipart/form-data` upload securely.
3. **cURL Request**: PHP sends the image file via `cURL` an internal HTTP `POST` to `flask-ai/predict`.
4. **YOLO AI Inference**: Flask processes the image logic using the `.pt` models and OpenCV, calculates repair costs, and responds purely in JSON.
5. **Database Storage**: `upload.php` decodes the JSON, inserts the results into the MySQL `analyses` table, and associates it with the logged-in User's ID.
6. **Results Rendering**: `upload.php` redirects the browser to `result.php?id=X`, where PHP natively renders the detailed findings from the MySQL database.

*(Note: The `flask-ai` app no longer queries databases, maintains user sessions, or attempts to render HTML. It acts purely as a stateless REST service).*
