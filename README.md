# Productivity Management App (FocusBrief)

A web application designed to help users manage productivity through task management, habit tracking, and time management techniques like the Pomodoro timer.

## Features

* **User Authentication:** Secure user registration and login system.
* **Habit Tracking:**
    * Add custom "good" or "bad" habits.
    * Log habit completion/occurrence.
    * Gamified points system ("Gold") based on habit logging.
    * View recent habit activity log.
    * Remove existing habits.
* **Pomodoro Timer:**
    * Integrated Pomodoro timer to help users focus.
    * Tracks completed Pomodoro sessions.
* **Dashboard:** A central place for users to view their information (specific features may vary).

## Technologies Used

* **Backend:** PHP
* **Database:** phpMyAdmin (Database name: `taskmanager`, using PDO for connection)
* **Frontend:** HTML, CSS, JavaScript

## Setup

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/ixoz/Productivity-Management-App.git
    cd Productivity-Management-App
    ```
2.  **Database Setup:**
    * Ensure you have a phpMyAdmin server running.
    * Create a database named `taskmanager`.
    * Import the necessary database schema (You might need to add a `.sql` file to your repository).

## Usage

1.  Register a new account or Sign In if you already have one.
2.  Navigate to the Habit Tracker section to add and manage your habits.
3.  Use the Pomodoro Timer section to start focus sessions.
4.  Check your dashboard for To-Do.
