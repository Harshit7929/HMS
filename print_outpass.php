<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$outpassId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$sql = "SELECT o.*, 
               ss.firstName, ss.lastName, ss.regNo, ss.gender, ss.contact, ss.email, ss.dob,
               sd.emergency_phone, sd.course, sd.year_of_study, sd.address,
               r.hostel_name, r.room_number, r.floor, r.is_ac, r.sharing_type
        FROM outpass o
        JOIN student_signup ss ON o.student_reg_no = ss.regNo
        LEFT JOIN student_details sd ON ss.regNo = sd.reg_no
        LEFT JOIN room_bookings rb ON ss.email = rb.user_email AND rb.status = 'confirmed'
        LEFT JOIN rooms r ON rb.hostel_name = r.hostel_name AND rb.room_number = r.room_number
        WHERE o.id = ? AND o.status = 'Approved'
        AND ss.email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $outpassId, $_SESSION['user']['email']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo "Invalid outpass ID, not approved, or not authorized to view this outpass.";
    exit();
}
$outpass = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outpass #<?php echo $outpassId; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            font-size: 14px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .outpass-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #000;
            padding: 20px;
        }
        .college-name {
            font-size: 24px;
            font-weight: bold;
        }
        .hostel-name {
            font-size: 18px;
            margin-top: 5px;
        }
        .outpass-title {
            font-size: 18px;
            font-weight: bold;
            margin: 15px 0;
            text-align: center;
        }
        .outpass-number {
            font-weight: bold;
            color: #d9534f;
        }
        .student-details, .room-details, .outpass-details {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .signature-box {
            width: 30%;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        .status-approved {
            font-weight: bold;
            color: green;
        }
        .badge-ac {
            background-color: #28a745;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 12px;
        }
        .badge-non-ac {
            background-color: #17a2b8;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 12px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .outpass-container {
                border: none;
            }
            a[href]:after {
                content: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="outpass-container">
        <div class="header">
            <div class="college-name">UNIVERSITY HOSTEL MANAGEMENT</div>
            <div class="hostel-name"><?php echo $outpass['hostel_name'] ? $outpass['hostel_name'] . ' Hostel' : 'Student Hostel'; ?></div>
            <div>Student Outpass Permit</div>
        </div>
        <div class="outpass-title">
            <?php echo strtoupper($outpass['outpass_type']); ?> OUTPASS PERMIT - <span class="outpass-number">#<?php echo $outpassId; ?></span>
        </div>
        <div class="student-details">
            <table>
                <tr>
                    <th colspan="4">STUDENT DETAILS</th>
                </tr>
                <tr>
                    <td width="25%"><strong>Name:</strong></td>
                    <td width="25%"><?php echo $outpass['firstName'] . ' ' . $outpass['lastName']; ?></td>
                    <td width="25%"><strong>Registration No:</strong></td>
                    <td width="25%"><?php echo $outpass['regNo']; ?></td>
                </tr>
                <tr>
                    <td><strong>Course:</strong></td>
                    <td><?php echo $outpass['course'] ? $outpass['course'] : 'Not specified'; ?></td>
                    <td><strong>Year of Study:</strong></td>
                    <td><?php echo $outpass['year_of_study'] ? $outpass['year_of_study'] : 'Not specified'; ?></td>
                </tr>
                <tr>
                    <td><strong>Gender:</strong></td>
                    <td><?php echo $outpass['gender'] ? $outpass['gender'] : 'Not specified'; ?></td>
                    <td><strong>Date of Birth:</strong></td>
                    <td><?php echo $outpass['dob'] ? date('d-m-Y', strtotime($outpass['dob'])) : 'Not specified'; ?></td>
                </tr>
                <tr>
                    <td><strong>Contact:</strong></td>
                    <td><?php echo $outpass['contact'] ? $outpass['contact'] : 'Not specified'; ?></td>
                    <td><strong>Emergency Contact:</strong></td>
                    <td><?php echo $outpass['emergency_phone'] ? $outpass['emergency_phone'] : 'Not specified'; ?></td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td><?php echo $outpass['email']; ?></td>
                    <td><strong>Home Address:</strong></td>
                    <td><?php echo $outpass['address'] ? $outpass['address'] : 'Not specified'; ?></td>
                </tr>
            </table>
        </div>
        <div class="room-details">
            <table>
                <tr>
                    <th colspan="4">HOSTEL DETAILS</th>
                </tr>
                <?php if($outpass['hostel_name']): ?>
                <tr>
                    <td width="25%"><strong>Hostel Name:</strong></td>
                    <td width="25%"><?php echo $outpass['hostel_name']; ?></td>
                    <td width="25%"><strong>Room Number:</strong></td>
                    <td width="25%"><?php echo $outpass['room_number']; ?></td>
                </tr>
                <tr>
                    <td><strong>Floor:</strong></td>
                    <td><?php echo $outpass['floor']; ?></td>
                    <td><strong>Room Type:</strong></td>
                    <td>
                        <?php echo $outpass['sharing_type']; ?> 
                        <span class="badge-<?php echo $outpass['is_ac'] ? 'ac' : 'non-ac'; ?>">
                            <?php echo $outpass['is_ac'] ? 'AC' : 'Non-AC'; ?>
                        </span>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">No hostel information found. Please contact the administration.</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="outpass-details">
            <table>
                <tr>
                    <th colspan="4">OUTPASS DETAILS</th>
                </tr>
                <tr>
                    <td width="25%"><strong>Destination:</strong></td>
                    <td width="25%"><?php echo $outpass['destination']; ?></td>
                    <td width="25%"><strong>Outpass Type:</strong></td>
                    <td width="25%"><?php echo $outpass['outpass_type']; ?></td>
                </tr>
                <tr>
                    <td><strong>Out Date:</strong></td>
                    <td><?php echo date('d-m-Y', strtotime($outpass['out_date'])); ?></td>
                    <td><strong>Out Time:</strong></td>
                    <td><?php echo $outpass['out_time']; ?></td>
                </tr>
                <tr>
                    <td><strong>In Date:</strong></td>
                    <td><?php echo date('d-m-Y', strtotime($outpass['in_date'])); ?></td>
                    <td><strong>In Time:</strong></td>
                    <td><?php echo $outpass['in_time']; ?></td>
                </tr>
                <tr>
                    <td><strong>Reason:</strong></td>
                    <td colspan="3"><?php echo $outpass['reason']; ?></td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><span class="status-approved"><?php echo $outpass['status']; ?></span></td>
                    <td><strong>Applied On:</strong></td>
                    <td><?php echo date('d-m-Y H:i', strtotime($outpass['applied_at'])); ?></td>
                </tr>
                <?php if($outpass['approval_date']): ?>
                <tr>
                    <td><strong>Approval Date:</strong></td>
                    <td><?php echo date('d-m-Y H:i', strtotime($outpass['approval_date'])); ?></td>
                    <td><strong>Approved By:</strong></td>
                    <td><?php echo $outpass['approved_by'] ? $outpass['approved_by'] : 'N/A'; ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="signature-section">
            <div class="signature-box">
                Student Signature
            </div>
            <div class="signature-box">
                Warden Signature
            </div>
            <div class="signature-box">
                Security Verification
            </div>
        </div>
        <div class="mt-4 text-center">
            <p><small>This outpass is valid only with proper signatures and for the dates mentioned above.</small></p>
            <p><small>Student must carry this outpass along with ID card while exiting and entering the hostel.</small></p>
            <p><small>Outpass ID: #<?php echo $outpassId; ?> | Generated on: <?php echo date('d-m-Y H:i:s'); ?></small></p>
        </div>
        <div class="mt-4 text-center no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Outpass
            </button>
            <a href="apply_outpass.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Outpass Page
            </a>
        </div>
    </div>
    <script>
        // Auto-print when page loads (optional)
        /*
        window.onload = function() {
            window.print();
        }
        */
    </script>
</body>
</html>