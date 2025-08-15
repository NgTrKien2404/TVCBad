<?php
// add_set.php
require_once 'db.php';

$match_id = $_GET['match_id'] ?? null;
if (!$match_id) {
    die("Thiếu match_id");
}

// Lấy người chơi trong trận này
$players = mysqli_query($conn, "SELECT p.id, p.name FROM match_players mp JOIN players p ON mp.player_id = p.id WHERE mp.match_id = $match_id");
$player_list = [];
while ($row = mysqli_fetch_assoc($players)) {
    $player_list[] = $row;
}

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $winning_team = $_POST['winning_team'];
    $losing_team = $_POST['losing_team'];
    $shuttles_used = (int) $_POST['shuttles_used'];

    // Chuyển mảng thành chuỗi JSON để lưu DB
    $win_json = json_encode($winning_team);
    $lose_json = json_encode($losing_team);

    $stmt = $conn->prepare("INSERT INTO sets (match_id, winning_team, losing_team, shuttles_used) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $match_id, $win_json, $lose_json, $shuttles_used);
    $stmt->execute();

    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ghi set đấu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <h2 class="mb-4">Ghi set đấu</h2>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Đội thua (2 người)</label><br>
            <?php foreach ($player_list as $p) : ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="losing_team[]" value="<?= $p['id'] ?>">
                    <label class="form-check-label"><?= htmlspecialchars($p['name']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Số quả cầu đã dùng</label>
            <input type="number" name="shuttles_used" class="form-control" min="1" required>
        </div>

        <button type="submit" class="btn btn-primary">Lưu set</button>
        <a href="index.php" class="btn btn-secondary">Quay lại</a>
    </form>
</body>
</html>