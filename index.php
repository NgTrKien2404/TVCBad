<?php
// index.php — Quản lý tiền cầu lông (chỉ tính tiền phải trả, không ai được nhận)
// Yêu cầu: db.php, bảng: players, matches, match_players, sets

require_once 'db.php';
mysqli_set_charset($conn, 'utf8');

// =============== CẤU HÌNH ===============
$price = isset($_GET['price']) ? (int)$_GET['price'] : 22000;
if ($price <= 0) $price = 22000;

// =============== HÀM TIỆN ÍCH ===============
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetch_all($res) {
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) $out[] = $row;
    return $out;
}

// =============== XỬ LÝ GHI SET ===============
// Form ghi set: chỉ chọn đội thua; đội thắng = phần còn lại (để lưu DB)
// TÍNH TIỀN: chỉ ghi nợ cho đội thua; KHÔNG cộng cho đội thắng
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_set') {
    $match_id      = (int)($_POST['match_id'] ?? 0);
    $losing_team   = array_map('intval', $_POST['losing_team'] ?? []);
    $shuttles_used = (int)($_POST['shuttles_used'] ?? 0);

    if ($match_id <= 0)              $errors[] = 'Thiếu match_id.';
    if ($shuttles_used <= 0)         $errors[] = 'Số quả cầu phải > 0.';
    if (count($losing_team) < 1)     $errors[] = 'Chọn ít nhất 1 người thua.';

    if (!$errors) {
        // Lấy toàn bộ người chơi của trận
        $all = fetch_all(mysqli_query(
            $conn,
            "SELECT p.id FROM match_players mp JOIN players p ON mp.player_id = p.id WHERE mp.match_id = {$match_id}"
        ));
        $all_ids = array_map(fn($r)=> (int)$r['id'], $all);
        if (!$all_ids) $errors[] = 'Không tìm thấy người chơi cho trận này.';

        // losing_team phải thuộc all_ids
        foreach ($losing_team as $uid) {
            if (!in_array($uid, $all_ids, true)) { $errors[] = 'Có người thua không thuộc trận này.'; break; }
        }

        if (!$errors) {
            $winning_team = array_values(array_diff($all_ids, $losing_team));
            if (!$winning_team) $errors[] = 'Không thể xác định đội thắng (phần còn lại rỗng).';
        }

        if (!$errors) {
            $win_json  = json_encode($winning_team);
            $lose_json = json_encode($losing_team);
            $stmt = $conn->prepare("INSERT INTO sets (match_id, winning_team, losing_team, shuttles_used) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = 'Không chuẩn bị được câu lệnh SQL.';
            } else {
                $stmt->bind_param("issi", $match_id, $win_json, $lose_json, $shuttles_used);
                $stmt->execute();
                $stmt->close();
                header("Location: index.php");
                exit();
            }
        }
    }
}

// =============== LẤY DANH SÁCH TRẬN ===============
$matches = fetch_all(mysqli_query($conn, "SELECT * FROM matches ORDER BY id DESC"));
$show_add_set_for = (int)($_GET['add_set'] ?? 0);

// =============== TÍNH NỢ TOÀN HỆ THỐNG (CHỈ ĐỘI THUA CHIA) ===============
$allPlayers = [];
foreach (fetch_all(mysqli_query($conn, "SELECT id, name FROM players")) as $row) {
    $allPlayers[(int)$row['id']] = $row['name'];
}
$debts = array_fill_keys(array_keys($allPlayers), 0); // số tiền phải trả của từng người

$setsRes = mysqli_query($conn, "SELECT losing_team, shuttles_used FROM sets");
while ($s = mysqli_fetch_assoc($setsRes)) {
    $losers  = array_values(array_map('intval', json_decode($s['losing_team'] ?? '[]', true) ?: []));
    $sh      = (int)$s['shuttles_used'];
    if ($sh <= 0 || count($losers) < 1) continue;

    $total = $sh * $price;

    // Chia đều kiểu integer (đảm bảo không bị lệch tổng)
    $cnt = count($losers);
    $base = intdiv($total, $cnt);
    $rem  = $total - $base * $cnt;
    foreach ($losers as $i => $uid) {
        if (!isset($debts[$uid])) $debts[$uid] = 0;
        $debts[$uid] += $base + ($i < $rem ? 1 : 0);
    }
}
// loại người không nợ
$debts = array_filter($debts, fn($v)=> $v > 0);
$total_owed = array_sum($debts);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quản lý tiền cầu lông</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Danh sách trận đấu</h2>
        <div class="d-flex gap-2">
            <a href="add_match.php" class="btn btn-primary">+ Thêm trận mới</a>
            <a href="players.php" class="btn btn-secondary">Người chơi</a>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <table class="table table-bordered mb-4 align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Ngày</th>
                <th>Người chơi</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($matches as $m): ?>
            <?php
            $mid = (int)$m['id'];
            $ps  = fetch_all(mysqli_query($conn,
                   "SELECT p.id, p.name FROM match_players mp JOIN players p ON mp.player_id = p.id WHERE mp.match_id = {$mid}"));
            $names = array_map(fn($r)=> $r['name'], $ps);
            ?>
            <tr>
                <td><?= $mid ?></td>
                <td><?= h($m['match_date']) ?></td>
                <td><?= h(implode(', ', $names)) ?></td>
                <td class="d-flex gap-2">
                    <a href="index.php?add_set=<?= $mid ?>" class="btn btn-sm btn-success">Ghi set</a>
                    <a href="calculate.php?match_id=<?= $mid ?>" class="btn btn-sm btn-warning">Xem chi tiết</a>
                </td>
            </tr>

            <?php if ($show_add_set_for === $mid): ?>
                <tr><td colspan="4">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Ghi set cho trận #<?= $mid ?></h5>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="add_set">
                                <input type="hidden" name="match_id" value="<?= $mid ?>">

                                <div class="col-12">
                                    <label class="form-label">Chọn người thua (đội thắng = phần còn lại)</label><br>
                                    <?php foreach ($ps as $p): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="losing_team[]" value="<?= (int)$p['id'] ?>" id="lose<?= (int)$p['id'] ?>">
                                            <label class="form-check-label" for="lose<?= (int)$p['id'] ?>"><?= h($p['name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Số quả cầu</label>
                                    <input type="number" name="shuttles_used" class="form-control" min="1" required>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Lưu set</button>
                                    <a href="index.php" class="btn btn-secondary">Hủy</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="d-flex justify-content-between align-items-end mb-2">
        <h4 class="mb-0">Tổng số tiền phải trả theo người (VND)</h4>
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label mb-0">Giá 1 quả cầu</label>
                <input type="number" name="price" class="form-control" value="<?= h($price) ?>" min="1000" step="500">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary">Áp dụng</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!$debts): ?>
                <div class="alert alert-info mb-0">Không có ai phải trả tiền.</div>
            <?php else: ?>
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr><th>Người chơi</th><th>Số tiền phải trả</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($debts as $uid => $amt): ?>
                            <tr>
                                <td><?= h($allPlayers[$uid] ?? ("#".$uid)) ?></td>
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
        </div>
    </div>

</body>
</html>
