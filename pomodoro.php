<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$username = isset($_SESSION['user_username']) ? htmlspecialchars($_SESSION['user_username']) : 'User';

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'taskmanager';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    error_log("Database Connection Failed: " . $conn->connect_error);
    die("An error occurred connecting to the service. Please try again later.");
}
$conn->set_charset("utf8mb4");

$currentPomodoroCount = 0;

$stmt_fetch = $conn->prepare("SELECT pomodoro_count FROM users WHERE id = ?");
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $userId);
    if ($stmt_fetch->execute()) {
        $result = $stmt_fetch->get_result();
        if ($row = $result->fetch_assoc()) {
            $currentPomodoroCount = $row['pomodoro_count'];
        }
    } else {
         error_log("Failed to execute statement for fetching count: " . $stmt_fetch->error);
    }
    $stmt_fetch->close();
} else {
    error_log("Failed to prepare statement for fetching count: " . $conn->error);
}

$is_ajax_request = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'increment_pomodoro');

if ($is_ajax_request && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'increment_pomodoro') {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    if ($userId) {
        $conn->begin_transaction();
        try {
            $updateStmt = $conn->prepare("UPDATE users SET pomodoro_count = pomodoro_count + 1 WHERE id = ?");
            if(!$updateStmt) throw new Exception("Prepare failed (UPDATE): " . $conn->error);

            $updateStmt->bind_param("i", $userId);
            if(!$updateStmt->execute()) throw new Exception("Execute failed (UPDATE): " . $updateStmt->error);
            $updateStmt->close();

            $selectStmt = $conn->prepare("SELECT pomodoro_count FROM users WHERE id = ?");
             if(!$selectStmt) throw new Exception("Prepare failed (SELECT): " . $conn->error);

            $selectStmt->bind_param("i", $userId);
             if(!$selectStmt->execute()) throw new Exception("Execute failed (SELECT): " . $selectStmt->error);

            $result = $selectStmt->get_result();
            $newCount = 0;
            if ($row = $result->fetch_assoc()) {
                $newCount = $row['pomodoro_count'];
            }
            $selectStmt->close();

            $conn->commit();
            $response = ['success' => true, 'new_count' => $newCount];

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Pomodoro increment failed for user $userId: " . $e->getMessage());
            $response['message'] = 'Failed to update count.';
        }
    } else {
        $response['message'] = 'User not identified.';
        error_log("Pomodoro increment attempt without valid userId in session.");
    }

    echo json_encode($response);
    $conn->close();
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pomodoro Timer</title>
    <style>
        :root {
            --background-color: #c9d6ff;
            --container-bg: #ffffff;
            --header-bg: #fff;
            --timer-color: #333;
            --button-bg: #5cb85c;
            --button-hover-bg: #4cae4c;
            --button-pause-bg: #f0ad4e;
            --button-pause-hover-bg: #ec971f;
            --button-reset-bg: #d9534f;
            --button-reset-hover-bg: #d43f3a;
            --text-color: #555;
            --border-color: #ddd;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --header-height: 80px;
        }
        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--background-color);
            margin: 0;
            color: var(--text-color);
        }
        header {
            background-color: var(--header-bg);
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: var(--header-height);
            box-shadow: 0 2px 4px var(--shadow-color);
            border-bottom: 1px solid var(--border-color);
        }
        header img {
            height: 60px;
            margin: 0;
            padding: 0;
        }
        .habit-tracker-btn-container {
            flex-grow: 1;
            text-align: center;
        }
        header .user {
            display: flex;
            align-items: center;
        }
        header .user h4 {
            margin: 0 15px 0 0;
            color: var(--timer-color);
            font-weight: normal;
        }
        header .user button,
        header .user a.btn {
            text-decoration: none;
            color: white;
            background-color:#512da8;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-size: 0.9em;
            margin-left: 10px;
        }
         header .user button a {
            color: white;
            text-decoration: none;
            display: block;
         }
        header .user button:hover,
        header .user a.btn:hover {
            background-color: var(--button-reset-hover-bg);
        }
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - var(--header-height));
            padding: 20px;
        }
        .pomodoro-container {
            background-color: var(--container-bg);
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px var(--shadow-color);
            text-align: center;
            border: 1px solid var(--border-color);
            min-width: 300px;
            max-width: 400px;
        }
        .pomodoro-container h1 {
            color: var(--timer-color);
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.8em;
        }
        #timer-display {
            font-size: 4.5em;
            font-weight: bold;
            color: var(--timer-color);
            margin: 20px 0;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
        }
        .controls button {
            font-size: 1em;
            padding: 12px 25px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s ease;
            min-width: 90px;
        }
        #start-pause-btn {
            background-color: var(--button-bg);
        }
        #start-pause-btn:hover {
            background-color: var(--button-hover-bg);
        }
        #start-pause-btn.paused {
             background-color: var(--button-pause-bg);
        }
         #start-pause-btn.paused:hover {
             background-color: var(--button-pause-hover-bg);
        }
        #reset-btn {
            background-color: var(--button-reset-bg);
        }
        #reset-btn:hover {
            background-color: var(--button-reset-hover-bg);
        }
        #pomodoro-count-display {
            margin-top: 25px;
            font-size: 1.1em;
            color: var(--text-color);
        }
         #pomodoro-count-display strong {
             color: var(--timer-color);
             font-weight: bold;
             font-size: 1.3em;
             margin-left: 5px;
         }
         .status-message {
             margin-top: 15px;
             font-style: italic;
             color: #777;
             min-height: 20px;
         }
    </style>
