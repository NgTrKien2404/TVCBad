<?php
// players.php
require_once 'db.php';

// Thêm người chơi mới
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO players (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        header("Location: players.php");
        exit();
    }
}

// Danh sách người chơi
$players = mysqli_query($conn, "SELECT * FROM players ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quản lý người chơi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <h2 class="mb-4">Danh sách người chơi</h2>

    <form method="POST" class="mb-4">
        <div class="input-group">
            <input type="text" name="name" class="form-control" placeholder="Tên người chơi" required>
            <button class="btn btn-success" type="submit">+ Thêm</button>
        </div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Tên</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($p = mysqli_fetch_assoc($players)) : ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <a href="index.php" class="btn btn-secondary">Quay lại</a>
</body>
</html>
