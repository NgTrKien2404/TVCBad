<?php
// add_match.php
require_once 'db.php';

// Xử lý thêm người chơi mới trực tiếp từ form
if (isset($_POST['new_player_name']) && trim($_POST['new_player_name']) !== '') {
    $new_name = trim($_POST['new_player_name']);
    $stmt = $conn->prepare("INSERT INTO players (name) VALUES (?)");
    $stmt->bind_param("s", $new_name);
    $stmt->execute();
    header("Location: add_match.php");
    exit();
}

// Xử lý form gửi lên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_date'])) {
    $match_date = $_POST['match_date'];
    $player_ids = $_POST['players'] ?? [];

    if (count($player_ids) < 4) {
        die("Cần chọn ít nhất 4 người chơi để tạo trận đấu.");
    }

    // Thêm vào bảng matches
    $stmt = $conn->prepare("INSERT INTO matches (match_date) VALUES (?)");
    $stmt->bind_param("s", $match_date);
    $stmt->execute();
    $match_id = $stmt->insert_id;

    // Thêm người chơi vào match_players
    foreach ($player_ids as $pid) {
        $stmt2 = $conn->prepare("INSERT INTO match_players (match_id, player_id) VALUES (?, ?)");
        $stmt2->bind_param("ii", $match_id, $pid);
        $stmt2->execute();
    }

    header("Location: index.php");
    exit();
}

// Lấy danh sách người chơi
$players = mysqli_query($conn, "SELECT * FROM players");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Thêm trận mới</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <h2 class="mb-4">Tạo trận cầu mới</h2>

    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="match_date" class="form-label">Ngày thi đấu</label>
            <input type="date" class="form-control" name="match_date" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Chọn người chơi (ít nhất 4)</label><br>
            <?php while ($p = mysqli_fetch_assoc($players)) : ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="players[]" value="<?= $p['id'] ?>">
                    <label class="form-check-label"><?= htmlspecialchars($p['name']) ?></label>
                </div>
            <?php endwhile; ?>
        </div>

        <button type="submit" class="btn btn-success">Lưu trận</button>
        <a href="index.php" class="btn btn-secondary">Quay lại</a>
    </form>

    <hr>
    <h5>Thêm người chơi mới:</h5>
    <form method="POST" class="row g-2 align-items-center">
        <div class="col-auto">
            <input type="text" name="new_player_name" class="form-control" placeholder="Tên người chơi" required>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary" type="submit">+ Thêm người chơi</button>
        </div>
    </form>
</body>
</html>
