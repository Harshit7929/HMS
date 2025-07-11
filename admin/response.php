<?php
session_start();
include('admin_db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'], $_POST['response'])) {
    $complaint_id = $_POST['complaint_id'];
    $response = trim($_POST['response']);
    $responder_email = isset($_POST['admin_email']) ? $_POST['admin_email'] : 'admin@example.com';
    $performed_by = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';

    $insert_sql = "INSERT INTO complaint_responses (complaint_id, responder_email, response, action, performed_by) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $action = "Response Added";
    $stmt->bind_param("issss", $complaint_id, $responder_email, $response, $action, $performed_by);

    if ($stmt->execute()) {
        $update_sql = "UPDATE complaints SET status = 'in_progress' WHERE id = ? AND status = 'pending'";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $complaint_id);
        $update_stmt->execute();
        $update_stmt->close();
        header('Location: admin_complaints.php');
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['complaint_id'])) {
    $complaint_id = $_GET['complaint_id'];
    $complaint_sql = "SELECT c.*, 
                      CONCAT(s.firstName, ' ', s.lastName) AS student_name,
                      s.email AS student_email
                      FROM complaints c
                      LEFT JOIN student_signup s ON c.student_email = s.email
                      WHERE c.id = ?";
    $complaint_stmt = $conn->prepare($complaint_sql);
    $complaint_stmt->bind_param("i", $complaint_id);
    $complaint_stmt->execute();
    $complaint = $complaint_stmt->get_result()->fetch_assoc();
    $complaint_stmt->close();
    
    if (!$complaint) {
        echo "Complaint not found";
        exit();
    }
    
    $responses_sql = "SELECT cr.*, 
                     CASE 
                         WHEN cr.responder_email = ? THEN 'You'
                         WHEN cr.responder_email = s.email THEN CONCAT(s.firstName, ' ', s.lastName, ' (Student)')
                         WHEN cr.responder_email = st.email THEN CONCAT(st.name, ' (Staff)')
                         ELSE cr.responder_email
                     END AS responder_name
                     FROM complaint_responses cr
                     LEFT JOIN student_signup s ON cr.responder_email = s.email
                     LEFT JOIN staff st ON cr.responder_email = st.email
                     WHERE cr.complaint_id = ?
                     ORDER BY cr.created_at ASC";
    
    $admin_email = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : '';
    $responses_stmt = $conn->prepare($responses_sql);
    $responses_stmt->bind_param("si", $admin_email, $complaint_id);
    $responses_stmt->execute();
    $responses_result = $responses_stmt->get_result();
    $responses_stmt->close();
?>
    <div class="modal fade" id="responsesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conversation History - <?php echo htmlspecialchars($complaint['subject']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($complaint['student_name']); ?></strong> (Student)
                            </div>
                            <small><?php echo date("M d, Y h:i A", strtotime($complaint['created_at'])); ?></small>
                        </div>
                        <div class="card-body">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                        </div>
                    </div>
                    <?php if ($responses_result->num_rows > 0): ?>
                        <div class="responses-container">
                            <?php while ($response = $responses_result->fetch_assoc()): ?>
                                <div class="card mb-3 <?php echo ($response['responder_email'] === $admin_email) ? 'admin-response' : 'other-response'; ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($response['responder_name']); ?></strong> - <?php echo htmlspecialchars($response['action']); ?>
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
                </div>
                <div class="modal-footer">
                    <form method="post" action="response.php" class="w-100 response-form">
                        <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                        <input type="hidden" name="admin_email" value="<?php echo $admin_email; ?>">
                        <div class="form-group">
                            <textarea name="response" class="form-control" rows="3" placeholder="Type your response here..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Submit Response</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <style>
        .responses-container {
            max-height: 400px;
            overflow-y: auto;
        }
        .admin-response .card-header {
            background-color: #e3f2fd;
        }
        .other-response .card-header {
            background-color: #f8f9fa;
        }
    </style>
<?php
}
?>