</head>
<body>

    <header>
        <img src="./img/Gulogo.png" alt="Logo" >

        <div class="habit-tracker-btn-container">
        </div>

        <div class="user">
            <h4><?php echo $username; ?></h4>
            <button><a href="dashboard.php">Dashboard</a></button>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </header>

    <div class="main-content">
        <div class="pomodoro-container">
            <h1>Pomodoro Timer</h1>
            <div id="timer-display">25:00</div>
            <div class="controls">
                <button id="start-pause-btn">Start</button>
                <button id="reset-btn">Reset</button>
            </div>
            <div class="status-message" id="status-message">Ready to focus?</div>
            <div id="pomodoro-count-display">
                Completed Pomodoros: <strong id="pomodoro-count"><?php echo htmlspecialchars($currentPomodoroCount); ?></strong>
            </div>
        </div>
    </div>

    <script>
        const timerDisplay = document.getElementById('timer-display');
        const startPauseBtn = document.getElementById('start-pause-btn');
        const resetBtn = document.getElementById('reset-btn');
        const pomodoroCountSpan = document.getElementById('pomodoro-count');
        const statusMessageDiv = document.getElementById('status-message');

        const WORK_DURATION = 25 * 60;

        let timerInterval = null;
        let remainingTime = WORK_DURATION;
        let isPaused = true;
        let isRunning = false;

        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        function updateDisplay() {
            timerDisplay.textContent = formatTime(remainingTime);
            if (isRunning && !isPaused) {
                 document.title = `${formatTime(remainingTime)} - Focusing...`;
            } else {
                 document.title = 'Pomodoro Timer';
            }
        }

        function updateStatusMessage(message) {
            statusMessageDiv.textContent = message;
        }

        function startTimer() {
            if (isPaused && remainingTime > 0) {
                isPaused = false;
                isRunning = true;
                startPauseBtn.textContent = 'Pause';
                startPauseBtn.classList.add('paused');
                updateStatusMessage('Focus time!');
                updateDisplay();

                timerInterval = setInterval(() => {
                    remainingTime--;
                    updateDisplay();

                    if (remainingTime <= 0) {
                        clearInterval(timerInterval);
                        timerInterval = null;
                        updateStatusMessage('Pomodoro session complete!');
                        incrementPomodoroCount();
                        resetTimer(false);
                        updateStatusMessage('Take a short break!');
                        document.title = 'Break Time! - Pomodoro';
                    }
                }, 1000);
            }
        }

        function pauseTimer() {
            if (!isPaused) {
                isPaused = true;
                startPauseBtn.textContent = 'Start';
                startPauseBtn.classList.remove('paused');
                updateStatusMessage('Timer paused.');
                clearInterval(timerInterval);
                timerInterval = null;
                 updateDisplay();
            }
        }

        function resetTimer(updateStatus = true) {
            clearInterval(timerInterval);
            timerInterval = null;
            remainingTime = WORK_DURATION;
            isPaused = true;
            isRunning = false;
            startPauseBtn.textContent = 'Start';
            startPauseBtn.classList.remove('paused');
            updateDisplay();
             if (updateStatus) {
                updateStatusMessage('Ready to start a new session!');
            }
        }

        function incrementPomodoroCount() {
            const headers = {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            };

            fetch(window.location.href, {
                method: 'POST',
                headers: headers,
                body: 'action=increment_pomodoro'
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
                    });
                }
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                     return response.json();
                } else {
                    return response.text().then(text => {
                         throw new Error(`Unexpected response type: ${contentType}. Content: ${text}`);
                    });
                }
            })
            .then(data => {
                if (data.success && typeof data.new_count !== 'undefined') {
                    pomodoroCountSpan.textContent = data.new_count;
                } else {
                    console.error('Failed to increment count:', data.message || 'Unknown error from server');
                    updateStatusMessage('Error saving count!');
                }
            })
            .catch(error => {
                console.error('Error sending increment request:', error);
                 updateStatusMessage('Network or server error saving count!');
            });
        }

        startPauseBtn.addEventListener('click', () => {
            if (isPaused) {
                startTimer();
            } else {
                pauseTimer();
            }
        });

        resetBtn.addEventListener('click', () => {
             if (isRunning && !isPaused) {
                 if (!confirm("Are you sure you want to reset the current timer? This session won't be saved.")) {
                    return;
                 }
             }
            resetTimer();
        });

        updateDisplay();

    </script>

</body>
</html>