<?php
session_start();

$db_host = '127.0.0.1';
$db_name = 'taskmanager';
$db_user = 'root';
$db_pass = '';
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int) $e->getCode());
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$message = '';
$error_message = '';
$confetti_trigger = false; // Initialize confetti flag

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_custom_habit'])) {
    $habit_name = trim($_POST['habit_name'] ?? '');
    $habit_type = $_POST['habit_type'] ?? '';

    if (empty($habit_name)) {
        $error_message = "Custom habit name cannot be empty.";
    } elseif ($habit_type !== 'good' && $habit_type !== 'bad') {
        $error_message = "Invalid habit type selected for custom habit.";
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT id FROM habits WHERE user_id = :user_id AND LOWER(name) = LOWER(:name)");
            $stmt_check->execute(['user_id' => $user_id, 'name' => $habit_name]);
            if ($stmt_check->fetch()) {
                $error_message = "You already have a habit named '" . htmlspecialchars($habit_name) . "'.";
            } else {
                $sql = "INSERT INTO habits (user_id, name, type) VALUES (:user_id, :name, :type)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'user_id' => $user_id,
                    'name' => $habit_name,
                    'type' => $habit_type
                ]);
                $message = "Custom habit '" . htmlspecialchars($habit_name) . "' added successfully!";
            }
        } catch (PDOException $e) {
            $error_message = "Error adding custom habit: " . $e->getMessage();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_habit'])) {
    $habit_id_to_log = $_POST['habit_id'] ?? null;

    if ($habit_id_to_log) {
        $pdo->beginTransaction();
        try {
            $stmt_habit = $pdo->prepare("SELECT name, type FROM habits WHERE id = :habit_id AND user_id = :user_id");
            $stmt_habit->execute(['habit_id' => $habit_id_to_log, 'user_id' => $user_id]);
            $habit = $stmt_habit->fetch();

            if ($habit) {
                $points_change = ($habit['type'] === 'good') ? 1 : -1;

                $sql_log = "INSERT INTO habit_logs (user_id, habit_id, points_change) VALUES (:user_id, :habit_id, :points_change)";
                $stmt_log = $pdo->prepare($sql_log);
                $stmt_log->execute([
                    'user_id' => $user_id,
                    'habit_id' => $habit_id_to_log,
                    'points_change' => $points_change
                ]);

                $sql_update_balance = "UPDATE users SET balance = balance + :points WHERE id = :user_id";
                $stmt_update = $pdo->prepare($sql_update_balance);
                $stmt_update->execute([
                    'points' => $points_change,
                    'user_id' => $user_id
                ]);

                $pdo->commit();
                $message = "Logged '" . htmlspecialchars($habit['name']) . "'! Points changed by " . $points_change . ".";

                if ($points_change > 0) {
                    $confetti_trigger = true; // Set flag for confetti on success + good habit
                }

            } else {
                $error_message = "Habit not found or doesn't belong to you.";
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error logging habit: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid habit selected for logging.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_habit'])) {
    $habit_id_to_remove = $_POST['habit_id'] ?? null;

    if ($habit_id_to_remove) {
        try {
            $stmt_get_name = $pdo->prepare("SELECT name FROM habits WHERE id = :habit_id AND user_id = :user_id");
            $stmt_get_name->execute(['habit_id' => $habit_id_to_remove, 'user_id' => $user_id]);
            $habit_name_to_remove = $stmt_get_name->fetchColumn();

            if ($habit_name_to_remove !== false) {
                $sql = "DELETE FROM habits WHERE id = :habit_id AND user_id = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'habit_id' => $habit_id_to_remove,
                    'user_id' => $user_id
                ]);

                if ($stmt->rowCount() > 0) {
                    $message = "Habit '" . htmlspecialchars($habit_name_to_remove) . "' removed successfully.";
                } else {
                    $error_message = "Habit not found or already removed.";
                }
            } else {
                $error_message = "Habit not found or doesn't belong to you.";
            }

        } catch (PDOException $e) {
            $error_message = "Error removing habit: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid habit selected for removal.";
    }
}

$stmt_user = $pdo->prepare("SELECT username, balance FROM users WHERE id = :user_id");
$stmt_user->execute(['user_id' => $user_id]);
$user = $stmt_user->fetch();
$current_balance = $user['balance'] ?? 0;
$username = $user['username'] ?? 'User';

$stmt_habits = $pdo->prepare("SELECT id, name, type FROM habits WHERE user_id = :user_id ORDER BY type, name");
$stmt_habits->execute(['user_id' => $user_id]);
$user_habits = $stmt_habits->fetchAll();

$user_good_habits = array_filter($user_habits, fn($h) => $h['type'] === 'good');
$user_bad_habits = array_filter($user_habits, fn($h) => $h['type'] === 'bad');

$stmt_logs = $pdo->prepare(
    "SELECT hl.log_time, h.name, hl.points_change
     FROM habit_logs hl
     JOIN habits h ON hl.habit_id = h.id
     WHERE hl.user_id = :user_id
     ORDER BY hl.log_time DESC
     LIMIT 10"
);
$stmt_logs->execute(['user_id' => $user_id]);
$recent_logs = $stmt_logs->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <style>
        :root {
            --background-color: #c9d6ff;
            --container-bg: #ffffff;
            --header-bg: aliceblue;
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

        button,
        .btn {
            background-color: #512da8;
            color: #fff;
            font-size: 12px;
            padding: 10px 45px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
            cursor: pointer;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--background-color);
            margin: 0;
            color: var(--text-color);
            min-height: 100vh;
            position: relative;
            padding-bottom: 55px;
            box-sizing: border-box;
            overflow-x: hidden;
           
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
            width: 100%;
            box-sizing: border-box;
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

        .user {
            display: flex;
            align-items: baseline;
        }

        .user h4 {
            color: black;
            margin: 25px;
            font-style: italic;
        }

        header .user button,
        header .user a.btn {
            text-decoration: none;
            color: white;
            background-color: #512da8;
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
            background-color: #522da8d8;
        }

        

        .container {
            max-width: 800px;
            margin: auto;
            margin-top: 10px;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: var(--header-bg);

        }

        .balance {
            font-size: 1.6em;
            font-weight: bold;
            color: #007bff;
            text-align: center;
            margin-bottom: 30px;
            padding: 10px;
            background-color: #e7f3ff;
            border-radius: 5px;
        }

        .balance span {
            color: #e69900;
            font-size: 1.1em;
            margin-left: 5px;
        }

        .message,
        .error-message {
            padding: 12px 18px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid transparent;
        }

        .message {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #ccc;
        }

        section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        form {
            margin: 0;
            padding: 0;
            display: inline;
        }

        button,
        .button-link {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            display: inline-block;
            vertical-align: middle;
            margin-left: 5px;
        }

        button[type="submit"],
        .button-link {
            color: white;
        }

        .log-good {
            background-color: #28a745;
        }

        .log-good:hover {
            background-color: #218838;
        }

        .log-bad {
            background-color: #dc3545;
        }

        .log-bad:hover {
            background-color: #c82333;
        }

        .remove-button {
            background-color: #6c757d;
            color: white;
            padding: 4px 8px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .remove-button:hover {
            background-color: #5a6268;
        }

        #toggle-custom-form {
            background-color: #ffc107;
            color: #333;
            font-weight: bold;
            font-size: 1.2em;
            padding: 5px 10px;
            margin-bottom: 20px;
            display: inline-block;
            float: right;
        }

        #toggle-custom-form:hover {
            background-color: #e0a800;
        }

        #custom-habit-form {
            margin-top: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            clear: both;
        }

        #custom-habit-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        #custom-habit-form input[type="text"],
        #custom-habit-form select {
            width: calc(100% - 24px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        #custom-habit-form button[type="submit"] {
            background-color: #007bff;
            font-size: 1em;
        }

        #custom-habit-form button[type="submit"]:hover {
            background-color: #0056b3;
        }

        .habit-list {
            list-style: none;
            padding: 0;
        }

        .habit-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border: 1px solid #eee;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.2s ease-in-out;
        }

        .habit-list li:hover {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .habit-list li .habit-name {
            flex-grow: 1;
            margin-right: 15px;
            font-size: 1.1em;
        }

        .habit-list li .habit-actions {
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .habit-list .good-habit {
            border-left: 5px solid #28a745;
        }

        .habit-list .bad-habit {
            border-left: 5px solid #dc3545;
        }

        .log-history ul {
            list-style: none;
            padding: 0;
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid #eee;
            padding: 15px;
            background: #fdfdfd;
            border-radius: 5px;
        }

        .log-history li {
            margin-bottom: 8px;
            font-size: 0.9em;
            color: #555;
            padding-bottom: 8px;
            border-bottom: 1px dotted #eee;
        }

        .log-history li:last-child {
            border-bottom: none;
        }

        .log-history .points-plus {
            color: #28a745;
            font-weight: bold;
        }

        .log-history .points-minus {
            color: #dc3545;
            font-weight: bold;
        }

        .hidden {
            display: none;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--background-color);
            margin: 0;
            color: var(--text-color);
            min-height: 100vh;
            position: relative;
            padding-bottom: 55px;
            box-sizing: border-box;
            overflow-x: hidden;
        }
    </style>
</head>

<body>
    <header>
        <img src="./img/Gulogo.png" alt="Logo">
        <h2 style="padding: 20px;">FocusBrief</h2>
        <div class="habit-tracker-btn-container">
        </div>

        <div class="user">
            <h4><?php echo $username; ?></h4>
            <button><a href="./pomodoro.php">Pomodoro</a></button>
            <button><a href="dashboard.php">Dashboard</a></button>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </header>
    <div class="container">
        <h1>Habit Tracker</h1>
        <p>Welcome, <?php echo htmlspecialchars($username); ?>!</p>

        <div class="balance">
            Your Gold: <span>💰 <?php echo htmlspecialchars($current_balance); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section>
            <h2>Your Habits</h2>

            <h3>Good Habits</h3>
            <?php if (empty($user_good_habits)): ?>
                <p>No good habits added yet. Use the '+' button below to add some!</p>
            <?php else: ?>
                <ul class="habit-list">
                    <?php foreach ($user_good_habits as $habit): ?>
                        <li class="good-habit">
                            <span class="habit-name"><?php echo htmlspecialchars($habit['name']); ?></span>
                            <div class="habit-actions">
                                <form action="habits.php" method="POST">
                                    <input type="hidden" name="habit_id" value="<?php echo $habit['id']; ?>">
                                    <button type="submit" name="log_habit" class="log-good">+1 Gold</button>
                                </form>
                                <form action="habits.php" method="POST"
                                    onsubmit="return confirm('Are you sure you want to remove this habit?');">
                                    <input type="hidden" name="habit_id" value="<?php echo $habit['id']; ?>">
                                    <button type="submit" name="remove_habit" class="remove-button"
                                        title="Remove Habit">X</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3 style="margin-top: 25px;">Bad Habits</h3>
            <?php if (empty($user_bad_habits)): ?>
                <p>No bad habits added yet. Use the '+' button below to add some!</p>
            <?php else: ?>
                <ul class="habit-list">
                    <?php foreach ($user_bad_habits as $habit): ?>
                        <li class="bad-habit">
                            <span class="habit-name"><?php echo htmlspecialchars($habit['name']); ?></span>
                            <div class="habit-actions">
                                <form action="habits.php" method="POST">
                                    <input type="hidden" name="habit_id" value="<?php echo $habit['id']; ?>">
                                    <button type="submit" name="log_habit" class="log-bad">-1 Gold</button>
                                </form>
                                <form action="habits.php" method="POST"
                                    onsubmit="return confirm('Are you sure you want to remove this habit?');">
                                    <input type="hidden" name="habit_id" value="<?php echo $habit['id']; ?>">
                                    <button type="submit" name="remove_habit" class="remove-button"
                                        title="Remove Habit">X</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="clearfix">
            <button id="toggle-custom-form" title="Add Custom Habit">+</button>
            <h2>Add Custom Habit</h2>

            <div id="custom-habit-form" class="hidden">
                <form action="habits.php" method="POST" style="display: block;">
                    <label for="habit_name">Habit Name:</label>
                    <input type="text" id="habit_name" name="habit_name" required
                        placeholder="e.g., Meditate for 10 minutes">

                    <label for="habit_type">Habit Type:</label>
                    <select id="habit_type" name="habit_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="good">Good Habit (+1 Gold)</option>
                        <option value="bad">Bad Habit (-1 Gold)</option>
                    </select>

                    <button type="submit" name="add_custom_habit">Add Custom Habit</button>
                </form>
            </div>
        </section>

        <section class="log-history">
            <h2>Recent Activity</h2>
            <?php if (empty($recent_logs)): ?>
                <p>No recent habit activity.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($recent_logs as $log): ?>
                        <li>
                            <?php echo htmlspecialchars(date('M d, H:i', strtotime($log['log_time']))); ?> -
                            Logged '<?php echo htmlspecialchars($log['name']); ?>'
                            (<span class="<?php echo $log['points_change'] > 0 ? 'points-plus' : 'points-minus'; ?>">
                                <?php echo ($log['points_change'] > 0 ? '+' : '') . $log['points_change']; ?> Gold
                            </span>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>

    <script>
        const toggleButton = document.getElementById('toggle-custom-form');
        const customForm = document.getElementById('custom-habit-form');

        if (toggleButton && customForm) {
            toggleButton.addEventListener('click', () => {
                customForm.classList.toggle('hidden');
                if (customForm.classList.contains('hidden')) {
                    toggleButton.textContent = '+';
                    toggleButton.title = 'Add Custom Habit';
                } else {
                    toggleButton.textContent = '-';
                    toggleButton.title = 'Hide Custom Habit Form';
                }
            });
        }

        <?php if ($confetti_trigger): ?>
            confetti({
                particleCount: 620,
                spread: 120,
                origin: { y: 0.6 },
                angle: 90,
                startVelocity: 70,
                colors: ['#28a745', '#ffc107', '#007bff', '#ffffff']
            });
        <?php endif; ?>
    </script>

</body>

</html>