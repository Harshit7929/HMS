<?php include('db.php'); 
session_start(); 
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$user = $_SESSION['user'];
$sql = "SELECT ss.*, sd.emergency_phone, sd.course, sd.year_of_study, sd.address,
         r.hostel_name, r.room_number, r.floor, r.is_ac, r.sharing_type
        FROM student_signup ss
        LEFT JOIN student_details sd ON ss.regNo = sd.reg_no
        LEFT JOIN room_bookings rb ON ss.email = rb.user_email AND rb.status = 'confirmed'
        LEFT JOIN rooms r ON rb.hostel_name = r.hostel_name AND rb.room_number = r.room_number
        WHERE ss.email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user['email']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
if (isset($_POST['submit_outpass'])) {
    $outDate = $_POST['out_date'];
    $outTime = $_POST['out_time'];
    $inDate = $_POST['in_date'];
    $inTime = $_POST['in_time'];
    $timezone = new DateTimeZone(date_default_timezone_get());
    $outDateTime = new DateTime($outDate . ' ' . $outTime, $timezone);
    $inDateTime = new DateTime($inDate . ' ' . $inTime, $timezone);
    $currentDateTime = new DateTime('now', $timezone);
    $form_error = false;
    $currentWithBuffer = clone $currentDateTime;
    $currentWithBuffer->modify('-5 minutes');
    if ($outDateTime < $currentWithBuffer) {
        $_SESSION['error_message'] = "Out date/time cannot be in the past.";
        $form_error = true;
    } elseif ($inDateTime <= $outDateTime) {
        $_SESSION['error_message'] = "In date/time must be after out date/time.";
        $form_error = true;
    } else {
        try {
            $checkDuplicate = "SELECT id, status FROM outpass 
                           WHERE student_reg_no = ? 
                           AND out_date = ? 
                           AND out_time = ? 
                           AND in_date = ? 
                           AND in_time = ? 
                           AND destination = ?";
            $checkStmt = $conn->prepare($checkDuplicate);
            $checkStmt->bind_param("ssssss", 
                $student['regNo'],
                $_POST['out_date'],
                $_POST['out_time'],
                $_POST['in_date'],
                $_POST['in_time'],
                $_POST['destination']
            );
            $checkStmt->execute();
            $duplicateResult = $checkStmt->get_result();
            if ($duplicateResult->num_rows > 0) {
                $duplicateOutpass = $duplicateResult->fetch_assoc();
                if ($duplicateOutpass['status'] == 'Rejected') {
                    $insertSql = "INSERT INTO outpass
                              (student_reg_no, outpass_type, out_time, in_time, out_date, in_date, reason, destination)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param(
                        "ssssssss", 
                        $student['regNo'],
                        $_POST['outpass_type'],
                        $_POST['out_time'],
                        $_POST['in_time'],
                        $_POST['out_date'],
                        $_POST['in_date'],
                        $_POST['reason'],
                        $_POST['destination']
                    );
                    if ($insertStmt->execute()) {$_SESSION['success_message'] = "Outpass request resubmitted successfully. Your previous similar request was rejected.";} 
                    else {
                        $_SESSION['error_message'] = "Failed to submit outpass request: " . $insertStmt->error;
                        $form_error = true;
                    }
                } else {
                    $_SESSION['error_message'] = "A similar outpass request already exists with status: " . $duplicateOutpass['status'];
                    $form_error = true;
                }
            } else {
                $insertSql = "INSERT INTO outpass
                          (student_reg_no, outpass_type, out_time, in_time, out_date, in_date, reason, destination)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param(
                    "ssssssss", 
                    $student['regNo'],
                    $_POST['outpass_type'],
                    $_POST['out_time'],
                    $_POST['in_time'],
                    $_POST['out_date'],
                    $_POST['in_date'],
                    $_POST['reason'],
                    $_POST['destination']
                );
                if ($insertStmt->execute()) {$_SESSION['success_message'] = "Outpass request submitted successfully.";} 
                else {
                    $_SESSION['error_message'] = "Failed to submit outpass request: " . $insertStmt->error;
                    $form_error = true;
                }
            }
        } catch (mysqli_sql_exception $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            $form_error = true;
        }
    }
    if (!$form_error) {header("Location: apply_outpass.php?success=1");} 
    else {header("Location: apply_outpass.php?error=1");}
    exit();
}
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
$historySql = "SELECT * FROM outpass WHERE student_reg_no = ? ORDER BY applied_at DESC";
$historyStmt = $conn->prepare($historySql);
$historyStmt->bind_param("s", $student['regNo']);
$historyStmt->execute();
$history_result = $historyStmt->get_result();
$outpass_history = array();
$total_records = $history_result->num_rows;
$counter = $total_records;
if ($history_result) {
    while ($outpass = $history_result->fetch_assoc()) {
        $outpass['display_id'] = $counter--;
        $outpass_history[] = $outpass;
    }
}
if (isset($_GET['logout'])) {
    $updateLogout = "UPDATE login_details SET logout_time = CURRENT_TIMESTAMP
                      WHERE student_email = ? AND logout_time IS NULL
                      ORDER BY login_time DESC LIMIT 1";
    $logoutStmt = $conn->prepare($updateLogout);
    $logoutStmt->bind_param("s", $user['email']);
    $logoutStmt->execute();
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outpass Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/apply_outpass.css"> -->
    <style>
        :root { --sidebar-width: 250px; --header-height: 60px; --primary-color: #3a3b45; --secondary-color: #2c2d36; --light-color: #f8f9fc; --dark-color: #5a5c69; 
            --success-color: #1cc88a; --info-color: #36b9cc; --warning-color: #f6c23e; --danger-color: #e74a3b; --accent-color: #4e73df; }
        body { font-family: "Nunito", sans-serif; background-color: #f8f9fc; margin: 0; padding: 0; overflow-x: hidden; }
        #sidebar { position: fixed; width: var(--sidebar-width); height: 100vh; background: linear-gradient( 180deg, var(--primary-color) 0%, var(--secondary-color) 100% ); 
            color: white; transition: all 0.3s; z-index: 1000; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
        #sidebar .sidebar-brand { height: var(--header-height); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; 
            font-weight: 800; padding: 1.5rem 1rem; text-transform: uppercase; letter-spacing: 0.05rem; z-index: 1; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        #sidebar .nav-item { position: relative; margin-bottom: 0.25rem; }
        #sidebar .nav-item .nav-link { display: block; color: rgba(255, 255, 255, 0.8); padding: 1rem; font-weight: 600; transition: all 0.3s; }
        #sidebar .nav-item .nav-link:hover, #sidebar .nav-item .nav-link.active { color: white; background-color: rgba(255, 255, 255, 0.1); border-radius: 0.35rem; }
        #sidebar .nav-item .nav-link i { margin-right: 0.5rem; font-size: 0.85rem; width: 1.5rem; }
        #sidebar .sidebar-divider { border-top: 1px solid rgba(255, 255, 255, 0.15); margin: 1rem 1rem; }
        #content { margin-left: var(--sidebar-width); min-height: 100vh; }
        #header { height: var(--header-height); background-color: white; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); 
            display: flex; align-items: center; justify-content: flex-end; padding: 0 1.5rem; z-index: 100; }
        #main-content { padding: 1.5rem; }
        .card { margin-bottom: 1.5rem; border: none; border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
        .card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; padding: 0.75rem 1.25rem; border-top-left-radius: 0.35rem; border-top-right-radius: 0.35rem; }
        .card-header h3 { font-size: 1.2rem; font-weight: 700; margin: 0; color: var(--accent-color); }
        .custom-card-header { padding: 1rem 1.25rem; margin-bottom: 0; border-bottom: 1px solid rgba(0, 0, 0, 0.125); }
        .student-info { background-color: #f8f9fc; padding: 1rem; border-radius: 0.35rem; margin-bottom: 1.5rem; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 2rem; }
        .info-item { display: flex; margin-bottom: 0.5rem; }
        .info-label { font-weight: 600; min-width: 140px; color: var(--dark-color); }
        .info-value { flex-grow: 1; }
        .status-pending { color: var(--warning-color); font-weight: 600; }
        .status-approved { color: var(--success-color); font-weight: 600; }
        .status-rejected { color: var(--danger-color); font-weight: 600; }
        .badge-ac { background-color: var(--info-color); color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .badge-non-ac { background-color: var(--secondary-color); color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .alert { border-radius: 0.35rem; border: none; margin-bottom: 1.5rem; }
        .modal-content { border: none; border-radius: 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .modal-header { border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; background-color: var(--accent-color); color: white; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem; background-color: #f8f9fc; }
        .btn-view { background-color: var(--accent-color); color: white; border-radius: 0.25rem; padding: 0.25rem 0.75rem; font-size: 0.85rem; transition: all 0.3s; }
        .btn-view:hover { background-color: #3a5cd0; color: white; }
        .btn-print { background-color: var(--success-color); color: white; border-radius: 0.25rem; padding: 0.25rem 0.75rem; font-size: 0.85rem; margin-left: 0.5rem; transition: all 0.3s; }
        .btn-print:hover { background-color: #19b37a; color: white; }
        @media (max-width: 768px) { :root { --sidebar-width: 100px; } #sidebar .sidebar-brand { font-size: 1rem; padding: 1.5rem 0.5rem; } 
        #sidebar .nav-item .nav-link span { display: none; } #sidebar .nav-item .nav-link i { margin-right: 0; font-size: 1rem; width: auto; 
            text-align: center; display: block; margin: 0 auto; } .info-grid { grid-template-columns: 1fr; } }
        @media (max-width: 576px) { :root { --sidebar-width: 0; } #sidebar { transform: translateX(-100%); } }
        @media print { body * { visibility: hidden; } .print-section, .print-section * { visibility: visible; } 
        .print-section { position: absolute; left: 0; top: 0; width: 100%; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="sidebar-brand"><i class="fas fa-school"></i> HostelEase</div>
        <div class="sidebar-divider"></div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="apply_outpass.php">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>Outpass</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="room_details.php">
                    <i class="fas fa-fw fa-bed"></i>
                    <span>Room Details</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-fw fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?logout=1">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    <div id="content">
        <div id="header">
            <div class="user-name">
                <strong><?php echo $student['firstName'] . ' ' . $student['lastName']; ?></strong> | <?php echo $student['regNo']; ?>
            </div>
        </div>
        <div id="main-content">
            <h1 class="h3 mb-4 text-gray-800">Outpass Management</h1>
            <?php if(isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if(isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="card mb-4">
                <div class="custom-card-header bg-primary text-white">
                    <h3><i class="fas fa-user-graduate me-2"></i>Student Details</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Name:</div>
                            <div class="info-value"><?php echo $student['firstName'] . ' ' . $student['lastName']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Registration No:</div>
                            <div class="info-value"><?php echo $student['regNo']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Birth:</div>
                            <div class="info-value"><?php echo isset($student['dob']) ? date('d-m-Y', strtotime($student['dob'])) : 'Not specified'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Gender:</div>
                            <div class="info-value"><?php echo isset($student['gender']) ? $student['gender'] : 'Not specified'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?php echo $student['email']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contact:</div>
                            <div class="info-value"><?php echo isset($student['contact']) ? $student['contact'] : 'Not specified'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Emergency Contact:</div>
                            <div class="info-value"><?php echo isset($student['emergency_phone']) ? $student['emergency_phone'] : 'Not specified'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Course:</div>
                            <div class="info-value"><?php echo isset($student['course']) ? $student['course'] : 'Not specified'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Year of Study:</div>
                            <div class="info-value"><?php echo isset($student['year_of_study']) ? $student['year_of_study'] : 'Not specified'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address:</div>
                            <div class="info-value"><?php echo isset($student['address']) ? $student['address'] : 'Not specified'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-4">
                <div class="custom-card-header bg-info text-white">
                    <h3><i class="fas fa-home me-2"></i>Hostel Details</h3>
                </div>
                <div class="card-body">
                    <?php if(isset($student['hostel_name']) && $student['hostel_name']): ?>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Hostel Name:</div>
                                <div class="info-value"><?php echo $student['hostel_name']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Room Number:</div>
                                <div class="info-value"><?php echo $student['room_number']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Floor:</div>
                                <div class="info-value"><?php echo $student['floor']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Room Type:</div>
                                <div class="info-value">
                                    <?php echo $student['sharing_type']; ?> 
                                    <span class="badge-<?php echo $student['is_ac'] ? 'ac' : 'non-ac'; ?>">
                                        <?php echo $student['is_ac'] ? 'AC' : 'Non-AC'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> No hostel information found. Please contact the administration.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="custom-card-header bg-success text-white">
                            <h3><i class="fas fa-file-alt me-2"></i>Request Outpass</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" action="apply_outpass.php" id="outpassForm">
                                <div class="mb-3">
                                    <label for="outpass_type" class="form-label">Outpass Type</label>
                                    <select class="form-select" id="outpass_type" name="outpass_type" required>
                                        <option value="General">General Outpass</option>
                                        <option value="Home">Home Outpass</option>
                                    </select>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="out_date" class="form-label">Out Date</label>
                                        <input type="date" class="form-control" id="out_date" name="out_date" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="out_time" class="form-label">Out Time</label>
                                        <input type="time" class="form-control" id="out_time" name="out_time" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="in_date" class="form-label">In Date</label>
                                        <input type="date" class="form-control" id="in_date" name="in_date" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="in_time" class="form-label">In Time</label>
                                        <input type="time" class="form-control" id="in_time" name="in_time" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="destination" class="form-label">Destination</label>
                                    <input type="text" class="form-control" id="destination" name="destination" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Reason</label>
                                    <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                                </div>
                                <button type="submit" name="submit_outpass" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Outpass Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="custom-card-header bg-primary text-white"><h3><i class="fas fa-history me-2"></i>Outpass History</h3></div>
                        <div class="card-body">
                            <?php if (!empty($outpass_history)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th> <th>Type</th> <th>Out Date</th>
                                                <th>In Date</th> <th>Status</th> <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($outpass_history as $outpass): ?>
                                                <tr>
                                                    <td>#<?php echo $outpass['display_id']; ?></td>
                                                    <td><?php echo $outpass['outpass_type']; ?></td>
                                                    <td><?php echo date('d-m-Y', strtotime($outpass['out_date'])); ?></td>
                                                    <td><?php echo date('d-m-Y', strtotime($outpass['in_date'])); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo strtolower($outpass['status']); ?>">
                                                            <?php echo $outpass['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-view" data-bs-toggle="modal" data-bs-target="#outpassModal<?php echo $outpass['id']; ?>">
                                                            View
                                                        </button>
                                                        <?php if (strtolower($outpass['status']) === 'approved'): ?>
                                                        <a href="print_outpass.php?id=<?php echo $outpass['id']; ?>" target="_blank" class="btn btn-print">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <div class="modal fade" id="outpassModal<?php echo $outpass['id']; ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo $outpass['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="modalLabel<?php echo $outpass['id']; ?>">Outpass Details #<?php echo $outpass['display_id']; ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body print-section" id="printArea<?php echo $outpass['id']; ?>">
                                                                <div class="text-center mb-4">
                                                                    <h4>SRM AP Hostel Outpass</h4>
                                                                    <p>Student Outpass Permit</p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <h5>Student Information</h5>
                                                                    <div class="row">
                                                                        <div class="col-6">
                                                                            <p><strong>Name:</strong> <?php echo $student['firstName'] . ' ' . $student['lastName']; ?></p>
                                                                            <p><strong>Reg No:</strong> <?php echo $student['regNo']; ?></p>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <p><strong>Contact:</strong> <?php echo $student['contact']; ?></p>
                                                                            <p><strong>Room:</strong> <?php echo $student['hostel_name'] . '-' . $student['room_number']; ?></p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <h5>Outpass Details</h5>
                                                                    <div class="row">
                                                                        <div class="col-6">
                                                                            <p><strong>Type:</strong> <?php echo $outpass['outpass_type']; ?></p>
                                                                            <p><strong>Out Date:</strong> <?php echo date('d-m-Y', strtotime($outpass['out_date'])); ?></p>
                                                                            <p><strong>Out Time:</strong> <?php echo $outpass['out_time']; ?></p>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <p><strong>Status:</strong> <span class="status-<?php echo strtolower($outpass['status']); ?>"><?php echo $outpass['status']; ?></span></p>
                                                                            <p><strong>In Date:</strong> <?php echo date('d-m-Y', strtotime($outpass['in_date'])); ?></p>
                                                                            <p><strong>In Time:</strong> <?php echo $outpass['in_time']; ?></p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <p><strong>Destination:</strong> <?php echo $outpass['destination']; ?></p>
                                                                    <p><strong>Reason:</strong> <?php echo $outpass['reason']; ?></p>
                                                                </div>
                                                                <?php if ($outpass['approved_by']): ?>
                                                                <div class="mb-3">
                                                                    <p><strong>Approved By:</strong> <?php echo $outpass['approved_by']; ?></p>
                                                                    <p><strong>Approval Date:</strong> <?php echo date('d-m-Y H:i', strtotime($outpass['approved_at'])); ?></p>
                                                                </div>
                                                                <?php endif; ?>
                                                                <?php if ($outpass['rejected_by']): ?>
                                                                <div class="mb-3">
                                                                    <p><strong>Rejected By:</strong> <?php echo $outpass['rejected_by']; ?></p>
                                                                    <p><strong>Rejection Date:</strong> <?php echo date('d-m-Y H:i', strtotime($outpass['rejected_at'])); ?></p>
                                                                    <?php if ($outpass['reject_reason']): ?>
                                                                    <p><strong>Rejection Reason:</strong> <?php echo $outpass['reject_reason']; ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php endif; ?>
                                                                <div class="mb-3">
                                                                    <p><strong>Applied At:</strong> <?php echo date('d-m-Y H:i', strtotime($outpass['applied_at'])); ?></p>
                                                                </div>
                                                                <?php if (strtolower($outpass['status']) === 'approved'): ?>
                                                                <div class="mt-4 text-center">
                                                                    <div class="row">
                                                                        <div class="col-6">
                                                                            <p>_____________________</p>
                                                                            <p>Student Signature</p>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <p>_____________________</p>
                                                                            <p>Warden Signature</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer no-print">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <?php if (strtolower($outpass['status']) === 'approved'): ?>
                                                                <a href="print_outpass.php?id=<?php echo $outpass['id']; ?>" target="_blank" class="btn btn-success">
                                                                    <i class="fas fa-print me-1"></i> Print
                                                                </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No outpass history found. Submit a new request to see it here.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const todayFormatted = today.toISOString().split('T')[0];
            document.getElementById('out_date').setAttribute('min', todayFormatted);
            document.getElementById('in_date').setAttribute('min', todayFormatted);
            document.getElementById('outpassForm').addEventListener('submit', function(event) {
                const outDate = new Date(document.getElementById('out_date').value + 'T' + document.getElementById('out_time').value);
                const inDate = new Date(document.getElementById('in_date').value + 'T' + document.getElementById('in_time').value);
                const now = new Date();
                const bufferTime = new Date(now.getTime() - (5 * 60 * 1000));
                if (outDate < bufferTime) {
                    alert('Out date/time cannot be in the past.');
                    event.preventDefault();
                    return false;
                }
                if (inDate <= outDate) {
                    alert('In date/time must be after out date/time.');
                    event.preventDefault();
                    return false;
                }
                return true;
            });
            const printButtons = document.querySelectorAll('.btn-print');
            printButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (e.target.getAttribute('href') && e.target.getAttribute('href').includes('print_outpass.php')) {return; }
                    e.preventDefault();
                    const modalId = this.closest('.modal').id;
                    const printContent = document.getElementById(modalId).querySelector('.print-section');
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write('<html><head><title>Outpass</title>');
                    printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
                    printWindow.document.write('<style>body { padding: 20px; } .status-approved { color: #1cc88a; font-weight: 600; } ' +
                                             '.status-pending { color: #f6c23e; font-weight: 600; } ' +
                                             '.status-rejected { color: #e74a3b; font-weight: 600; }</style>');
                    printWindow.document.write('</head><body>');
                    printWindow.document.write(printContent.innerHTML);
                    printWindow.document.write('</body></html>');
                    printWindow.document.close();
                    setTimeout(function() {
                        printWindow.print();
                    }, 500);
                });
            });
        });
    </script>
</body>
</html>