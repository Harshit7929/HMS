<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';
class PaymentQueryManager {
    private $conn;
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeTables();
    }
    private function initializeTables() {
        $queriesTable = "
            CREATE TABLE IF NOT EXISTS queries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                regNo VARCHAR(50) NOT NULL,
                query TEXT NOT NULL,
                category VARCHAR(50) DEFAULT 'general',
                payment_ref VARCHAR(100) NULL,
                status ENUM('pending', 'in-progress', 'resolved', 'closed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_response_at TIMESTAMP NULL,
                response_count INT DEFAULT 0,
                resolved_at TIMESTAMP NULL
            )
        ";
        $this->conn->query($queriesTable);
        $responsesTable = "
            CREATE TABLE IF NOT EXISTS query_responses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                query_id INT NOT NULL,
                response TEXT NOT NULL,
                responder_type ENUM('student', 'staff') NOT NULL,
                responder_id VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (query_id) REFERENCES queries(id) ON DELETE CASCADE
            )
        ";
        $this->conn->query($responsesTable);
    }
    public function createQuery($regNo, $queryText, $category = 'general', $paymentRef = null) {
        $stmt = $this->conn->prepare("INSERT INTO queries (regNo, query, category, payment_ref, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ssss", $regNo, $queryText, $category, $paymentRef);
        if ($stmt->execute()) {return true;}
        return false;
    }

    public function getQueriesByRegNo($regNo, $status = null, $limit = 10, $offset = 0) {
        $sql = "SELECT q.*, (SELECT COUNT(*) FROM query_responses WHERE query_id = q.id) as response_count 
                FROM queries q WHERE q.regNo = ?";
        $params = [$regNo];
        $types = "s";
        if ($status) {
            $sql .= " AND q.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        $sql .= " ORDER BY q.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $queries = [];
        while ($row = $result->fetch_assoc()) {$queries[] = $row;}
        return $queries;
    }
    public function getQueryDetails($queryId, $regNo) {
        $stmt = $this->conn->prepare("SELECT * FROM queries WHERE id = ? AND regNo = ?");
        $stmt->bind_param("is", $queryId, $regNo);
        $stmt->execute();
        $query = $stmt->get_result()->fetch_assoc();
        if (!$query) {return null;}
        $stmt = $this->conn->prepare("SELECT * FROM query_responses WHERE query_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $queryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $responses = [];
        while ($row = $result->fetch_assoc()) {$responses[] = $row;} 
        $query['responses'] = $responses;
        return $query;
    }
    public function addResponse($queryId, $responseText, $responderType, $responderId) {
        $stmt = $this->conn->prepare("INSERT INTO query_responses (query_id, response, responder_type, responder_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $queryId, $responseText, $responderType, $responderId);
        if (!$stmt->execute()) {return false;}
        $updateStmt = $this->conn->prepare("UPDATE queries SET last_response_at = CURRENT_TIMESTAMP, response_count = response_count + 1 WHERE id = ?");
        $updateStmt->bind_param("i", $queryId);
        return $updateStmt->execute();
    }
    public function updateQueryStatus($queryId, $status, $regNo) {
        $stmt = $this->conn->prepare("UPDATE queries SET status = ? WHERE id = ? AND regNo = ?");
        $stmt->bind_param("sis", $status, $queryId, $regNo);
        return $stmt->execute();
    }
    public function getRelatedQueries($queryId, $category, $queryText, $regNo, $limit = 3) {
        $sql = "SELECT id, query, category, status, created_at 
                FROM queries 
                WHERE id != ? AND regNo = ? AND category = ? 
                ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("issi", $queryId, $regNo, $category, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $relatedQueries = [];
        while ($row = $result->fetch_assoc()) {$relatedQueries[] = $row;}
        if (count($relatedQueries) < $limit) {
            $remainingLimit = $limit - count($relatedQueries);
            $keywords = preg_split('/\s+/', $queryText, -1, PREG_SPLIT_NO_EMPTY);
            $keywordsForSearch = [];
            usort($keywords, function($a, $b) {return strlen($b) - strlen($a);});
            foreach ($keywords as $word) {
                if (strlen($word) >= 4) {
                    $keywordsForSearch[] = $word;
                    if (count($keywordsForSearch) >= 3) break;
                }
            }
            if (!empty($keywordsForSearch)) {
                $searchTerm = '%' . implode('%', $keywordsForSearch) . '%';
                $excludeIds = array_merge([$queryId], array_column($relatedQueries, 'id'));
                $placeholders = rtrim(str_repeat('?,', count($excludeIds)), ',');
                $sql = "SELECT id, query, category, status, created_at 
                        FROM queries 
                        WHERE id NOT IN ($placeholders) AND regNo = ? AND query LIKE ? 
                        ORDER BY created_at DESC LIMIT ?";
                $params = array_merge($excludeIds, [$regNo, $searchTerm, $remainingLimit]);
                $types = str_repeat('i', count($excludeIds)) . 'ssi';
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {$relatedQueries[] = $row;}
            }
        }
        return $relatedQueries;
    }
    public function getQueryStats($regNo) {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'resolved' => 0,
            'closed' => 0
        ];
        $sql = "SELECT status, COUNT(*) as count FROM queries WHERE regNo = ? GROUP BY status";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $regNo);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $status = str_replace('-', '_', $row['status']);
            $stats[$status] = $row['count'];
            $stats['total'] += $row['count'];
        }
        return $stats;
    }
}
$queryManager = new PaymentQueryManager($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_query') {
    $regNo = $_SESSION['user']['regNo'];
    $queryText = $_POST['query'];
    $category = $_POST['category'];
    $paymentRef = $_POST['payment_ref'] ?? null;
    if ($queryManager->createQuery($regNo, $queryText, $category, $paymentRef)) {$_SESSION['success_message'] = "Your query has been submitted successfully.";} 
    else {$_SESSION['error_message'] = "Failed to submit your query. Please try again.";}
    header("Location: payment_queries.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_response') {
    $queryId = $_POST['query_id'];
    $response = $_POST['response'];
    $regNo = $_SESSION['user']['regNo'];
    if ($queryManager->addResponse($queryId, $response, 'student', $regNo)) {$_SESSION['success_message'] = "Your response has been added.";} 
    else {$_SESSION['error_message'] = "Failed to add your response. Please try again.";}
    header("Location: payment_queries.php?view_query=" . $queryId);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $queryId = $_POST['query_id'];
    $status = $_POST['status'];
    $regNo = $_SESSION['user']['regNo'];
    if ($queryManager->updateQueryStatus($queryId, $status, $regNo)) {$_SESSION['success_message'] = "Query status updated to " . ucfirst($status) . ".";} 
    else {$_SESSION['error_message'] = "Failed to update query status. Please try again.";}
    header("Location: payment_queries.php?view_query=" . $queryId);
    exit;
}
$regNo = $_SESSION['user']['regNo'];
$status = $_GET['status'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;
$queries = $queryManager->getQueriesByRegNo($regNo, $status, $limit, $offset);
$queryStats = $queryManager->getQueryStats($regNo);
$viewQuery = null;
$relatedQueries = [];
if (isset($_GET['view_query'])) {
    $queryId = (int)$_GET['view_query'];
    $viewQuery = $queryManager->getQueryDetails($queryId, $regNo);
    if ($viewQuery) {
        $relatedQueries = $queryManager->getRelatedQueries(
            $queryId, 
            $viewQuery['category'], 
            $viewQuery['query'], 
            $regNo
        );
    }
}
$activePage = 'payment_queries';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Queries | Hostel Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/payment_queries.css"> -->
     <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar { min-width: 250px; background-color: #343a40; color: #fff; height: 100vh; position: fixed; left: 0; top: 0; padding-top: 70px; transition: all 0.3s; z-index: 100; }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.75); padding: 12px 20px; border-left: 3px solid transparent; transition: all 0.2s; }
        .sidebar .nav-link:hover { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .nav-link.active { color: #fff; border-left-color: #007bff; background-color: rgba(0, 123, 255, 0.1); }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-header { background-color: #fff; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; height: 60px; }
        .header-logo { color: #343a40; font-weight: 700; font-size: 1.5rem; }
        .main-content { margin-left: 250px; padding-top: 80px; flex: 1; padding-bottom: 30px; }
        .query-card { margin-bottom: 20px; border-left: 3px solid #3498db; transition: transform 0.2s; }
        .query-card:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .query-pending { border-left-color: #f39c12; }
        .query-resolved { border-left-color: #2ecc71; }
        .query-closed { border-left-color: #95a5a6; }
        .query-in-progress { border-left-color: #3498db; }
        .response-bubble { border-radius: 15px; padding: 10px 15px; margin-bottom: 10px; max-width: 80%; }
        .response-student { background-color: #e8f4f8; margin-right: auto; }
        .response-staff { background-color: #f0f0f0; margin-left: auto; }
        .responses-container { max-height: 300px; overflow-y: auto; }
        .badge.bg-pending { background-color: #f39c12; }
        .badge.bg-in-progress { background-color: #3498db; }
        .badge.bg-resolved { background-color: #2ecc71; }
        .badge.bg-closed { background-color: #95a5a6; }
        .stat-card { text-align: center; border-radius: 10px; border: none; box-shadow: 0 0 10px rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .card-body { padding: 1rem; }
        .stat-value { font-size: 2rem; font-weight: bold; margin-bottom: 0; }
        .stat-label { font-size: 0.9rem; color: #6c757d; margin-bottom: 0; }
        .related-query { border-left: 3px solid #6c757d; padding: 10px 15px; margin-bottom: 10px; transition: all 0.2s; }
        .related-query:hover { background-color: #f8f9fa; border-left-color: #007bff; }
        .related-query-pending { border-left-color: #f39c12; }
        .related-query-resolved { border-left-color: #2ecc71; }
        .related-query-closed { border-left-color: #95a5a6; }
        .related-query-in-progress { border-left-color: #3498db; }
        @media (max-width: 768px) { .sidebar { margin-left: -250px; } .sidebar.active { margin-left: 0; } .main-content { margin-left: 0; } .main-content.pushed { margin-left: 250px; } }
     </style>
</head>
<body>
    <header class="main-header d-flex align-items-center px-3">
        <button id="sidebarToggle" class="btn btn-link d-md-none me-2">
            <i class="fas fa-bars"></i>
        </button>
        <div class="d-flex align-items-center">
            <span class="header-logo me-2">
                <i class="fas fa-hotel"></i> HMS
            </span>
            <span class="d-none d-sm-inline">Hostel Management System</span>
        </div>
        <div class="ms-auto d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-1"></i>
                    <span class="d-none d-md-inline"><?php echo $_SESSION['user']['name'] ?? $_SESSION['user']['regNo']; ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <nav class="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'room_booking' ? 'active' : ''; ?>" href="room_booking.php">
                        <i class="fas fa-bed"></i> Room Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'payments' ? 'active' : ''; ?>" href="payment_history.php">
                        <i class="fas fa-credit-card"></i> Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'payment_queries' ? 'active' : ''; ?>" href="payment_queries.php">
                        <i class="fas fa-question-circle"></i> Payment Queries
                    </a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'maintenance' ? 'active' : ''; ?>" href="maintenance.php">
                        <i class="fas fa-tools"></i> Maintenance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'meal_plan' ? 'active' : ''; ?>" href="meal_plan.php">
                        <i class="fas fa-utensils"></i> Meal Plan
                    </a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'notices' ? 'active' : ''; ?>" href="noticeboard.php">
                        <i class="fas fa-bullhorn"></i> Notices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'profile' ? 'active' : ''; ?>" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
            </ul>
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Help & Support</span>
            </h6>
            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link" href="faq.php"><i class="fas fa-question"></i> FAQ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a>
                </li>
            </ul>
        </div>
    </nav>
    <main class="main-content">
        <div class="container">
            <h1 class="mb-4">Payment Queries</h1>
            <div class="row mb-4">
                <div class="col-md-2 col-sm-4 mb-3">
                    <div class="card stat-card bg-light">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['total']; ?></h5>
                            <p class="stat-label">Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 mb-3">
                    <div class="card stat-card bg-warning bg-opacity-10">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['pending']; ?></h5>
                            <p class="stat-label">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 mb-3">
                    <div class="card stat-card bg-primary bg-opacity-10">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['in_progress']; ?></h5>
                            <p class="stat-label">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 mb-3">
                    <div class="card stat-card bg-success bg-opacity-10">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['resolved']; ?></h5>
                            <p class="stat-label">Resolved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 mb-3">
                    <div class="card stat-card bg-secondary bg-opacity-10">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['closed']; ?></h5>
                            <p class="stat-label">Closed</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($viewQuery): ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Query Details</h5>
                                <a href="payment_queries.php" class="btn btn-sm btn-light">Back to List</a>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h4><?php echo ucfirst(str_replace('_', ' ', $viewQuery['category'])); ?></h4>
                                    <span class="badge bg-<?php echo $viewQuery['status']; ?>"><?php echo ucfirst($viewQuery['status']); ?></span>
                                </div>
                                <p class="text-muted">Reference #: <?php echo $viewQuery['id']; ?> · Created: <?php echo date('d M Y H:i', strtotime($viewQuery['created_at'])); ?></p>
                                <?php if ($viewQuery['payment_ref']): ?>
                                    <p class="text-muted">Payment Reference: <?php echo $viewQuery['payment_ref']; ?></p>
                                <?php endif; ?>
                                
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h6>Original Query:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($viewQuery['query'])); ?></p>
                                    </div>
                                </div>
                                <h5 class="mt-4 mb-3">Responses</h5>
                                <div class="responses-container mb-4">
                                    <?php if (empty($viewQuery['responses'])): ?>
                                        <div class="alert alert-info">No responses yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($viewQuery['responses'] as $response): ?>
                                            <div class="d-flex mb-3">
                                                <div class="response-bubble <?php echo $response['responder_type'] == 'student' ? 'response-student' : 'response-staff'; ?>">
                                                    <div class="small text-muted mb-1">
                                                        <?php echo $response['responder_type'] == 'student' ? 'You' : 'Staff'; ?> · 
                                                        <?php echo date('d M Y H:i', strtotime($response['created_at'])); ?>
                                                    </div>
                                                    <div><?php echo nl2br(htmlspecialchars($response['response'])); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($viewQuery['status'] != 'closed'): ?>
                                    <form action="payment_queries.php" method="post" class="mb-4">
                                        <input type="hidden" name="action" value="add_response">
                                        <input type="hidden" name="query_id" value="<?php echo $viewQuery['id']; ?>">
                                        <div class="form-group mb-3">
                                            <label for="response" class="form-label">Add Response</label>
                                            <textarea class="form-control" id="response" name="response" rows="3" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Submit Response</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($viewQuery['status'] != 'closed'): ?>
                                    <hr>
                                    <h5 class="mb-3">Update Status</h5>
                                    <form action="payment_queries.php" method="post" class="d-flex align-items-center">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="query_id" value="<?php echo $viewQuery['id']; ?>">
                                        <select name="status" class="form-select me-2" style="max-width: 200px;">
                                            <option value="pending" <?php echo $viewQuery['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in-progress" <?php echo $viewQuery['status'] == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $viewQuery['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="closed" <?php echo $viewQuery['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                        <button type="submit" class="btn btn-outline-primary">Update</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">Related Queries</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($relatedQueries)): ?>
                                    <p class="text-muted">No related queries found.</p>
                                <?php else: ?>
                                    <?php foreach ($relatedQueries as $related): ?>
                                        <div class="related-query related-query-<?php echo $related['status']; ?>">
                                            <h6 class="mb-1">
                                                <a href="payment_queries.php?view_query=<?php echo $related['id']; ?>">
                                                    <?php echo htmlspecialchars(substr($related['query'], 0, 60) . (strlen($related['query']) > 60 ? '...' : '')); ?>
                                                </a>
                                            </h6>
                                            <div class="d-flex justify-content-between align-items-center small text-muted">
                                                <span><?php echo ucfirst($related['category']); ?></span>
                                                <span><?php echo date('d M Y', strtotime($related['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">Tips</h5>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>Keep your responses clear and concise</li>
                                    <li>Provide any requested information promptly</li>
                                    <li>Mark as "Resolved" once your issue is fixed</li>
                                    <li>Mark as "Closed" when no further action is needed</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Your Queries</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <div class="btn-group" role="group">
                                        <a href="payment_queries.php" class="btn btn-sm <?php echo !isset($_GET['status']) ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                                        <a href="payment_queries.php?status=pending" class="btn btn-sm <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'btn-primary' : 'btn-outline-primary'; ?>">Pending</a>
                                        <a href="payment_queries.php?status=in-progress" class="btn btn-sm <?php echo isset($_GET['status']) && $_GET['status'] == 'in-progress' ? 'btn-primary' : 'btn-outline-primary'; ?>">In Progress</a>
                                        <a href="payment_queries.php?status=resolved" class="btn btn-sm <?php echo isset($_GET['status']) && $_GET['status'] == 'resolved' ? 'btn-primary' : 'btn-outline-primary'; ?>">Resolved</a>
                                        <a href="payment_queries.php?status=closed" class="btn btn-sm <?php echo isset($_GET['status']) && $_GET['status'] == 'closed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Closed</a>
                                    </div>
                                </div>
                                <?php if (empty($queries)): ?>
                                    <div class="alert alert-info">
                                        No queries found. 
                                        <?php if (isset($_GET['status'])): ?>
                                            <a href="payment_queries.php">View all queries</a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($queries as $query): ?>
                                        <div class="card query-card query-<?php echo $query['status']; ?> mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h5 class="card-title mb-0">
                                                        <a href="payment_queries.php?view_query=<?php echo $query['id']; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $query['category'])); ?>
                                                        </a>
                                                    </h5>
                                                    <span class="badge bg-<?php echo $query['status']; ?>"><?php echo ucfirst($query['status']); ?></span>
                                                </div>
                                                <p class="card-text text-truncate"><?php echo htmlspecialchars($query['query']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        Created: <?php echo date('d M Y', strtotime($query['created_at'])); ?>
                                                        <?php if ($query['last_response_at']): ?>
                                                            · Last response: <?php echo date('d M Y', strtotime($query['last_response_at'])); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                    <div>
                                                        <?php if ($query['response_count'] > 0): ?>
                                                            <span class="badge bg-secondary me-2"><?php echo $query['response_count']; ?> responses</span>
                                                        <?php endif; ?>
                                                        <a href="payment_queries.php?view_query=<?php echo $query['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php
                                    $totalQueries = count($queryManager->getQueriesByRegNo($regNo, $status, 1000, 0));
                                    $totalPages = ceil($totalQueries / $limit);
                                    if ($totalPages > 1):
                                    ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="payment_queries.php?<?php echo isset($_GET['status']) ? 'status=' . $_GET['status'] . '&' : ''; ?>page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                                    <a class="page-link" href="payment_queries.php?<?php echo isset($_GET['status']) ? 'status=' . $_GET['status'] . '&' : ''; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="payment_queries.php?<?php echo isset($_GET['status']) ? 'status=' . $_GET['status'] . '&' : ''; ?>page=<?php echo $page + 1; ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Submit New Query</h5>
                            </div>
                            <div class="card-body">
                                <form action="payment_queries.php" method="post">
                                    <input type="hidden" name="action" value="new_query">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="general">General Inquiry</option>
                                            <option value="payment_issue">Payment Issue</option>
                                            <option value="refund_request">Refund Request</option>
                                            <option value="fee_structure">Fee Structure</option>
                                            <option value="receipt_issue">Receipt/Invoice Issue</option>
                                            <option value="payment_deadline">Payment Deadline</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="payment_ref" class="form-label">Payment Reference (Optional)</label>
                                        <input type="text" class="form-control" id="payment_ref" name="payment_ref" placeholder="Reference number, transaction ID, etc.">
                                    </div>
                                    <div class="mb-3">
                                        <label for="query" class="form-label">Your Query</label>
                                        <textarea class="form-control" id="query" name="query" rows="4" required placeholder="Describe your query in detail..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Submit Query</button>
                                </form>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">Frequently Asked Questions</h5>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="paymentFAQ">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingOne">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                                How long does it take to get a response?
                                            </button>
                                        </h2>
                                        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#paymentFAQ">
                                            <div class="accordion-body">
                                                Most queries are responded to within 24-48 hours during working days.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingTwo">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                                How do I check my payment status?
                                            </button>
                                        </h2>
                                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#paymentFAQ">
                                            <div class="accordion-body">
                                                You can check your payment status in the "Payments" section of the dashboard. All successful payments will show as "Complete" with a confirmation number.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingThree">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                                When should I mark a query as "Resolved"?
                                            </button>
                                        </h2>
                                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#paymentFAQ">
                                            <div class="accordion-body">
                                                Mark a query as "Resolved" when your issue has been addressed to your satisfaction. If you need further assistance, leave it as "In Progress".
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('pushed');
        });
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>