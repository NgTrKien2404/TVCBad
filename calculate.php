<?php
// db.php
$host = 'localhost';
$dbname = 'badminton_player';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Kết nối CSDL thất bại: ' . $conn->connect_error);
}
$conn->set_charset('utf8');
?>

<?php
require_once 'db.php';

$match_id = (int)($_GET['match_id'] ?? 0);
if (!$match_id) {
    die('Thiếu match_id');
}

// Lấy người chơi của trận này
$playersRes = mysqli_query($conn, "SELECT p.id, p.name FROM match_players mp JOIN players p ON mp.player_id = p.id WHERE mp.match_id = $match_id");
$players = [];
while ($r = mysqli_fetch_assoc($playersRes)) {
    $players[(int)$r['id']] = $r['name'];
}
if (count($players) === 0) {
    die('Không tìm thấy trận hoặc người chơi cho match_id này.');
}

// Lấy các set của trận
$setsRes = mysqli_query($conn, "SELECT * FROM sets WHERE match_id = $match_id ORDER BY id ASC");
$sets = [];
while ($s = mysqli_fetch_assoc($setsRes)) {
    $s['winning_team'] = json_decode($s['winning_team'], true) ?: [];
    $s['losing_team']  = json_decode($s['losing_team'], true) ?: [];
    $s['shuttles_used'] = (int)$s['shuttles_used'];
    $sets[] = $s;
}

// Giá mỗi quả cầu (mặc định 22000), có thể nhập qua GET
$price = isset($_GET['price']) ? (int)$_GET['price'] : 22000;
if ($price <= 0) $price = 22000;

// Tính balance (đơn vị: VND, integer)
$balance = array_fill_keys(array_keys($players), 0);
$set_breakdown = [];

foreach ($sets as $set) {
    $total_cost = $set['shuttles_used'] * $price;

    $winners = array_values(array_map('intval', $set['winning_team']));
    $losers  = array_values(array_map('intval', $set['losing_team']));

    $winner_count = max(1, count($winners));
    $loser_count  = max(1, count($losers));

    // Phân phối: losers trả tổng (chia đều giữa losers), winners nhận (không hiển thị sau này)
    $base_loser = intdiv($total_cost, $loser_count);
    $rem_loser = $total_cost - $base_loser * $loser_count;
    $loser_amounts = [];
    for ($i = 0; $i < $loser_count; $i++) {
        $loser_amounts[] = $base_loser + ($i < $rem_loser ? 1 : 0);
    }

    $base_winner = intdiv($total_cost, $winner_count);
    $rem_winner = $total_cost - $base_winner * $winner_count;
    $winner_amounts = [];
    for ($i = 0; $i < $winner_count; $i++) {
        $winner_amounts[] = $base_winner + ($i < $rem_winner ? 1 : 0);
    }

    // Cập nhật balance: losers âm, winners dương
    foreach ($losers as $i => $uid) {
        if (!isset($balance[$uid])) continue;
        $balance[$uid] -= $loser_amounts[$i];
    }
    foreach ($winners as $i => $uid) {
        if (!isset($balance[$uid])) continue;
        $balance[$uid] += $winner_amounts[$i];
    }

    $set_breakdown[] = [
        'id' => $set['id'],
        'losers'  => array_map(fn($id) => $players[$id] ?? ("#".$id), $losers),
        'shuttles' => $set['shuttles_used'],
        'total_cost' => $total_cost,
        'loser_amounts' => $loser_amounts,
    ];
}

// Lọc chỉ những người thua (balance < 0)
$debtors = [];
foreach ($balance as $uid => $val) {
    if ($val < 0) $debtors[$uid] = -$val; // lưu dương (số tiền phải trả)
}
$total_owed = array_sum($debtors);

// Hiển thị
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tính tiền trận #<?= htmlspecialchars($match_id) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="mb-0">Tổng kết tiền - Trận #<?= htmlspecialchars($match_id) ?></h2>
        <a href="index.php" class="btn btn-secondary">Quay lại</a>
    </div>

    <form method="GET" class="row g-2 align-items-end mb-4">
        <input type="hidden" name="match_id" value="<?= $match_id ?>">
        <div class="col-auto">
            <label class="form-label">Giá 1 quả cầu (VND)</label>
            <input type="number" class="form-control" name="price" value="<?= htmlspecialchars($price) ?>" min="1000" step="500">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary" type="submit">Áp dụng</button>
        </div>
    </form>

    <h5>Tổng số tiền người thua cần trả</h5>
    <?php if (empty($debtors)): ?>
        <div class="alert alert-info">Không có ai phải trả tiền trong trận này.</div>
    <?php else: ?>
        <table class="table table-bordered mb-4">
            <thead class="table-light"><tr><th>Người thua</th><th>Số tiền phải trả (VND)</th></tr></thead>
            <tbody>
                <?php foreach ($debtors as $uid => $amt): ?>
                    <tr>
                        <td><?= htmlspecialchars($players[$uid] ?? ("#".$uid)) ?></td>
                        <td class="text-danger fw-semibold"><?= number_format($amt) ?> VND</td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-secondary">
                    <td><strong>Tổng cộng</strong></td>
                    <td><strong><?= number_format($total_owed) ?> VND</strong></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <h6 class="text-muted">Chi tiết từng set (chỉ hiển thị đội thua và phân chia cho mỗi người thua)</h6>
    <?php if (count($set_breakdown) === 0): ?>
        <div class="alert alert-info">Chưa có set nào được ghi cho trận này.</div>
    <?php else: ?>
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Set</th>
                    <th>Đội thua</th>
                    <th>Số cầu</th>
                    <th>Tổng tiền (VND)</th>
                    <th>Mỗi người thua</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($set_breakdown as $s): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($s['id']) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $s['losers'])) ?></td>
                        <td><?= (int)$s['shuttles'] ?></td>
                        <td><?= number_format($s['total_cost']) ?></td>
                        <td>
                            <?php
                            $lines = [];
                            foreach ($s['losers'] as $i => $name) {
                                $amt = $s['loser_amounts'][$i] ?? 0;
                                $lines[] = htmlspecialchars($name) . ' : ' . number_format($amt) . ' VND';
                            }
                            echo implode('<br>', $lines);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>
