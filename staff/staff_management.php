<?php
session_start();
include('staff_db.php');
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['position']) || !isset($_SESSION['hostel'])) {
    header("Location: staff_test_login.php");
    exit();
}
$currentUserHostel = $_SESSION['hostel'];
$currentUserPosition = $_SESSION['position'];
$currentUserName = $_SESSION['name'];
$page_title = "Staff Management - $currentUserHostel Hostel";
function isAssignedToHostel($staffHostel, $hostelName) {
    if ($staffHostel === $hostelName) {return true;}
    $hostels = explode(',', $staffHostel);
    foreach ($hostels as $hostel) {if (trim($hostel) === $hostelName) {return true;}}
    return false;
}
$query = "SELECT * FROM staff WHERE 
          hostel = ? OR hostel LIKE ? OR 
          hostel LIKE ? OR hostel LIKE ? ORDER BY staff_id ASC";
$stmt = $conn->prepare($query);
$param1 = $currentUserHostel;
$param2 = "$currentUserHostel,%";
$param3 = "%, $currentUserHostel,%";
$param4 = "%, $currentUserHostel";
$stmt->bind_param("ssss", $param1, $param2, $param3, $param4);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #3a6ea5; --secondary-color: #004e98; --accent-color: #ff9e1b; --light-bg: #f8f9fa; --dark-bg: #343a40; 
            --success-bg: #d4edda; --border-radius: 8px; --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); --transition: all 0.3s ease; } 
        body { background-color: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; min-height: 100vh; display: flex; } 
        .sidebar { width: 250px; background: var(--secondary-color); height: 100vh; position: fixed; left: 0; top: 0; padding-top: 20px; 
            z-index: 100; transition: var(--transition); box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); } 
        .sidebar-brand { padding: 15px 25px; margin-bottom: 30px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); } 
        .sidebar-brand h2 { color: white; font-size: 1.5rem; margin-bottom: 0; } 
        .sidebar-brand span { color: var(--accent-color); } 
        .sidebar-menu { padding: 0; list-style: none; } 
        .sidebar-menu li { margin-bottom: 5px; } 
        .sidebar-menu a { padding: 12px 25px; color: rgba(255, 255, 255, 0.85); display: block; text-decoration: none; transition: var(--transition); border-left: 4px solid transparent; } 
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255, 255, 255, 0.1); color: white; border-left: 4px solid var(--accent-color); } 
        .sidebar-menu i { margin-right: 15px; width: 20px; text-align: center; } 
        .main-content { flex: 1; margin-left: 250px; background-color: #f5f7fa; min-height: 100vh; transition: var(--transition); } 
        .header { background-color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); position: sticky; top: 0; z-index: 99; } 
        .header h1 { color: var(--secondary-color); font-size: 1.6rem; margin-bottom: 0; } 
        .user-info { display: flex; align-items: center; gap: 15px; } 
        .user-avatar { width: 40px; height: 40px; background-color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; } 
        .user-details span { display: block; } 
        .user-name { font-weight: 600; color: var(--dark-bg); } 
        .user-position { font-size: 0.8rem; color: #6c757d; } 
        .page-content { padding: 25px; } 
        .content-header { margin-bottom: 25px; } 
        .content-card { background-color: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow); padding: 25px; margin-bottom: 25px; } 
        .staff-table { width: 100%; border-collapse: separate; border-spacing: 0; border-radius: var(--border-radius); overflow: hidden; box-shadow: var(--box-shadow); } 
        .staff-table th { background-color: var(--primary-color); color: white; font-weight: 500; text-transform: uppercase; font-size: 0.85rem; padding: 15px; text-align: left; } 
        .staff-table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; } 
        .staff-table tbody tr:last-child td { border-bottom: none; } 
        .staff-table tbody tr:hover { background-color: rgba(58, 110, 165, 0.05); } 
        .position-warden { background-color: rgba(58, 110, 165, 0.1); } 
        .position-security { background-color: rgba(92, 184, 92, 0.1); } 
        .position-maintenance { background-color: rgba(240, 173, 78, 0.1); } 
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); } 
        .btn-primary:hover { background-color: var(--secondary-color); border-color: var(--secondary-color); } 
        .btn-outline-primary { color: var(--primary-color); border-color: var(--primary-color); } 
        .btn-outline-primary:hover { background-color: var(--primary-color); color: white; } 
        .custom-alert { border-radius: var(--border-radius); padding: 12px 20px; margin-bottom: 20px; border-left: 4px solid transparent; } 
        .alert-info { background-color: #e3f2fd; border-left-color: #2196f3; color: #0c5460; } 
        .modal-content { border-radius: var(--border-radius); box-shadow: var(--box-shadow); } 
        .modal-header { background-color: var(--primary-color); color: white; border-radius: calc(var(--border-radius) - 1px) calc(var(--border-radius) - 1px) 0 0; } 
        .modal-title { font-weight: 600; } 
        .modal-body { padding: 20px; } 
        .badge { font-weight: 500; padding: 6px 12px; border-radius: 30px; } 
        @media (max-width: 992px) { .sidebar { width: 80px; padding-top: 15px; } .sidebar-brand { padding: 10px; text-align: center; } 
        .sidebar-brand h2 { display: none; } .sidebar-brand span { font-size: 1.5rem; display: block; } .sidebar-menu a { padding: 15px; t
            ext-align: center; } .sidebar-menu a span { display: none; } .sidebar-menu i { margin-right: 0; font-size: 1.2rem; } .main-content { margin-left: 80px; } } 
        @media (max-width: 768px) { .header { flex-direction: column; align-items: flex-start; gap: 15px; } .user-info { align-self: flex-end; } } 
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; } 
        .status-active { background-color: #d4edda; color: #155724; } 
        .status-on-leave { background-color: #fff3cd; color: #856404; } 
        .department-heading { color: var(--secondary-color); border-left: 4px solid var(--accent-color); padding-left: 12px; margin: 25px 0 15px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand"><h2><span><i class="fas fa-building"></i></span> Hostel MS</h2></div>
        <ul class="sidebar-menu">
            <li>
                <a href="warden_test_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="staff_management.php" class="active">
                    <i class="fas fa-users"></i>
                    <span>Staff Management</span>
                </a>
            </li>
            <li>
                <a href="service_requests.php">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Service Requests</span>
                </a>
            </li>
            <li>
                <a href="room_allocation.php">
                    <i class="fas fa-bed"></i>
                    <span>Room Allocation</span>
                </a>
            </li>
            <!-- <li>
                <a href="maintenance.php"> 
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li> -->
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="main-content">
        <div class="header">
            <h1><?php echo $page_title; ?></h1>
            <div class="user-info">
                <div class="user-avatar"><?php echo substr($currentUserName, 0, 1); ?></div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($currentUserName); ?></span>
                    <span class="user-position"><?php echo htmlspecialchars($currentUserPosition); ?></span>
                </div>
            </div>
        </div>
        <div class="page-content">
            <div class="content-header">
                <div class="custom-alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Showing staff members assigned to <?php echo htmlspecialchars($currentUserHostel); ?> Hostel
                </div>
            </div>
            <div class="content-card">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table staff-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-id-card me-2"></i>Staff ID</th>
                                    <th><i class="fas fa-user me-2"></i>Name</th>
                                    <th><i class="fas fa-briefcase me-2"></i>Position</th>
                                    <th><i class="fas fa-building me-2"></i>Department</th>
                                    <th><i class="fas fa-envelope me-2"></i>Email</th>
                                    <th><i class="fas fa-cogs me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $currentDepartment = '';
                                while ($row = $result->fetch_assoc()): 
                                    $rowClass = '';
                                    if ($row['position'] === 'Warden') {$rowClass = 'position-warden';} 
                                    elseif (strpos(strtolower($row['position']), 'security') !== false) {$rowClass = 'position-security';} 
                                    elseif (strpos(strtolower($row['position']), 'maintenance') !== false || strpos(strtolower($row['department']), 'maintenance') !== false) {$rowClass = 'position-maintenance';}
                                    if ($row['department'] != $currentDepartment) {
                                        $currentDepartment = $row['department'];
                                        echo '<tr><td colspan="6" class="department-heading">' . htmlspecialchars($currentDepartment) . ' Department</td></tr>';
                                    }
                                ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td><?php echo htmlspecialchars($row['staff_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['position']); ?>
                                            <span class="status-badge status-active ms-2">Active</span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#contactModal" 
                                                    data-staff-id="<?php echo htmlspecialchars($row['staff_id']); ?>"
                                                    data-staff-name="<?php echo htmlspecialchars($row['name']); ?>">
                                                <i class="fas fa-address-card me-1"></i> Contact
                                            </button>
                                            <?php if ($currentUserPosition === 'Warden'): ?>
                                            <button class="btn btn-sm btn-outline-secondary ms-1" type="button" data-bs-toggle="modal" data-bs-target="#assignTaskModal"
                                                    data-staff-id="<?php echo htmlspecialchars($row['staff_id']); ?>"
                                                    data-staff-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                    data-staff-position="<?php echo htmlspecialchars($row['position']); ?>">
                                                <i class="fas fa-tasks me-1"></i> Assign Task
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No staff members found for <?php echo htmlspecialchars($currentUserHostel); ?> Hostel.
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-3">
                <a href="warden_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Back to Dashboard</a>
                <?php if ($currentUserPosition === 'Warden'): ?>
                <a href="service_requests.php" class="btn btn-primary"><i class="fas fa-clipboard-list me-2"></i> View Service Requests</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel"><i class="fas fa-address-card me-2"></i> Contact Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="contactDetails">
                        <div class="mb-3 text-center">
                            <div style="width: 80px; height: 80px; background-color: var(--primary-color); color: white; font-size: 2rem; 
                            border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 fw-bold text-secondary">Name:</div>
                            <div class="col-8" id="modalStaffName"></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 fw-bold text-secondary">Staff ID:</div>
                            <div class="col-8" id="modalStaffId"></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 fw-bold text-secondary">Phone:</div>
                            <div class="col-8">+91 9876543210</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 fw-bold text-secondary">Email:</div>
                            <div class="col-8" id="modalStaffEmail">staff@example.com</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 fw-bold text-secondary">Available Hours:</div>
                            <div class="col-8">08:00 AM - 06:00 PM</div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-around mt-4">
                            <button class="btn btn-outline-primary">
                                <i class="fas fa-phone me-1"></i> Call
                            </button>
                            <button class="btn btn-outline-success">
                                <i class="fas fa-envelope me-1"></i> Email
                            </button>
                            <button class="btn btn-outline-info">
                                <i class="fas fa-comment me-1"></i> Message
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
    <?php if ($currentUserPosition === 'Warden'): ?>
    <div class="modal fade" id="assignTaskModal" tabindex="-1" aria-labelledby="assignTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignTaskModalLabel"><i class="fas fa-tasks me-2"></i> Assign Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="assignTaskForm" action="assign_task.php" method="POST">
                        <input type="hidden" id="taskStaffId" name="staff_id">
                        <div class="mb-3">
                            <label for="taskStaffName" class="form-label">Staff Member:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="taskStaffName" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="taskStaffPosition" class="form-label">Position:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                <input type="text" class="form-control" id="taskStaffPosition" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="taskType" class="form-label">Task Type:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clipboard-list"></i></span>
                                <select class="form-select" id="taskType" name="task_type" required>
                                    <option value="">Select Task Type</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="security">Security Check</option>
                                    <option value="cleaning">Room Cleaning</option>
                                    <option value="inspection">Room Inspection</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="taskDescription" class="form-label">Task Description:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-edit"></i></span>
                                <textarea class="form-control" id="taskDescription" name="task_description" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="taskPriority" class="form-label">Priority:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-flag"></i></span>
                                <select class="form-select" id="taskPriority" name="task_priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="taskDeadline" class="form-label">Deadline:</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                <input type="datetime-local" class="form-control" id="taskDeadline" name="task_deadline" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
                    <button type="submit" form="assignTaskForm" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Assign Task</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const contactModal = document.getElementById('contactModal');
            if (contactModal) {
                contactModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const staffId = button.getAttribute('data-staff-id');
                    const staffName = button.getAttribute('data-staff-name');
                    document.getElementById('modalStaffId').textContent = staffId;
                    document.getElementById('modalStaffName').textContent = staffName;
                    document.getElementById('modalStaffEmail').textContent = staffName.toLowerCase().replace(/\s/g, '.') + '@example.com';
                });
            }
            const assignTaskModal = document.getElementById('assignTaskModal');
            if (assignTaskModal) {
                assignTaskModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const staffId = button.getAttribute('data-staff-id');
                    const staffName = button.getAttribute('data-staff-name');
                    const staffPosition = button.getAttribute('data-staff-position');
                    document.getElementById('taskStaffId').value = staffId;
                    document.getElementById('taskStaffName').value = staffName;
                    document.getElementById('taskStaffPosition').value = staffPosition;
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    tomorrow.setHours(17, 0, 0, 0); 
                    const tomorrowStr = tomorrow.toISOString().slice(0, 16);
                    document.getElementById('taskDeadline').value = tomorrowStr;
                    const taskTypeSelect = document.getElementById('taskType');
                    taskTypeSelect.innerHTML = ''; 
                    const positionLower = staffPosition.toLowerCase();
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Select Task Type';
                    taskTypeSelect.appendChild(defaultOption);
                    const commonTasks = [{ value: 'other', text: 'Other' }];
                    if (positionLower.includes('security')) {
                        const securityTasks = [
                            { value: 'security_patrol', text: 'Security Patrol' },
                            { value: 'visitor_verification', text: 'Visitor Verification' },
                            { value: 'night_duty', text: 'Night Duty' },
                            { value: 'security_check', text: 'Security Check' }
                        ];
                        securityTasks.forEach(task => {
                            const option = document.createElement('option');
                            option.value = task.value;
                            option.textContent = task.text;
                            taskTypeSelect.appendChild(option);
                        });
                    } else if (positionLower.includes('maintenance')) {
                        const maintenanceTasks = [
                            { value: 'plumbing', text: 'Plumbing Repair' },
                            { value: 'plumbing', text: 'Plumbing Repair' },
                            { value: 'electrical', text: 'Electrical Repair' },
                            { value: 'furniture', text: 'Furniture Repair' },
                            { value: 'painting', text: 'Painting Work' },
                            { value: 'maintenance', text: 'General Maintenance' }
                        ];
                        maintenanceTasks.forEach(task => {
                            const option = document.createElement('option');
                            option.value = task.value;
                            option.textContent = task.text;
                            taskTypeSelect.appendChild(option);
                        });
                    } else if (positionLower.includes('warden')) {
                        const wardenTasks = [
                            { value: 'inspection', text: 'Room Inspection' },
                            { value: 'discipline', text: 'Discipline Matter' },
                            { value: 'meeting', text: 'Student Meeting' },
                            { value: 'supervision', text: 'Supervision' }
                        ];
                        wardenTasks.forEach(task => {
                            const option = document.createElement('option');
                            option.value = task.value;
                            option.textContent = task.text;
                            taskTypeSelect.appendChild(option);
                        });
                    } else if (positionLower.includes('clean') || positionLower.includes('janitor')) {
                        const cleaningTasks = [
                            { value: 'room_cleaning', text: 'Room Cleaning' },
                            { value: 'bathroom_cleaning', text: 'Bathroom Cleaning' },
                            { value: 'common_area', text: 'Common Area Cleaning' },
                            { value: 'waste_removal', text: 'Waste Removal' }
                        ];
                        cleaningTasks.forEach(task => {
                            const option = document.createElement('option');
                            option.value = task.value;
                            option.textContent = task.text;
                            taskTypeSelect.appendChild(option);
                        });
                    } else {
                        const genericTasks = [
                            { value: 'general', text: 'General Task' },
                            { value: 'admin', text: 'Administrative' },
                            { value: 'inspection', text: 'Inspection' },
                            { value: 'reporting', text: 'Reporting' }
                        ];
                        genericTasks.forEach(task => {
                            const option = document.createElement('option');
                            option.value = task.value;
                            option.textContent = task.text;
                            taskTypeSelect.appendChild(option);
                        });
                    }
                    commonTasks.forEach(task => {
                        const option = document.createElement('option');
                        option.value = task.value;
                        option.textContent = task.text;
                        taskTypeSelect.appendChild(option);
                    });
                });
            }
            const toggleSidebarBtn = document.createElement('button');
            toggleSidebarBtn.classList.add('btn', 'btn-primary', 'position-fixed');
            toggleSidebarBtn.style.left = '20px';
            toggleSidebarBtn.style.top = '20px';
            toggleSidebarBtn.style.zIndex = '200';
            toggleSidebarBtn.style.display = 'none';
            toggleSidebarBtn.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.appendChild(toggleSidebarBtn);
            let sidebarVisible = true;
            function toggleSidebar() {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.querySelector('.main-content');
                if (sidebarVisible) {
                    sidebar.style.left = '-250px';
                    mainContent.style.marginLeft = '0';
                } else {
                    sidebar.style.left = '0';
                    mainContent.style.marginLeft = '250px';
                }
                sidebarVisible = !sidebarVisible;
            }
            function handleResize() {
                if (window.innerWidth < 768) {
                    toggleSidebarBtn.style.display = 'block';
                    if (sidebarVisible) {toggleSidebar();}} 
                else {
                    toggleSidebarBtn.style.display = 'none';
                    if (!sidebarVisible) {toggleSidebar();}
                }
            }
            handleResize();
            window.addEventListener('resize', handleResize);
            toggleSidebarBtn.addEventListener('click', toggleSidebar);
            const staffTableRows = document.querySelectorAll('.staff-table tbody tr');
            staffTableRows.forEach(row => {
                if (!row.classList.contains('department-heading')) {
                    row.addEventListener('click', function(event) {
                        if (event.target.tagName === 'BUTTON' || 
                            event.target.closest('button') ||
                            event.target.tagName === 'I' && event.target.closest('button')) {
                            return;
                        }
                        const staffId = this.cells[0].textContent.trim();
                        const staffName = this.cells[1].textContent.trim();
                        const contactModal = new bootstrap.Modal(document.getElementById('contactModal'));
                        document.getElementById('modalStaffId').textContent = staffId;
                        document.getElementById('modalStaffName').textContent = staffName;
                        document.getElementById('modalStaffEmail').textContent = staffName.toLowerCase().replace(/\s/g, '.') + '@example.com';
                        contactModal.show();
                    });
                    row.style.cursor = 'pointer';
                }
            });
            const themeToggleBtn = document.createElement('button');
            themeToggleBtn.classList.add('btn', 'btn-sm', 'btn-outline-secondary', 'position-fixed');
            themeToggleBtn.style.right = '20px';
            themeToggleBtn.style.bottom = '20px';
            themeToggleBtn.style.zIndex = '200';
            themeToggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
            document.body.appendChild(themeToggleBtn);
            let darkMode = false;
            function toggleTheme() {
                const body = document.body;
                const sidebar = document.querySelector('.sidebar');
                const header = document.querySelector('.header');
                const contentCards = document.querySelectorAll('.content-card');
                const tables = document.querySelectorAll('.staff-table');
                if (darkMode) {
                    body.style.backgroundColor = '#f5f7fa';
                    body.style.color = '#343a40';
                    header.style.backgroundColor = 'white';
                    header.style.color = '#343a40';
                    contentCards.forEach(card => {
                        card.style.backgroundColor = 'white';
                        card.style.color = '#343a40';
                    });
                    tables.forEach(table => {
                        const tableHeaders = table.querySelectorAll('th');
                        tableHeaders.forEach(th => {
                            th.style.backgroundColor = 'var(--primary-color)';
                            th.style.color = 'white';
                        });
                    });
                    themeToggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
                } else {
                    body.style.backgroundColor = '#121212';
                    body.style.color = '#e0e0e0';
                    header.style.backgroundColor = '#1e1e1e';
                    header.style.color = '#e0e0e0';
                    contentCards.forEach(card => {
                        card.style.backgroundColor = '#1e1e1e';
                        card.style.color = '#e0e0e0';
                    });
                    tables.forEach(table => {
                        const tableHeaders = table.querySelectorAll('th');
                        tableHeaders.forEach(th => {
                            th.style.backgroundColor = '#333';
                            th.style.color = '#e0e0e0';
                        });
                    });
                    themeToggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
                }
                darkMode = !darkMode;
            }
            themeToggleBtn.addEventListener('click', toggleTheme);
            const quickActionsBtn = document.createElement('button');
            quickActionsBtn.classList.add('btn', 'btn-primary', 'rounded-circle', 'position-fixed');
            quickActionsBtn.style.right = '20px';
            quickActionsBtn.style.bottom = '80px';
            quickActionsBtn.style.width = '50px';
            quickActionsBtn.style.height = '50px';
            quickActionsBtn.style.zIndex = '200';
            quickActionsBtn.innerHTML = '<i class="fas fa-plus"></i>';
            document.body.appendChild(quickActionsBtn);
            const quickActionsMenu = document.createElement('div');
            quickActionsMenu.classList.add('position-fixed', 'bg-white', 'shadow', 'rounded', 'p-3');
            quickActionsMenu.style.right = '75px';
            quickActionsMenu.style.bottom = '80px';
            quickActionsMenu.style.zIndex = '199';
            quickActionsMenu.style.width = '200px';
            quickActionsMenu.style.display = 'none';
            quickActionsMenu.innerHTML = `
                <div class="fw-bold mb-2">Quick Actions</div>
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action p-2 d-flex align-items-center">
                        <i class="fas fa-user-plus me-2"></i> Add New Staff
                    </a>
                    <a href="#" class="list-group-item list-group-item-action p-2 d-flex align-items-center">
                        <i class="fas fa-file-export me-2"></i> Export Staff List
                    </a>
                    <a href="#" class="list-group-item list-group-item-action p-2 d-flex align-items-center">
                        <i class="fas fa-print me-2"></i> Print Directory
                    </a>
                    <a href="#" class="list-group-item list-group-item-action p-2 d-flex align-items-center">
                        <i class="fas fa-envelope me-2"></i> Email All Staff
                    </a>
                </div>
            `;
            document.body.appendChild(quickActionsMenu);
            let quickActionsVisible = false;
            quickActionsBtn.addEventListener('click', function() {
                if (quickActionsVisible) {
                    quickActionsMenu.style.display = 'none';
                    quickActionsBtn.innerHTML = '<i class="fas fa-plus"></i>';
                } else {
                    quickActionsMenu.style.display = 'block';
                    quickActionsBtn.innerHTML = '<i class="fas fa-times"></i>';
                }
                quickActionsVisible = !quickActionsVisible;
            });
            document.addEventListener('click', function(event) {
                if (quickActionsVisible && 
                    !quickActionsMenu.contains(event.target) && 
                    !quickActionsBtn.contains(event.target)) {
                    quickActionsMenu.style.display = 'none';
                    quickActionsBtn.innerHTML = '<i class="fas fa-plus"></i>';
                    quickActionsVisible = false;
                }
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>