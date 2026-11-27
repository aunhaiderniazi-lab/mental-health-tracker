<?php
session_start();
include 'connection.php';

// Protect page: redirect to login if not signed in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Handle Add / Edit / Delete requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!csrf_valid()) {
        http_response_code(403);
        die("Invalid form token. Please reload the page and try again.");
    }

    $action = $_POST['action'] ?? 'save';

    if ($action == 'delete' && isset($_POST['entry_id'])) {
        $entryId = (int)$_POST['entry_id'];
        $stmt = $conn->prepare("DELETE FROM journal_entries WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $entryId, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        header("Location: final.php?status=" . ($ok ? 'deleted' : 'error') . "#tracker");
        exit();
    }

    if ($action == 'save') {
        $entryId   = (int)($_POST['entry_id'] ?? 0);
        $entryDate = $_POST['entry_date'] ?? '';
        $mood      = (int)($_POST['mood'] ?? 3);
        $content   = trim($_POST['content'] ?? '');

        if ($mood < 1 || $mood > 5) $mood = 3;

        $parsedDate = DateTime::createFromFormat('!Y-m-d', $entryDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $entryDate) {
            header("Location: final.php?status=error#tracker");
            exit();
        }

        if (!empty($content)) {
            if ($entryId > 0) {
                // Update existing entry
                $stmt = $conn->prepare("UPDATE journal_entries SET entry_date = ?, mood = ?, content = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("sisii", $entryDate, $mood, $content, $entryId, $userId);
            } else {
                // Insert new entry
                $stmt = $conn->prepare("INSERT INTO journal_entries (user_id, entry_date, mood, content) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isis", $userId, $entryDate, $mood, $content);
            }
            $ok = $stmt->execute();
            $stmt->close();

            header("Location: final.php?status=" . ($ok ? 'saved' : 'error') . "#tracker");
            exit();
        }
    }
}

// Load all entries for this user
$stmt = $conn->prepare("SELECT id, entry_date, mood, content FROM journal_entries WHERE user_id = ? ORDER BY entry_date DESC, id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$entries = [];
while ($row = $result->fetch_assoc()) {
    $entries[] = $row;
}
$stmt->close();

// Calculate stats for dashboard & charts
$totalEntries = count($entries);
$moodSum = 0;
$moodCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$dayScores = [];
$dayCounts = [];

foreach ($entries as $item) {
    $m = (int)$item['mood'];
    $moodSum += $m;
    $moodCounts[$m]++;

    $day = date('l', strtotime($item['entry_date']));
    if (!isset($dayScores[$day])) {
        $dayScores[$day] = 0;
        $dayCounts[$day] = 0;
    }
    $dayScores[$day] += $m;
    $dayCounts[$day]++;
}

$avgMood = $totalEntries > 0 ? round($moodSum / $totalEntries, 1) : 0;

$bestDay = "N/A";
$highestAvg = 0;
foreach ($dayScores as $day => $sum) {
    $avg = $sum / $dayCounts[$day];
    if ($avg > $highestAvg) {
        $highestAvg = $avg;
        $bestDay = $day;
    }
}

// Last 7 entries in chronological order for the line chart
$lastSeven = array_reverse(array_slice($entries, 0, 7));
$chartDates = [];
$chartMoods = [];
foreach ($lastSeven as $item) {
    $chartDates[] = date('M d', strtotime($item['entry_date']));
    $chartMoods[] = (int)$item['mood'];
}

$moodLabels = [
    5 => ['text' => 'Excellent', 'color' => 'success',   'icon' => 'fa-laugh-beam'],
    4 => ['text' => 'Good',      'color' => 'info',      'icon' => 'fa-smile'],
    3 => ['text' => 'Neutral',   'color' => 'warning',   'icon' => 'fa-meh'],
    2 => ['text' => 'Not Great', 'color' => 'danger',    'icon' => 'fa-frown'],
    1 => ['text' => 'Difficult', 'color' => 'secondary', 'icon' => 'fa-sad-tear']
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --maroon: #7a1c32;
            --maroon-dark: #561222;
            --gold: #d4a359;
            --teal: #2e7d6b;
        }
        [data-bs-theme="dark"] body {
            background-color: #140b0e;
            color: #f8f2f3;
        }
        [data-bs-theme="dark"] .card {
            background-color: #1f1216;
            border-color: #3d1d26;
        }
        [data-bs-theme="light"] body {
            background-color: #faf6f4;
            color: #241317;
        }
        .navbar-maroon {
            background: linear-gradient(90deg, var(--maroon-dark), var(--maroon));
        }
        .hero-box {
            background: linear-gradient(135deg, rgba(122, 28, 50, 0.25), rgba(212, 163, 89, 0.12));
            border-bottom: 1px solid rgba(212, 163, 89, 0.25);
            padding: 50px 0;
        }
        .btn-maroon {
            background-color: var(--maroon);
            border-color: var(--maroon);
            color: #fff;
        }
        .btn-maroon:hover {
            background-color: var(--maroon-dark);
            border-color: var(--maroon-dark);
            color: #fff;
        }
        .gold-accent { color: var(--gold); }
        .mood-btn.active {
            outline: 2px solid var(--gold);
            font-weight: 600;
        }
        .entry-box {
            border-left: 4px solid var(--maroon);
            padding: 14px 16px;
            border-radius: 8px;
        }
        .chart-wrap {
            height: 250px;
            position: relative;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-maroon sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            <i class="fas fa-brain gold-accent me-2"></i>Mental Health Tracker
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#tracker">Tracker</a></li>
                <li class="nav-item"><a class="nav-link" href="#insights">Insights</a></li>
                <li class="nav-item">
                    <button id="themeBtn" class="btn btn-sm btn-outline-light" type="button">
                        <i class="fas fa-adjust me-1"></i>Theme
                    </button>
                </li>
                <li class="nav-item"><span class="nav-link text-white">Hello, <?= htmlspecialchars($userName) ?></span></li>
                <li class="nav-item"><a href="logout.php" class="btn btn-sm btn-warning text-dark fw-semibold">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero-box text-center">
    <div class="container">
        <h1 class="display-6 fw-bold mb-2">Welcome back, <span class="gold-accent"><?= htmlspecialchars($userName) ?></span></h1>
        <p class="text-secondary mb-4">Check in with yourself today, write down your thoughts, and track your mood patterns.</p>
        <a href="#tracker" class="btn btn-maroon px-4 rounded-pill">Start Today's Entry</a>
    </div>
</section>

<section id="tracker" class="py-5">
    <div class="container">
        <?php if (isset($_GET['status'])): ?>
            <div class="alert alert-<?= $_GET['status'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
                <?php
                $status = $_GET['status'];
                if ($status === 'deleted') {
                    echo 'Entry removed.';
                } elseif ($status === 'error') {
                    echo 'Could not save the entry. Please check the date and try again.';
                } else {
                    echo 'Journal entry saved!';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Mood Picker -->
            <div class="col-lg-5">
                <div class="card p-4 h-100">
                    <h5 class="fw-bold mb-3"><i class="fas fa-smile gold-accent me-2"></i>1. How are you feeling?</h5>
                    <div class="d-grid gap-2" id="moodList">
                        <?php foreach ($moodLabels as $score => $info): ?>
                            <button type="button"
                                    class="btn btn-outline-<?= $info['color'] ?> text-start py-2 px-3 mood-btn <?= $score == 4 ? 'active' : '' ?>"
                                    data-mood="<?= $score ?>">
                                <i class="fas <?= $info['icon'] ?> me-2"></i><?= $info['text'] ?> (<?= $score ?>/5)
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Journal Form -->
            <div class="col-lg-7">
                <div class="card p-4 h-100">
                    <h5 class="fw-bold mb-3" id="formHeading"><i class="fas fa-book gold-accent me-2"></i>2. Daily Journal</h5>
                    <form method="POST" action="final.php" id="journalForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="entry_id" id="entryId" value="0">
                        <input type="hidden" name="mood" id="moodField" value="4">

                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="entry_date" id="entryDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Your Thoughts</label>
                            <textarea name="content" id="entryContent" class="form-control" rows="4" placeholder="Write about your day..." required></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-maroon px-4"><i class="fas fa-save me-1"></i>Save Entry</button>
                            <button type="button" id="cancelEdit" class="btn btn-outline-secondary d-none">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Saved Entries -->
        <div class="card p-4 mt-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-history gold-accent me-2"></i>Past Entries (<?= $totalEntries ?>)</h5>
            <?php if ($totalEntries == 0): ?>
                <p class="text-secondary mb-0">No entries saved yet. Add your first check-in above!</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($entries as $row):
                        $m = (int)$row['mood'];
                        $info = $moodLabels[$m];
                    ?>
                        <div class="entry-box bg-body-tertiary">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><?= date('l, M j, Y', strtotime($row['entry_date'])) ?></strong>
                                <span class="badge bg-<?= $info['color'] ?>">
                                    <i class="fas <?= $info['icon'] ?> me-1"></i><?= $info['text'] ?> (<?= $m ?>/5)
                                </span>
                            </div>
                            <p class="mb-2"><?= nl2br(htmlspecialchars($row['content'])) ?></p>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button"
                                        class="btn btn-sm btn-outline-warning edit-btn"
                                        data-id="<?= $row['id'] ?>"
                                        data-date="<?= $row['entry_date'] ?>"
                                        data-mood="<?= $m ?>"
                                        data-content="<?= htmlspecialchars($row['content'], ENT_QUOTES) ?>">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <form method="POST" action="final.php" onsubmit="return confirm('Delete this entry?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="entry_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Insights Section -->
<section id="insights" class="py-5 border-top border-secondary-subtle">
    <div class="container">
        <h4 class="fw-bold mb-4"><i class="fas fa-chart-line gold-accent me-2"></i>Mood Insights</h4>

        <div class="row g-3 mb-4 text-center">
            <div class="col-md-4">
                <div class="card p-3">
                    <h3 class="gold-accent fw-bold mb-0"><?= $avgMood ?: '—' ?></h3>
                    <small class="text-secondary">Average Mood (1–5)</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3">
                    <h3 class="text-info fw-bold mb-0"><?= $totalEntries ?></h3>
                    <small class="text-secondary">Total Entries</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3">
                    <h3 class="text-success fw-bold mb-0"><?= htmlspecialchars($bestDay) ?></h3>
                    <small class="text-secondary">Best Mood Day</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 h-100">
                    <h6 class="fw-bold mb-3">Recent Mood Trend</h6>
                    <div class="chart-wrap"><canvas id="lineChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card p-4 h-100">
                    <h6 class="fw-bold mb-3">Mood Distribution</h6>
                    <div class="chart-wrap"><canvas id="pieChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="py-4 text-center text-secondary small border-top border-secondary-subtle">
    <div class="container">
        Mental Health Tracker &bull; Intro to Web Technologies Project
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Light / Dark mode switcher
    const root = document.documentElement;
    const savedTheme = localStorage.getItem('mht_theme') || 'dark';
    root.setAttribute('data-bs-theme', savedTheme);

    document.getElementById('themeBtn').addEventListener('click', function() {
        const nextTheme = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', nextTheme);
        localStorage.setItem('mht_theme', nextTheme);
    });

    // Mood button selection
    const moodButtons = document.querySelectorAll('.mood-btn');
    const moodField = document.getElementById('moodField');

    function setMood(val) {
        moodField.value = val;
        moodButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mood === String(val));
        });
    }

    moodButtons.forEach(btn => {
        btn.addEventListener('click', () => setMood(btn.dataset.mood));
    });

    // Edit entry handler
    const cancelBtn = document.getElementById('cancelEdit');
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('entryId').value = this.dataset.id;
            document.getElementById('entryDate').value = this.dataset.date;
            document.getElementById('entryContent').value = this.dataset.content;
            setMood(this.dataset.mood);

            document.getElementById('formHeading').innerHTML = '<i class="fas fa-edit gold-accent me-2"></i>Editing Entry';
            cancelBtn.classList.remove('d-none');
            document.getElementById('tracker').scrollIntoView({ behavior: 'smooth' });
        });
    });

    cancelBtn.addEventListener('click', function() {
        document.getElementById('entryId').value = '0';
        document.getElementById('journalForm').reset();
        document.getElementById('entryDate').value = new Date().toISOString().split('T')[0];
        setMood(4);

        document.getElementById('formHeading').innerHTML = '<i class="fas fa-book gold-accent me-2"></i>2. Daily Journal';
        cancelBtn.classList.add('d-none');
    });

    // Charts
    const dates = <?= json_encode($chartDates ?: ['No entries']) ?>;
    const moods = <?= json_encode($chartMoods ?: [0]) ?>;
    const counts = <?= json_encode([
        $moodCounts[5], $moodCounts[4], $moodCounts[3], $moodCounts[2], $moodCounts[1]
    ]) ?>;

    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Mood Score',
                data: moods,
                borderColor: '#d4a359',
                backgroundColor: 'rgba(122, 28, 50, 0.25)',
                borderWidth: 2,
                pointBackgroundColor: '#7a1c32',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { min: 1, max: 5, ticks: { stepSize: 1 } }
            }
        }
    });

    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Excellent', 'Good', 'Neutral', 'Not Great', 'Difficult'],
            datasets: [{
                data: counts,
                backgroundColor: ['#2e7d6b', '#d4a359', '#e09f3e', '#9e2a2b', '#540b0e']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
</script>
</body>
</html>
