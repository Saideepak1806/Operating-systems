<?php
$host = "localhost";
$user = "root";
$pass = ""; // Leave blank if no password
$db = "library_system";

// Step 1: Connect to MySQL
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// Step 2: Create database
$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);

// Step 3: Create tables
$conn->query("CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100),
    author VARCHAR(100),
    isbn VARCHAR(20) UNIQUE,
    available INT DEFAULT 1
)");

$conn->query("CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE
)");

$conn->query("CREATE TABLE IF NOT EXISTS issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    member_id INT,
    issue_date DATE,
    return_date DATE,
    actual_return_date DATE DEFAULT NULL,
    fine INT DEFAULT 0,
    FOREIGN KEY (book_id) REFERENCES books(id),
    FOREIGN KEY (member_id) REFERENCES members(id)
)");

$message = "";

// Step 4: Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["add_book"])) {
        $title = $conn->real_escape_string($_POST["title"]);
        $author = $conn->real_escape_string($_POST["author"]);
        $isbn = $conn->real_escape_string($_POST["isbn"]);
        $sql = "INSERT INTO books (title, author, isbn) VALUES ('$title', '$author', '$isbn')";
        $message = $conn->query($sql) ? "✅ Book '$title' added." : "❌ " . $conn->error;
    }

    if (isset($_POST["add_member"])) {
        $name = $conn->real_escape_string($_POST["member_name"]);
        $email = $conn->real_escape_string($_POST["email"]);
        $sql = "INSERT INTO members (name, email) VALUES ('$name', '$email')";
        $message = $conn->query($sql) ? "✅ Member '$name' added." : "❌ " . $conn->error;
    }

    if (isset($_POST["issue_book"])) {
        $book_id = $_POST["book_id"];
        $member_id = $_POST["member_id"];
        $issue_date = $_POST["issue_date"];
        $return_date = $_POST["return_date"];
        $conn->query("INSERT INTO issues (book_id, member_id, issue_date, return_date) VALUES ($book_id, $member_id, '$issue_date', '$return_date')");
        $conn->query("UPDATE books SET available = 0 WHERE id = $book_id");
        $message = "✅ Book issued to member.";
    }

    if (isset($_POST["return_book"])) {
        $issue_id = $_POST["issue_id"];
        $actual_return = $_POST["actual_return_date"];

        // Fetch due return date
        $result = $conn->query("SELECT return_date, book_id FROM issues WHERE id = $issue_id");
        $row = $result->fetch_assoc();
        $due_date = $row['return_date'];
        $book_id = $row['book_id'];

        // Calculate fine (₹10 per day)
        $days_late = (strtotime($actual_return) - strtotime($due_date)) / (60 * 60 * 24);
        $fine = ($days_late > 0) ? $days_late * 10 : 0;

        $conn->query("UPDATE issues SET actual_return_date = '$actual_return', fine = $fine WHERE id = $issue_id");
        $conn->query("UPDATE books SET available = 1 WHERE id = $book_id");
        $message = "✅ Book returned. Fine: ₹$fine";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>📚 Library System</title>
    <style>
        body { font-family: Arial; background: #f0f2f5; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 10px; max-width: 700px; margin: auto; box-shadow: 0 0 10px gray; }
        h2 { text-align: center; }
        input, button, select { width: 100%; padding: 10px; margin: 8px 0; }
        .msg { background: #d1e7dd; padding: 10px; border-radius: 5px; color: #0f5132; }
        .error { background: #f8d7da; color: #842029; }
        hr { margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <h2>📚 Library Management System</h2>

    <form method="post">
        <h3>➕ Add Book</h3>
        <input type="text" name="title" placeholder="Title" required>
        <input type="text" name="author" placeholder="Author" required>
        <input type="text" name="isbn" placeholder="ISBN" required>
        <button type="submit" name="add_book">Add Book</button>
    </form>

    <form method="post">
        <h3>👤 Register Member</h3>
        <input type="text" name="member_name" placeholder="Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <button type="submit" name="add_member">Add Member</button>
    </form>

    <form method="post">
        <h3>🔁 Issue Book</h3>
        <select name="book_id" required>
            <option value="">-- Select Book --</option>
            <?php
            $books = $conn->query("SELECT id, title FROM books WHERE available = 1");
            while ($b = $books->fetch_assoc()) {
                echo "<option value='{$b['id']}'>{$b['title']}</option>";
            }
            ?>
        </select>
        <select name="member_id" required>
            <option value="">-- Select Member --</option>
            <?php
            $members = $conn->query("SELECT id, name FROM members");
            while ($m = $members->fetch_assoc()) {
                echo "<option value='{$m['id']}'>{$m['name']}</option>";
            }
            ?>
        </select>
        <input type="date" name="issue_date" required>
        <input type="date" name="return_date" required>
        <button type="submit" name="issue_book">Issue Book</button>
    </form>

    <form method="post">
        <h3>📥 Return Book</h3>
        <select name="issue_id" required>
            <option value="">-- Select Issued Book --</option>
            <?php
            $issues = $conn->query("SELECT issues.id, books.title, members.name FROM issues
                                     JOIN books ON issues.book_id = books.id
                                     JOIN members ON issues.member_id = members.id
                                     WHERE actual_return_date IS NULL");
            while ($i = $issues->fetch_assoc()) {
                echo "<option value='{$i['id']}'>{$i['title']} issued to {$i['name']}</option>";
            }
            ?>
        </select>
        <input type="date" name="actual_return_date" required>
        <button type="submit" name="return_book">Return Book</button>
    </form>

    <?php if ($message): ?>
        <div class="msg <?= strpos($message, '❌') !== false ? 'error' : '' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
