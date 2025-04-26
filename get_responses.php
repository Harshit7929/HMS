<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied. Please login first.";
    exit();
}

include('db.php');
$student_email = $_SESSION['user']['email'];

if (!isset($_GET['complaint_id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo "Complaint ID is required";
    exit();
}

$complaint_id = intval($_GET['complaint_id']);

// Verify this complaint belongs to the logged-in student
$verify_sql = "SELECT * FROM complaints WHERE id = ? AND student_email = ?";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param("is", $complaint_id, $student_email);
$verify_stmt->execute();
$complaint = $verify_stmt->get_result()->fetch_assoc();

if (!$complaint) {
    header("HTTP/1.1 403 Forbidden");
    echo "You don't have permission to view this complaint";
    exit();
}

// Fetch all responses for this complaint
$responses_sql = "SELECT cr.*, 
                 CASE 
                     WHEN cr.responder_email = ? THEN 'You'
                     WHEN cr.performed_by = 'staff' THEN CONCAT(st.name, ' (', st.department, ')')
                     WHEN cr.performed_by = 'admin' THEN CONCAT(cr.responder_email, ' (Admin)')
                     ELSE cr.responder_email
                 END AS responder_name
                 FROM complaint_responses cr
                 LEFT JOIN staff st ON cr.responder_email = st.email
                 WHERE cr.complaint_id = ?
                 ORDER BY cr.created_at ASC";

$responses_stmt = $conn->prepare($responses_sql);
$responses_stmt->bind_param("si", $student_email, $complaint_id);
$responses_stmt->execute();
$responses_result = $responses_stmt->get_result();
?>

<div class="modal-header">
    <h5 class="modal-title">Conversation History - <?php echo htmlspecialchars($complaint['subject']); ?></h5>
    <button type="button" class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
    <!-- Original complaint -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong>You</strong> (Student)
            </div>
            <small><?php echo date("M d, Y h:i A", strtotime($complaint['created_at'])); ?></small>
        </div>
        <div class="card-body">
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
        </div>
    </div>
    
    <!-- Responses -->
    <?php if ($responses_result->num_rows > 0): ?>
        <div class="responses-container">
            <?php while ($response = $responses_result->fetch_assoc()): ?>
                <div class="card mb-3 <?php echo ($response['responder_email'] === $student_email) ? 'student-response' : 'other-response'; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($response['responder_name']); ?></strong> 
                            <?php if (!empty($response['action'])): ?>
                                - <?php echo htmlspecialchars($response['action']); ?>
                            <?php endif; ?>
                        </div>
                        <small><?php echo date("M d, Y h:i A", strtotime($response['created_at'])); ?></small>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($response['response'])); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No responses yet.</div>
    <?php endif; ?>
    
    <!-- Reply form (only if complaint is not closed) -->
    <?php if ($complaint['status'] != 'closed'): ?>
        <form action="complaints.php" method="post" class="mt-4">
            <input type="hidden" name="action" value="add_response">
            <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
            <div class="form-group">
                <label for="response_message">Add Response:</label>
                <textarea class="form-control" name="response_message" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Response</button>
        </form>
    <?php else: ?>
        <div class="alert alert-warning mt-3">This complaint is closed. No further responses can be added.</div>
    <?php endif; ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
    <?php if ($complaint['status'] != 'closed'): ?>
        <form action="complaints.php" method="post" style="display: inline;">
            <input type="hidden" name="action" value="close_complaint">
            <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
            <button type="submit" class="btn btn-danger">Close Complaint</button>
        </form>
    <?php endif; ?>
</div>

<style>
    .responses-container {
        max-height: 400px;
        overflow-y: auto;
    }
    .student-response .card-header {
        background-color: #e3f2fd;
    }
    .other-response .card-header {
        background-color: #f8f9fa;
    }
</style>