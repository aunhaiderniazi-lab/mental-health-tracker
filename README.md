# Mental Health Tracker

A full-stack **PHP & MySQL** wellness web application built for the **Introduction to Web Technologies** course at Foundation University School of Science and Technology (FUSST). It enables users to register securely, log daily moods on a 1–5 scale, maintain a private reflection journal with full CRUD support, and visualize emotional trends through live **Chart.js** analytics.

## Key Features
* **Secure User Authentication:** Registration and login powered by PHP sessions, `password_hash()` / `password_verify()`, and `mysqli` prepared statements to prevent SQL injection.
* **Daily Mood & Reflection CRUD:** Select from 5 mood states (*Excellent, Good, Neutral, Not Great, Difficult*), record dated journal reflections, and edit or delete past entries.
* **Live Chart.js Wellness Analytics:** Automatically computes average mood scores, total logged check-ins, and highest-mood days alongside a 7-entry trend line chart and mood distribution doughnut chart.
* **Maroon & Champagne Gold UI + Dark/Light Mode:** Responsive Bootstrap 5 interface styled in Maroon (`#7A1C32`) and Champagne Gold (`#D4A359`) with a persisted `localStorage` Dark/Light theme toggle.

## Project Structure

| File | Description |
| :--- | :--- |
| `mht.sql` | MySQL database schema creating `mht`, `users`, and `journal_entries` tables. |
| `connection.php` | `mysqli` database connection configuration (`utf8mb4`). |
| `user_register.php` | Account registration page with duplicate email checks and bcrypt password hashing. |
| `index.php` | User login page with session initialization and validation. |
| `final.php` | Main authenticated dashboard with mood selector, journal CRUD, KPI metrics, and Chart.js graphs. |
| `logout.php` | Session termination and redirect to `index.php`. |

## Local Setup (XAMPP / WAMP)
1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`, go to the **SQL** tab, paste the contents of `mht.sql`, and click **Go**.
3. Copy all `.php` files into `C:\xampp\htdocs\mht\`.
4. Open `http://localhost/mht/user_register.php` in your browser to create an account and sign in.

## Disclaimer
This application is built as an academic web engineering project for personal self-reflection and mood journaling, not medical diagnosis or treatment.
