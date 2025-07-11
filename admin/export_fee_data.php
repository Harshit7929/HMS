<?php
ob_start();
include 'admin_db.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
$export_format = isset($_GET['export_format']) ? $_GET['export_format'] : 'excel';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$hostel_filter = isset($_GET['hostel']) ? $_GET['hostel'] : '';
$room_type = isset($_GET['room_type']) ? $_GET['room_type'] : '';
$ac_filter = isset($_GET['ac']) ? $_GET['ac'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$query = "SELECT rb.id as booking_id, ss.regNo, CONCAT(ss.firstName, ' ', ss.lastName) as student_name, 
          ss.email, rb.hostel_name, rb.room_number, rb.sharing_type, rb.is_ac, rb.booking_date,
          rb.total_fee, COALESCE(SUM(pd.amount), 0) as amount_paid, 
          (rb.total_fee - COALESCE(SUM(pd.amount), 0)) as amount_due, 
          rb.status as booking_status,
          CASE 
              WHEN rb.total_fee <= COALESCE(SUM(pd.amount), 0) THEN 'Paid'
              WHEN EXISTS (SELECT 1 FROM fee_dues fd WHERE fd.booking_id = rb.id AND fd.status = 'overdue') THEN 'Overdue'
              ELSE 'Pending'
          END as payment_status
          FROM room_bookings rb
          LEFT JOIN student_signup ss ON rb.user_email = ss.email
          LEFT JOIN payment_details pd ON rb.id = pd.booking_id AND pd.payment_status = 'completed'";
$where_conditions = [];
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(ss.regNo LIKE '%$search%' OR ss.firstName LIKE '%$search%' OR 
                          ss.lastName LIKE '%$search%' OR ss.email LIKE '%$search%')";
}
if ($filter == 'paid') {$where_conditions[] = "rb.total_fee <= COALESCE(SUM(pd.amount), 0)";} 
elseif ($filter == 'pending') {$where_conditions[] = "rb.total_fee > COALESCE(SUM(pd.amount), 0)";} 
elseif ($filter == 'overdue') {$where_conditions[] = "EXISTS (SELECT 1 FROM fee_dues fd WHERE fd.booking_id = rb.id AND fd.status = 'overdue')";}
if (!empty($hostel_filter)) {
    $hostel_filter = mysqli_real_escape_string($conn, $hostel_filter);
    $where_conditions[] = "rb.hostel_name = '$hostel_filter'";
}
if (!empty($room_type)) {
    $room_type = mysqli_real_escape_string($conn, $room_type);
    $where_conditions[] = "rb.sharing_type = '$room_type'";
}
if ($ac_filter !== '') {$where_conditions[] = "rb.is_ac = " . ($ac_filter == '1' ? '1' : '0');}
if (!empty($date_from)) {
    $date_from = mysqli_real_escape_string($conn, $date_from);
    $where_conditions[] = "rb.booking_date >= '$date_from'";
}
if (!empty($date_to)) {
    $date_to = mysqli_real_escape_string($conn, $date_to);
    $where_conditions[] = "rb.booking_date <= '$date_to'";}
if (!empty($where_conditions)) {$query .= " WHERE " . implode(" AND ", $where_conditions);}
$query .= " GROUP BY rb.id, ss.regNo, ss.firstName, ss.lastName, ss.email, rb.hostel_name, 
           rb.room_number, rb.sharing_type, rb.is_ac, rb.booking_date, rb.total_fee, rb.status";
