<?php
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_user'])) {
    header("Location: admin_login.php");
    exit;}
require_once 'admin_db.php';
class AdminQueryManager {
    private $conn;  
    public function __construct($connection) { $this->conn = $connection;}
    public function getQueries($status = null, $category = null, $search = null, $limit = 10, $offset = 0) {
        $sql = "SELECT q.*,
                (SELECT COUNT(*) FROM query_responses WHERE query_id = q.id) as response_count,
                (SELECT MAX(created_at) FROM query_responses WHERE query_id = q.id) as last_response
                FROM queries q WHERE 1=1";
        $params = [];
        $types = "";
        if ($status) {
            $sql .= " AND q.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        if ($category) {
            $sql .= " AND q.category = ?";
            $params[] = $category;
            $types .= "s";
        } 
        if ($search) {
            $search = "%$search%";
            $sql .= " AND (q.regNo LIKE ? OR q.query LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $types .= "ss";
        }
        $sql .= " ORDER BY CASE 
                    WHEN q.status = 'pending' THEN 0 
                    WHEN q.status = 'in-progress' THEN 1 
                    WHEN q.status = 'resolved' THEN 2
                    WHEN q.status = 'closed' THEN 3
                END, q.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt = $this->conn->prepare($sql);
        if ($types && !empty($params)) {$stmt->bind_param($types, ...$params);}
        $stmt->execute();
        $result = $stmt->get_result();
        $queries = [];
        while ($row = $result->fetch_assoc()) {$queries[] = $row;}
        return $queries;
    }
    public function getQueryDetails($queryId) {
        $stmt = $this->conn->prepare("SELECT * FROM queries WHERE id = ?");
        $stmt->bind_param("i", $queryId);
        $stmt->execute();
        $query = $stmt->get_result()->fetch_assoc();
        if (!$query) {return null;}
        $studentStmt = $this->conn->prepare("SELECT name, email, phone FROM students WHERE regNo = ?");
        $studentStmt->bind_param("s", $query['regNo']);
        $studentStmt->execute();
        $student = $studentStmt->get_result()->fetch_assoc();
        $stmt = $this->conn->prepare("SELECT r.*, 
                                     CASE WHEN r.responder_type = 'staff' THEN 
                                        (SELECT username FROM admin WHERE id = r.responder_id)
                                     ELSE
                                        r.responder_id
                                     END as responder_name
                                     FROM query_responses r 
                                     WHERE r.query_id = ? 
                                     ORDER BY r.created_at ASC");
        $stmt->bind_param("i", $queryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $responses = [];
        while ($row = $result->fetch_assoc()) {$responses[] = $row;}
        $query['responses'] = $responses;
        $query['student'] = $student;
        return $query;
    }
    public function addResponse($queryId, $responseText, $adminId) {
        $stmt = $this->conn->prepare("INSERT INTO query_responses (query_id, response, responder_type, responder_id) VALUES (?, ?, 'staff', ?)");
        $stmt->bind_param("iss", $queryId, $responseText, $adminId);
        if (!$stmt->execute()) {return false;}
        $updateStmt = $this->conn->prepare("UPDATE queries SET last_response_at = CURRENT_TIMESTAMP, response_count = response_count + 1, status = CASE WHEN status = 'pending' THEN 'in-progress' ELSE status END WHERE id = ?");
        $updateStmt->bind_param("i", $queryId);
        return $updateStmt->execute();
    }
    public function updateQueryStatus($queryId, $status) {
        $stmt = $this->conn->prepare("UPDATE queries SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $queryId);
        return $stmt->execute();
    }
    public function getQueryStats() {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'resolved' => 0,
            'closed' => 0,
            'response_rate' => 0,
            'avg_resolution_time' => 0
        ];
        $sql = "SELECT status, COUNT(*) as count FROM queries GROUP BY status";
        $result = $this->conn->query($sql);
        $totalQueries = 0;
        while ($row = $result->fetch_assoc()) {
            $status = str_replace('-', '_', $row['status']);
            $stats[$status] = $row['count'];
            $totalQueries += $row['count'];
        }
        $stats['total'] = $totalQueries;
        $sql = "SELECT COUNT(*) as responded FROM queries WHERE response_count > 0";
        $result = $this->conn->query($sql);
        $respondedCount = $result->fetch_assoc()['responded'];
        if ($totalQueries > 0) {$stats['response_rate'] = round(($respondedCount / $totalQueries) * 100, 1);}
        $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_time 
                FROM queries WHERE status IN ('resolved', 'closed')";
        $result = $this->conn->query($sql);
        $avgTime = $result->fetch_assoc()['avg_time'];
        $stats['avg_resolution_time'] = $avgTime ? round($avgTime, 1) : 0;
        return $stats;
    }
    public function getAttentionQueries($limit = 5) {
        $sql = "SELECT q.*, 
                TIMESTAMPDIFF(HOUR, q.created_at, NOW()) as hours_since_creation,
                TIMESTAMPDIFF(HOUR, COALESCE(q.last_response_at, q.created_at), NOW()) as hours_since_response
                FROM queries q
                WHERE q.status IN ('pending', 'in-progress') 
                ORDER BY 
                    CASE WHEN q.response_count = 0 THEN 0 ELSE 1 END, 
                    hours_since_response DESC 
                LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $queries = [];
        while ($row = $result->fetch_assoc()) {
            $queries[] = $row;
        }
        return $queries;
    }
    public function getCategories() {
        $sql = "SELECT DISTINCT category FROM queries ORDER BY category";
        $result = $this->conn->query($sql);
        $categories = [];
        while ($row = $result->fetch_assoc()) {$categories[] = $row['category'];}
        return $categories;
    }
}
$queryManager = new AdminQueryManager($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_response') {
    $queryId = $_POST['query_id'];
    $response = $_POST['response'];
    $adminId = $_SESSION['admin_id'];
    if ($queryManager->addResponse($queryId, $response, $adminId)) {$_SESSION['success_message'] = "Your response has been added.";}
    else {$_SESSION['error_message'] = "Failed to add your response. Please try again.";}
    header("Location: admin_payment_queries.php?view_query=" . $queryId);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $queryId = $_POST['query_id'];
    $status = $_POST['status'];
    if ($queryManager->updateQueryStatus($queryId, $status)) {$_SESSION['success_message'] = "Query status updated to " . ucfirst($status) . ".";} 
    else {$_SESSION['error_message'] = "Failed to update query status. Please try again.";}
    header("Location: admin_payment_queries.php?view_query=" . $queryId);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    if (isset($_POST['query_ids']) && isset($_POST['bulk_status'])) {
        $queryIds = $_POST['query_ids'];
        $status = $_POST['bulk_status'];
        $updatedCount = 0;
        foreach ($queryIds as $queryId) {
            if ($queryManager->updateQueryStatus($queryId, $status)) {$updatedCount++;}
        }
        if ($updatedCount > 0) {$_SESSION['success_message'] = "Updated status of $updatedCount queries to " . ucfirst($status) . ".";} 
        else {$_SESSION['error_message'] = "Failed to update query statuses. Please try again.";}
    }
    header("Location: admin_payment_queries.php");
    exit;
}
$queryStats = $queryManager->getQueryStats();
$attentionQueries = $queryManager->getAttentionQueries();
$status = $_GET['status'] ?? null;
$category = $_GET['category'] ?? null;
$search = $_GET['search'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$categories = $queryManager->getCategories();
$queries = $queryManager->getQueries($status, $category, $search, $limit, $offset);
$viewQuery = null;
if (isset($_GET['view_query'])) {
    $queryId = (int)$_GET['view_query'];
    $viewQuery = $queryManager->getQueryDetails($queryId);
}
$activePage = 'payment_queries';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Queries Administration | Hostel Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/payment_queries.css">
    <style>
        body { background-color: #f8f9fa; }
        .main-header { height: 60px; background-color: #343a40; color: white; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1); position: fixed; top: 0; right: 0; left: 0; z-index: 1030; }
        .sidebar { position: fixed; top: 60px; bottom: 0; left: 0; width: 240px; background-color: #343a40; color: #fff; z-index: 1020; transition: all 0.3s; overflow-y: auto; }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.75); padding: 0.75rem 1.25rem; font-weight: 500; border-left: 3px solid transparent; }
        .sidebar .nav-link:hover { color: #fff; border-left-color: #fff; }
        .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); border-left-color: #007bff; }
        .sidebar .nav-link i { margin-right: 0.5rem; width: 20px; text-align: center; }
        .main-content { margin-left: 240px; margin-top: 60px; padding: 20px; transition: margin 0.3s; }
        @media (max-width: 768px) { 
        .sidebar { transform: translateX(-100%); }
        .sidebar.active { transform: translateX(0); }
        .main-content { margin-left: 0; }
        .main-content.pushed { margin-left: 240px; }}
        .stat-card { border-radius: 8px; box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05); transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1); }
        .stat-value { font-size: 1.75rem; font-weight: 700; margin-bottom: 0; }
        .stat-label { margin-bottom: 0; opacity: 0.7; font-weight: 500; }
        .query-card { transition: all 0.2s; border-left: 4px solid #e9ecef; }
        .query-card:hover { box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05); }
        .query-pending { border-left-color: #ffc107; }
        .query-in-progress { border-left-color: #0d6efd; }
        .query-resolved { border-left-color: #198754; }
        .query-closed { border-left-color: #6c757d; }
        .badge.bg-pending { background-color: #ffc107 !important; color: #000; }
        .badge.bg-in-progress { background-color: #0d6efd !important; }
        .badge.bg-resolved { background-color: #198754 !important; }
        .badge.bg-closed { background-color: #6c757d !important; }
        .response-container { max-height: 500px; overflow-y: auto; }
        .response-bubble { padding: 12px; border-radius: 8px; margin-bottom: 15px; max-width: 85%; }
        .response-student { background-color: #f0f2f5; margin-right: auto; }
        .response-staff { background-color: #e3f2fd; margin-left: auto; }
        .attention-needed { background-color: #fff8e1; border-left: 3px solid #ffc107; }
        .checkbox-column { width: 40px; }
        .query-table th, .query-table td { vertical-align: middle; }
    </style>
</head>
<body>
    <header class="main-header d-flex align-items-center px-3">
        <button id="sidebarToggle" class="btn btn-link d-md-none me-2 text-white"><i class="fas fa-bars"></i></button>
        <div class="d-flex align-items-center">
            <span class="header-logo me-2"><i class="fas fa-hotel"></i> HMS</span>
            <span class="d-none d-sm-inline">Admin Dashboard</span>
        </div>
        <div class="ms-auto d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-link text-white dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-shield me-1"></i>
                    <span class="d-none d-md-inline"><?php echo $_SESSION['admin_name'] ?? 'Administrator'; ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="update_profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                    <!-- <li><a class="dropdown-item" href="admin_settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li> -->
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
                    <a class="nav-link <?php echo $activePage == 'dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'students' ? 'active' : ''; ?>" href="manage_students.php">
                        <i class="fas fa-user-graduate"></i> Students</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'rooms' ? 'active' : ''; ?>" href="manage_rooms.php">
                        <i class="fas fa-bed"></i> Rooms Management</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'payments' ? 'active' : ''; ?>" href="payment_history.php">
                        <i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'payment_queries' ? 'active' : ''; ?>" href="payment_queries.php">
                        <i class="fas fa-question-circle"></i> Payment Queries</a></li>
                <!-- <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'maintenance' ? 'active' : ''; ?>" href="admin_maintenance.php">
                        <i class="fas fa-tools"></i> Maintenance</a></li> -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'meal_plans' ? 'active' : ''; ?>" href="manage_mess.php">
                        <i class="fas fa-utensils"></i> Mess</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'notices' ? 'active' : ''; ?>" href="add_notice.php">
                        <i class="fas fa-bullhorn"></i> Notices</a></li>
                <!-- <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'reports' ? 'active' : ''; ?>" href="admin_reports.php">
                        <i class="fas fa-chart-bar"></i> Reports</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'settings' ? 'active' : ''; ?>" href="admin_settings.php">
                        <i class="fas fa-cog"></i> Settings</a></li> -->
            </ul>
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Staff Management</span></h6>
            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'staff' ? 'active' : ''; ?>" href="admin_staff.php">
                        <i class="fas fa-users"></i> Staff Members</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage == 'roles' ? 'active' : ''; ?>" href="admin_roles.php">
                        <i class="fas fa-user-tag"></i> Roles & Permissions</a></li>
            </ul>
        </div>
    </nav>
    <main class="main-content">
        <div class="container-fluid">
            <h1 class="mb-4">Payment Queries Management</h1>
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card stat-card bg-light h-100">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['total']; ?></h5>
                            <p class="stat-label">Total Queries</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card stat-card bg-warning bg-opacity-10 h-100">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['pending']; ?></h5>
                            <p class="stat-label">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card stat-card bg-primary bg-opacity-10 h-100">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['in_progress']; ?></h5>
                            <p class="stat-label">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card stat-card bg-success bg-opacity-10 h-100">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['resolved']; ?></h5>
                            <p class="stat-label">Resolved</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card stat-card bg-secondary bg-opacity-10 h-100">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['closed']; ?></h5>
                            <p class="stat-label">Closed</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6 mb-3">
                    <div class="card stat-card bg-info bg-opacity-10 h-100">
                        <div class="card-body">
                            <h5 class="stat-value"><?php echo $queryStats['avg_resolution_time']; ?>h</h5>
                            <p class="stat-label">Avg. Resolution</p>
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
                                <a href="admin_payment_queries.php" class="btn btn-sm btn-light">Back to List</a>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h4><?php echo ucfirst(str_replace('_', ' ', $viewQuery['category'])); ?></h4>
                                    <span class="badge bg-<?php echo $viewQuery['status']; ?>"><?php echo ucfirst($viewQuery['status']); ?></span>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="text-muted mb-1">Reference #: <?php echo $viewQuery['id']; ?></p>
                                        <p class="text-muted mb-1">Created: <?php echo date('d M Y H:i', strtotime($viewQuery['created_at'])); ?></p>
                                        <?php if ($viewQuery['payment_ref']): ?>
                                            <p class="text-muted mb-1">Payment Reference: <?php echo $viewQuery['payment_ref']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Student:</strong> <?php echo $viewQuery['student']['name'] ?? 'N/A'; ?></p>
                                        <p class="mb-1"><strong>Reg No:</strong> <?php echo $viewQuery['regNo']; ?></p>
                                        <p class="mb-1"><strong>Contact:</strong> 
                                            <?php echo $viewQuery['student']['email'] ?? 'N/A'; ?> 
                                            <?php if (!empty($viewQuery['student']['phone'])): ?>
                                                | <?php echo $viewQuery['student']['phone']; ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="query-content p-3 bg-light rounded mb-4">
                                    <h6>Query Content:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($viewQuery['query'])); ?></p>
                                </div>
                                <h6 class="mb-3">Responses (<?php echo count($viewQuery['responses']); ?>)</h6>
                                <div class="response-container mb-4">
                                    <?php foreach ($viewQuery['responses'] as $response): ?>
                                        <div class="response-bubble <?php echo $response['responder_type'] === 'staff' ? 'response-staff' : 'response-student'; ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold">
                                                    <?php echo $response['responder_type'] === 'staff' ? 'Admin: ' . $response['responder_name'] : 'Student: ' . $viewQuery['student']['name']; ?>
                                                </span>
                                                <small class="text-muted"><?php echo date('d M, H:i', strtotime($response['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($response['response'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($viewQuery['responses']) === 0): ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-comments fa-2x mb-2"></i>
                                            <p>No responses yet</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($viewQuery['status'] !== 'closed'): ?>
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Add Response</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="admin_payment_queries.php">
                                            <input type="hidden" name="action" value="add_response">
                                            <input type="hidden" name="query_id" value="<?php echo $viewQuery['id']; ?>">
                                            <div class="mb-3">
                                                <textarea class="form-control" name="response" rows="4" required placeholder="Type your response here..."></textarea>
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary">Send Response</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Status Management</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="admin_payment_queries.php">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="query_id" value="<?php echo $viewQuery['id']; ?>">
                                    <div class="mb-3">
                                        <label for="statusSelect" class="form-label">Current Status:</label>
                                        <select class="form-select" id="statusSelect" name="status">
                                            <option value="pending" <?php echo $viewQuery['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in-progress" <?php echo $viewQuery['status'] === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $viewQuery['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="closed" <?php echo $viewQuery['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Update Status</button>
                                </form>
                            </div>
                        </div>
                        <div class="card mb-4">
                            <div class="card-header bg-light"><h6 class="mb-0">Query Information</h6></div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Response Time</span>
                                        <?php 
                                        $createdDate = new DateTime($viewQuery['created_at']);
                                        $firstResponse = null;
                                        foreach ($viewQuery['responses'] as $response) {
                                            if ($response['responder_type'] === 'staff') {
                                                $firstResponse = new DateTime($response['created_at']);
                                                break;
                                            }
                                        }
                                        ?>
                                        <span>
                                            <?php if ($firstResponse): ?>
                                                <?php 
                                                $interval = $createdDate->diff($firstResponse);
                                                $hours = $interval->h + ($interval->days * 24);
                                                echo $hours . 'h ' . $interval->i . 'm';
                                                ?>
                                            <?php else: ?>
                                                <?php 
                                                $now = new DateTime();
                                                $interval = $createdDate->diff($now);
                                                $hours = $interval->h + ($interval->days * 24);
                                                ?>
                                                <span class="text-danger"><?php echo $hours; ?>h waiting</span>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Last Updated</span>
                                        <span>
                                            <?php
                                            $lastActivity = $viewQuery['created_at'];
                                            if (!empty($viewQuery['responses'])) {
                                                $lastResponse = end($viewQuery['responses']);
                                                $lastActivity = $lastResponse['created_at'];
                                            }
                                            echo date('d M Y H:i', strtotime($lastActivity));
                                            ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Total Responses</span>
                                        <span class="badge bg-primary rounded-pill"><?php echo count($viewQuery['responses']); ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <?php if ($viewQuery['payment_ref']): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Related Payment</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Reference</span>
                                        <span><?php echo $viewQuery['payment_ref']; ?></span>
                                    </li>
                                </ul>
                                <div class="mt-3">
                                    <a href="admin_payments.php?ref=<?php echo $viewQuery['payment_ref']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="fas fa-external-link-alt me-1"></i> View Payment Details
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-lg-4 col-xl-3 mb-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">Filter Queries</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="admin_payment_queries.php">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="">All Statuses</option>
                                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in-progress" <?php echo $status === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select name="category" class="form-select">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst(str_replace('_', ' ', $cat)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Reg No or Query Content">
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                                        <a href="admin_payment_queries.php" class="btn btn-outline-secondary">Clear Filters</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card mt-4">
                            <div class="card-header bg-warning bg-opacity-10">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i> Needs Attention
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($attentionQueries)): ?>
                                        <li class="list-group-item text-center text-muted py-4">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <p class="mb-0">No urgent queries at the moment</p>
                                        </li>
                                    <?php else: ?>
                                        <?php foreach ($attentionQueries as $query): ?>
                                            <li class="list-group-item attention-needed p-3">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-0">
                                                        <a href="admin_payment_queries.php?view_query=<?php echo $query['id']; ?>">
                                                            #<?php echo $query['id']; ?> - <?php echo ucfirst(str_replace('_', ' ', $query['category'])); ?>
                                                        </a>
                                                    </h6>
                                                    <span class="badge bg-<?php echo $query['status']; ?>"><?php echo ucfirst($query['status']); ?></span>
                                                </div>
                                                <p class="mb-1 small text-truncate"><?php echo htmlspecialchars(substr($query['query'], 0, 80)); ?>...</p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted"><?php echo $query['regNo']; ?></small>
                                                    <small class="text-danger">
                                                        <?php if ($query['response_count'] == 0): ?>
                                                            <i class="fas fa-clock me-1"></i> No response for <?php echo $query['hours_since_creation']; ?>h
                                                        <?php else: ?>
                                                            <i class="fas fa-history me-1"></i> No update for <?php echo $query['hours_since_response']; ?>h
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8 col-xl-9">
                        <div class="card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Payment Queries</h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle bulk-action-btn disabled" type="button" id="bulkActionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        Bulk Actions
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="bulkActionDropdown">
                                        <li>
                                            <form id="bulkUpdateForm" method="POST" action="admin_payment_queries.php">
                                                <input type="hidden" name="action" value="bulk_update">
                                                <input type="hidden" name="bulk_status" value="in-progress">
                                                <button type="submit" class="dropdown-item bulk-update-btn" data-status="in-progress">Mark as In Progress</button>
                                            </form>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item bulk-update-btn" data-status="resolved">Mark as Resolved</button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item bulk-update-btn" data-status="closed">Mark as Closed</button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 query-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="checkbox-column">
                                                    <div class="form-check">
                                                        <input class="form-check-input select-all-checkbox" type="checkbox" id="selectAllCheckbox">
                                                    </div>
                                                </th>
                                                <th>ID</th> <th>Student</th> <th>Category</th>
                                                <th>Status</th><th>Created</th> <th>Last Activity</th> <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($queries)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class="fas fa-search fa-2x mb-3 text-muted"></i>
                                                        <p class="mb-0 text-muted">No queries found matching your criteria</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($queries as $query): ?>
                                                    <tr class="query-<?php echo $query['status']; ?>">
                                                        <td>
                                                            <div class="form-check">
                                                                <input class="form-check-input query-checkbox" type="checkbox" name="query_ids[]" value="<?php echo $query['id']; ?>">
                                                            </div>
                                                        </td>
                                                        <td><?php echo $query['id']; ?></td>
                                                        <td><?php echo $query['regNo']; ?></td>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $query['category'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $query['status']; ?>">
                                                                <?php echo ucfirst($query['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php echo date('d M Y', strtotime($query['created_at'])); ?>
                                                            <small class="d-block text-muted"><?php echo date('H:i', strtotime($query['created_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $lastActivity = !empty($query['last_response']) ? $query['last_response'] : $query['created_at'];
                                                            echo date('d M Y', strtotime($lastActivity));
                                                            ?>
                                                            <small class="d-block text-muted">
                                                                <?php if ($query['response_count'] > 0): ?>
                                                                    <?php echo $query['response_count']; ?> responses
                                                                <?php else: ?>
                                                                    No responses
                                                                <?php endif; ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <a href="admin_payment_queries.php?view_query=<?php echo $query['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-center mt-4 mb-3">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            <?php 
                                            $totalQueries = $queryManager->getQueries($status, $category, $search, 1000000, 0);
                                            $totalPages = ceil(count($totalQueries) / $limit);
                                            if ($page > 1): 
                                                $prevPage = $page - 1;
                                                $prevUrl = "admin_payment_queries.php?page=$prevPage";
                                                if ($status) $prevUrl .= "&status=$status";
                                                if ($category) $prevUrl .= "&category=$category";
                                                if ($search) $prevUrl .= "&search=$search";
                                            ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo $prevUrl; ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php 
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $startPage + 4);
                                            for ($i = $startPage; $i <= $endPage; $i++): 
                                                $pageUrl = "admin_payment_queries.php?page=$i";
                                                if ($status) $pageUrl .= "&status=$status";
                                                if ($category) $pageUrl .= "&category=$category";
                                                if ($search) $pageUrl .= "&search=$search";
                                            ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <?php 
                                            if ($page < $totalPages): 
                                                $nextPage = $page + 1;
                                                $nextUrl = "admin_payment_queries.php?page=$nextPage";
                                                if ($status) $nextUrl .= "&status=$status";
                                                if ($category) $nextUrl .= "&category=$category";
                                                if ($search) $nextUrl .= "&search=$search";
                                            ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo $nextUrl; ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
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
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const queryCheckboxes = document.querySelectorAll('.query-checkbox');
        const bulkActionBtn = document.querySelector('.bulk-action-btn');
        const bulkUpdateBtns = document.querySelectorAll('.bulk-update-btn');
        const bulkUpdateForm = document.getElementById('bulkUpdateForm');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                queryCheckboxes.forEach(checkbox => {checkbox.checked = isChecked;});
                updateBulkActionButton();
            });
        }
        if (queryCheckboxes.length > 0) {queryCheckboxes.forEach(checkbox => {checkbox.addEventListener('change', updateBulkActionButton);});}
        function updateBulkActionButton() {
            const checkedCount = document.querySelectorAll('.query-checkbox:checked').length;
            if (checkedCount > 0) {
                bulkActionBtn.classList.remove('disabled');
                bulkActionBtn.textContent = `Bulk Actions (${checkedCount})`;
            } else {
                bulkActionBtn.classList.add('disabled');
                bulkActionBtn.textContent = 'Bulk Actions';
            }
        }
        if (bulkUpdateBtns.length > 0) {
            bulkUpdateBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const status = this.getAttribute('data-status');
                    const checkedCheckboxes = document.querySelectorAll('.query-checkbox:checked');
                    if (checkedCheckboxes.length === 0) {
                        alert('Please select at least one query');
                        return;
                    }
                    const existingInputs = bulkUpdateForm.querySelectorAll('input[name="query_ids[]"]');
                    existingInputs.forEach(input => input.remove());
                    checkedCheckboxes.forEach(checkbox => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'query_ids[]';
                        input.value = checkbox.value;
                        bulkUpdateForm.appendChild(input);
                    });
                    const statusInput = bulkUpdateForm.querySelector('input[name="bulk_status"]');
                    statusInput.value = status;
                    bulkUpdateForm.submit();
                });
            });
        }
    </script>
</body>
</html>