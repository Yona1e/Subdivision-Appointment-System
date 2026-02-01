<?php
session_start();
require_once('../my-reservations/tcpdf/tcpdf.php'); // Path to TCPDF in my-reservations folder

// Check if user is logged in as Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$reservation_id = $_GET['id'] ?? null;

if (!$reservation_id) {
    die("Invalid reservation ID");
}

// Fetch reservation details with user information
// NOTE: Removed "AND r.user_id = :user_id" so Admin can view ANY reservation
$stmt = $conn->prepare("
    SELECT r.*, u.FirstName, u.LastName, u.Block, u.Lot, u.StreetName, u.Email
    FROM reservations r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.id = :id
");
$stmt->execute([':id' => $reservation_id]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    die("Reservation not found");
}

$cost = $reservation['cost'] ?? 0;

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Facility Reservation System');
$pdf->SetTitle('Reservation Invoice');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(20, 20, 20);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 28);

// Title
$pdf->Cell(0, 15, 'INVOICE', 0, 1, 'C');
$pdf->Ln(10);

// Reset font for content
$pdf->SetFont('helvetica', '', 10);

// Left side - Issued to
// Left side - Issued to
$pdf->Cell(100, 5, 'Issued to:', 0, 0, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Invoice No:', 0, 1, 'R');

$pdf->SetFont('helvetica', 'B', 10);
// Blank name
$pdf->Cell(100, 5, '', 0, 0, 'L');

$invoiceNo = 'INV-' . str_pad($reservation['id'], 6, '0', STR_PAD_LEFT);
$pdf->Cell(0, 5, $invoiceNo, 0, 1, 'R');

$pdf->SetFont('helvetica', '', 10);
// Blank address line 1
$pdf->Cell(100, 5, '', 0, 0, 'L');

$pdf->Cell(0, 5, 'Date Issued:', 0, 1, 'R');

$pdf->SetFont('helvetica', 'B', 10);
// Blank address line 2
$pdf->Cell(100, 5, '', 0, 0, 'L');

$dateIssued = date('M d, Y', strtotime($reservation['created_at']));
$pdf->Cell(0, 5, $dateIssued, 0, 1, 'R');

// Add Contact Number (Dynamic even for "blank" invoice invoice)
$pdf->SetFont('helvetica', 'B', 10);
$contactNo = !empty($reservation['phone']) ? htmlspecialchars($reservation['phone']) : 'N/A';
$pdf->Cell(100, 5, 'Contact No: ' . $contactNo, 0, 1, 'L');

$pdf->Ln(5);

// Table
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(0, 0, 0);
$pdf->SetTextColor(255, 255, 255);

// Table header
$pdf->Cell(70, 10, 'Facility', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Time Start', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Time End', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Total', 1, 1, 'C', true);

// Table rows
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 10);

// Only one row with data
$facilityName = htmlspecialchars($reservation['facility_name']);
$timeStart = date('g:i A', strtotime($reservation['time_start']));
$timeEnd = date('g:i A', strtotime($reservation['time_end']));
$totalCost = 'P' . number_format($cost, 2);

$pdf->Cell(70, 10, $facilityName, 1, 0, 'L');
$pdf->Cell(40, 10, $timeStart, 1, 0, 'C');
$pdf->Cell(40, 10, $timeEnd, 1, 0, 'C');
$pdf->Cell(30, 10, $totalCost, 1, 1, 'C');

$pdf->Ln(5);

// Payment info and total (STATIC VALUES)
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(100, 6, 'PAYMENT TO BE SUBMITTED TO', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 12);
$totalDisplay = 'TOTAL: P' . number_format($cost, 2);
$pdf->Cell(0, 6, $totalDisplay, 0, 1, 'R');

$pdf->SetFont('helvetica', '', 10);
// Static payment info
$pdf->Cell(100, 6, '09123456789', 0, 1, 'L');
$pdf->Cell(100, 6, 'Kurt Tan', 0, 1, 'L');

$pdf->Ln(8);

// Footer note
$pdf->SetFont('helvetica', '', 9);
$footerNote = 'Note that all payments are final, all cancelled reservations will be refunded via provided contact number during the reservation process.';
$pdf->MultiCell(0, 5, $footerNote, 0, 'C');

// Close and output PDF document
$filename = 'reservation_invoice_' . $reservation_id . '.pdf';
$pdf->Output($filename, 'D');
?>