$result = mysqli_query($conn, $query);
if (!$result) {die("Error in query: " . mysqli_error($conn));}
$headers = array(
    'ID' => 'booking_id',
    'Registration No' => 'regNo',
    'Student Name' => 'student_name',
    'Email' => 'email',
    'Hostel' => 'hostel_name',
    'Room' => 'room_number',
    'Type' => 'sharing_type',
    'AC' => 'is_ac',
    'Booking Date' => 'booking_date',
    'Total Fee' => 'total_fee',
    'Amount Paid' => 'amount_paid',
    'Amount Due' => 'amount_due',
    'Status' => 'booking_status',
    'Payment' => 'payment_status'
);
ob_clean();
if ($export_format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Hostel_Booking_Report_' . date('Y-m-d') . '.xls"');
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
        <title>Hostel Booking Report</title>
        <style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000; padding: 5px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .report-title { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class=\"report-title\">Hostel Booking Report</div>
        <table>
            <tr>";
    foreach ($headers as $display => $field) {
        echo "<th>$display</th>";
    }
    echo "</tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        foreach ($headers as $display => $field) {
            $value = $field == 'is_ac' ? ($row[$field] ? 'AC' : 'Non-AC') : $row[$field];
            echo "<td>$value</td>";
        }
        echo "</tr>";
    }
    echo "</table>
    </body>
    </html>";
    
} elseif ($export_format == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Hostel_Booking_Report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($headers));
    while ($row = mysqli_fetch_assoc($result)) {
        $data_row = array();
        foreach ($headers as $display => $field) {$data_row[] = $field == 'is_ac' ? ($row[$field] ? 'AC' : 'Non-AC') : $row[$field];}
        fputcsv($output, $data_row);
    }
    fclose($output);
} elseif ($export_format == 'pdf') {
    ob_end_clean();
    $fpdf_path = 'C:/xampp/htdocs/hostel_info/fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {die("FPDF library is missing. Please install FPDF library in 'fpdf' folder.");}
    require($fpdf_path);
    class HostelPDF extends FPDF {
        protected $headers = array();
        function SetTableHeaders($headers) {$this->headers = $headers;}
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 15, 'Hostel Booking Report', 0, 1, 'C');
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 8, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R');
            $this->Ln(2);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
        function TableHeader($widths) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetFillColor(41, 128, 185); 
            $this->SetTextColor(255, 255, 255); 
            $this->SetDrawColor(52, 73, 94); 
            $this->SetLineWidth(0.3);
            foreach ($this->headers as $header => $field) {$this->Cell($widths[$header], 10, $header, 1, 0, 'C', true);}
            $this->Ln();
        }
        function CreateTable($data, $widths) {
            $this->SetDrawColor(189, 195, 199);
            $this->SetLineWidth(0.2);
            $this->TableHeader($widths);
            $fill = false;
            foreach ($data as $row) {
                $maxHeight = 7;
                foreach ($this->headers as $header => $field) {
                    $value = ($field == 'is_ac') ? ($row[$field] ? 'AC' : 'Non-AC') : $row[$field];
                    $align = in_array($field, ['total_fee', 'amount_paid', 'amount_due']) ? 'R' : 'L';
                    $cellWidth = $widths[$header] - 2;  
                    $cellHeight = $this->GetStringWidth($value) > $cellWidth 
                                 ? ceil($this->GetStringWidth($value) / $cellWidth) * 5
                                 : 5;
                    $maxHeight = max($maxHeight, $cellHeight);
                }
                if ($this->GetY() + $maxHeight > $this->PageBreakTrigger) {
                    $this->AddPage('L');
                    $this->TableHeader($widths);
                }
                if ($fill) {
                    $this->SetFillColor(236, 240, 241); 
                    $this->SetTextColor(44, 62, 80); 
                } else {
                    $this->SetFillColor(255, 255, 255); 
                    $this->SetTextColor(44, 62, 80); 
                }
                $this->SetFont('Arial', '', 9);
                foreach ($this->headers as $header => $field) {
                    $value = ($field == 'is_ac') ? ($row[$field] ? 'AC' : 'Non-AC') : $row[$field];
                    $align = in_array($field, ['total_fee', 'amount_paid', 'amount_due']) ? 'R' : 'L';
                    if ($field == 'payment_status') {
                        if ($value == 'Paid') {
                            $this->SetTextColor(39, 174, 96);
                            $this->SetFont('Arial', 'B', 9);
                        } else if ($value == 'Overdue') {
                            $this->SetTextColor(192, 57, 43);
                            $this->SetFont('Arial', 'B', 9);
                        } else {$this->SetTextColor(211, 84, 0); }
                        $this->Cell($widths[$header], $maxHeight, $value, 1, 0, $align, $fill);
                        $this->SetTextColor(44, 62, 80);
                        $this->SetFont('Arial', '', 9); 
                    } else {$this->Cell($widths[$header], $maxHeight, $value, 1, 0, $align, $fill);}
                }
                $this->Ln();
                $fill = !$fill; 
            }
        }
    }
    $pdf = new HostelPDF('L', 'mm', 'A3'); 
    $pdf->SetTableHeaders($headers);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 9);
    $widths = array(
        'ID' => 15,
        'Registration No' => 35,
        'Student Name' => 45,
        'Email' => 65,
        'Hostel' => 30,
        'Room' => 20,
        'Type' => 25,
        'AC' => 20,
        'Booking Date' => 30,
        'Total Fee' => 25,
        'Amount Paid' => 25,
        'Amount Due' => 25,
        'Status' => 25,
        'Payment' => 25
    );
    $all_data = array();
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {$all_data[] = $row;}
    $pdf->CreateTable($all_data, $widths);
    $pdf->Output('D', 'Hostel_Booking_Report_' . date('Y-m-d') . '.pdf');
    exit();
} else {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="Hostel_Booking_Report_' . date('Y-m-d') . '.json"');
    $data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $row['is_ac'] = $row['is_ac'] ? 'AC' : 'Non-AC';
        $data[] = $row;
    }
    echo json_encode(array(
        'title' => 'Hostel Booking Report',
        'generated_date' => date('Y-m-d H:i:s'),
        'total_records' => count($data),
        'data' => $data
    ), JSON_PRETTY_PRINT);
}
mysqli_close($conn);
exit();
?>