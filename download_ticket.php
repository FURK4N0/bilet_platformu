<?php
require_once 'config/init.php';

define('FPDF_FONTPATH', __DIR__ . '/lib/font/');
require_once 'lib/fpdf.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$ticket_id = $_GET['ticket_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$ticket_id) {
    die("Geçersiz bilet ID'si.");
}

// Bilet ve sefer bilgisi
$stmt = $pdo->prepare(
    "SELECT 
        T.id as ticket_id, T.pnr_code, T.status, T.total_price, T.created_at as purchase_time,
        TR.departure_city, TR.destination_city, TR.departure_time, TR.bus_type,
        U.full_name as buyer_name,
        C.name as company_name
     FROM Tickets T
     JOIN Trips TR ON T.trip_id = TR.id
     JOIN User U ON T.user_id = U.id
     JOIN Bus_Company C ON TR.company_id = C.id
     WHERE T.id = ? AND T.user_id = ?"
);
$stmt->execute([$ticket_id, $user_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Bilet bulunamadı veya bu bileti görüntüleme yetkiniz yok.");
}

// Bilete ait tüm koltukları çek
$stmt_seats = $pdo->prepare("
    SELECT seat_number, passenger_name, passenger_tc, gender 
    FROM Booked_Seats 
    WHERE ticket_id = ? 
    ORDER BY seat_number ASC
");
$stmt_seats->execute([$ticket_id]);
$seats = $stmt_seats->fetchAll(PDO::FETCH_ASSOC);

// Türkçe karakterleri FPDF formatına çevir
function format_text($text) {
    return iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $text ?? '');
}

class PDF extends FPDF {
    public $ticket_status; 

    function Watermark() {
        if ($this->ticket_status === 'CANCELLED') {
            $this->SetFont('DejaVu-Bold', '', 36);
            $this->SetTextColor(239, 68, 68); 
            $this->SetXY(0, 140); 
            $this->Cell(210, 10, format_text('IPTAL EDILMISTIR'), 0, 1, 'C');
        }
    }
}

$pdf = new PDF();
$pdf->ticket_status = $ticket['status'];

$pdf->AddFont('DejaVu', '', 'DejaVuSans.php');
$pdf->AddFont('DejaVu-Bold', '', 'DejaVuSans-Bold.php');

$pdf->AddPage();
$pdf->Watermark(); 
$pdf->SetTextColor(30, 41, 59); 

// Başlık ve PNR
$pdf->SetFont('DejaVu-Bold', '', 22);
$pdf->Cell(0, 10, format_text('ELEKTRONIK YOLCU BILETI'), 0, 1, 'C');
$pdf->SetFont('DejaVu-Bold', '', 14);
$pdf->SetTextColor(2, 132, 199);
$pdf->Cell(0, 8, format_text('PNR REZERVASYON NO: ' . ($ticket['pnr_code'] ?? 'TR-DEMO')), 0, 1, 'C');
$pdf->Ln(5); 

// Firma Adı
$pdf->SetTextColor(15, 23, 42);
$pdf->SetFont('DejaVu-Bold', '', 16);
$pdf->Cell(0, 10, format_text($ticket['company_name']), 0, 1, 'C');
$pdf->Ln(4);

// Çerçeveli Sefer Detayları
$pdf->SetFont('DejaVu-Bold', '', 11);
$pdf->Cell(45, 8, format_text('Guzergah:'));
$pdf->SetFont('DejaVu', '', 11);
$pdf->Cell(0, 8, format_text($ticket['departure_city'] . ' -> ' . $ticket['destination_city']));
$pdf->Ln();

$pdf->SetFont('DejaVu-Bold', '', 11);
$pdf->Cell(45, 8, format_text('Kalkis Zamani:'));
$pdf->SetFont('DejaVu', '', 11);
$pdf->Cell(0, 8, date('d.m.Y H:i', strtotime($ticket['departure_time'])));
$pdf->Ln();

$pdf->SetFont('DejaVu-Bold', '', 11);
$pdf->Cell(45, 8, format_text('Otobus Tipi:'));
$pdf->SetFont('DejaVu', '', 11);
$pdf->Cell(0, 8, format_text($ticket['bus_type'] ?? '2+1 VIP'));
$pdf->Ln(8);

// Yolcu ve Koltuk Listesi
$pdf->SetFont('DejaVu-Bold', '', 12);
$pdf->Cell(0, 8, format_text('KOLTUK VE YOLCU LISTESI:'), 0, 1, 'L');
$pdf->SetFont('DejaVu', '', 10);

foreach ($seats as $s) {
    $gen = ($s['gender'] ?? 'male') === 'female' ? 'Kadin' : 'Erkek';
    $tc_mask = strlen($s['passenger_tc'] ?? '') === 11 ? substr($s['passenger_tc'], 0, 3) . '*****' . substr($s['passenger_tc'], -2) : '100*****146';
    $line = " - Koltuk No: " . $s['seat_number'] . " | Yolcu: " . ($s['passenger_name'] ?? 'Yolcu') . " | TC: " . $tc_mask . " | Cinsiyet: " . $gen;
    $pdf->Cell(0, 7, format_text($line), 0, 1, 'L');
}

$pdf->Ln(10);
$pdf->SetFont('DejaVu-Bold', '', 12);
$pdf->SetTextColor(37, 99, 235);
$pdf->Cell(0, 8, format_text('Toplam Odenen Tutar: ' . number_format($ticket['total_price'], 2) . ' TL'), 0, 1, 'R');
$pdf->SetFont('DejaVu', '', 9);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(0, 5, format_text('Duzenlenme Tarihi: ' . date('d.m.Y H:i', strtotime($ticket['purchase_time']))), 0, 1, 'R');

$pnr_clean = $ticket['pnr_code'] ?? 'bilet';
$pdf->Output('D', 'bilet_' . $pnr_clean . '.pdf');