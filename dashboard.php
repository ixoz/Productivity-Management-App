<?php
require_once 'includes/dbHandler.inc.php';
require_once 'includes/addCategory_func.inc.php';
require_once 'includes/addTask_func.inc.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="img/ficon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/dashboard_style.css">
    <link rel="stylesheet" href="css/taskList_style.css">

    <title>Focusbrief | Dashboard</title>

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

        * {
            box-sizing: border-box;
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

        button:hover {
            background-color: #522da8d8;
            transition: all 0.3s ease-in-out;
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
    </style>
</head>

<body>
    <header>
        <img src="./img/Gulogo.png" alt="Logo">
        <h2 style="padding: 20px;">FocusBrief</h2>
        <div class="habit-tracker-btn-container">
        </div>
        <?php
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit();
        }

        require_once 'includes/dbHandler.inc.php';

        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['user_username'];

        // Fetch user balance
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch();
        $balance = $user['balance'] ?? 0;
        ?>


        <div class="user">
            <div>
            <img src="img/coin.png" alt="coin" style="height: 30px; width: 30px;">
            <span style="font-size: 18px; font-weight: bold; color: #e69900;"><?php echo htmlspecialchars($balance); ?></span>
            </div>
            <h4><?php echo htmlspecialchars($username); ?></h4>
            <button><a href="./habits.php">Habit Tracker</a></button>
            <button><a href="./pomodoro.php">Pomodoro</a></button>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </header>


    <div class="global-container">
        <div class="add-forms-section">

            <div class="container" id="add-category-container">
                <div class="form-container" id="add-category-content">
                    <form action="includes/addCategory.inc.php" method="post">
                        <h1>Crate Category</h1>
                        <label for="category-title">Category Title: </label>
                        <input type="text" name="category-title" placeholder="Category Title" id="category-title">
                        <button>Crate</button>
                    </form>
                    <?php
                    check_addCategory_errors();
                    ?>
                </div>
            </div>

            <div class="container" id="add-task-container">
                <div class="form-container" id="add-task-content">
                    <form action="includes/addTask.inc.php" method="post">
                        <h1>Crate Task</h1>
                        <label for="title">Title: </label>
                        <input type="text" name="title" placeholder="Title" id="title">
                        <label for="description">Description: </label>
                        <textarea name="description" placeholder="Description" rows="3" cols="40"
                            id="description"></textarea>
                        <label for="category-list">Category: </label>
                        <select name="category" id="category-list">

                            <?php
                            echo "<option value=''>Select Category</option>";
                            show_categories($pdo);
                            ?>
                        </select>
                        <label for="deadline">Deadline: </label>
                        <input type="date" name="deadline" id="deadline">
                        <label for="status">Status: </label>
                        <select name="status" id="status">
                            <option value=''>Select Status</option>
                            <option value="not started">not started</option>
                            <option value="in progress">in progress</option>
                            <option value="completed">completed</option>
                        </select>
                        <button>Create</button>

                    </form>

                    <?php
                    check_addTask_errors();
                    ?>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="task-list-container">
                <div class="task-list-header">
                    <h1>List of Tasks</h1>
                    <div class="container-btn">
                        <button id="add-category-btn">Add Category</button>
                        <button id="add-task-btn">Add Task</button>
                    </div>
                </div>
                <?php
                show_tasks($pdo, $_SESSION['user_id']);
                ?>
            </div>
        </div>
    </div>

    <footer>
        <h4>All Rights Reserved © Created By <strong>Nisarg</strong></h4>
    </footer>

    <script src="js/addTask.js"></script>
    <script src="js/addCategory.js"></script>
</body>

</html>
