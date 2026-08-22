<?php
// ============================================================
//  UCC — Event Registration Backend
//  File: submit_registration.php
//  Place this file in the SAME folder as register.html
// ============================================================
define('DEBUG', true);
// ── 1. DATABASE CONFIG — edit these 4 lines ─────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'uaecarclubs_uccarclub_db');   // e.g. uccarclub_db
define('DB_USER', 'root');     // e.g. uccarclub_user
define('DB_PASS', '');
// ────────────────────────────────────────────────────────────

// ── 2. IMAGE UPLOAD CONFIG ───────────────────────────────────
define('UPLOAD_DIR', 'uploads/cars/');     // folder to store images
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
// ────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── 3. COLLECT & SANITIZE INPUTS ────────────────────────────
function clean($val) {
    return htmlspecialchars(strip_tags(trim($val)));
}

$full_name  = clean($_POST['full_name']  ?? '');
$phone      = clean($_POST['phone']      ?? '');
$email      = clean($_POST['email']      ?? '');
$club_name  = clean($_POST['club_name']  ?? '');
$car_type   = clean($_POST['car_type']   ?? '');
$car_model  = clean($_POST['car_model']  ?? '');
$plate      = clean($_POST['plate']      ?? '');
$car_color  = clean($_POST['car_color']  ?? '');

// ── 4. VALIDATE REQUIRED FIELDS ─────────────────────────────
$errors = [];
if (empty($full_name))  $errors[] = 'Full name is required.';
if (empty($phone))      $errors[] = 'Phone number is required.';
if (empty($email))      $errors[] = 'Email address is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
if (empty($car_type))   $errors[] = 'Car type is required.';
if (empty($car_model))  $errors[] = 'Car make & model is required.';
if (empty($plate))      $errors[] = 'Plate number is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── 5. HANDLE IMAGE UPLOAD ───────────────────────────────────
$car_image_path = null;

if (isset($_FILES['car_image']) && $_FILES['car_image']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['car_image'];
    $ftype    = mime_content_type($file['tmp_name']); // check real mime, not extension
    $fsize    = $file['size'];

    if (!in_array($ftype, $allowed_types)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid image type. Use JPG, PNG or WEBP.']);
        exit;
    }

    if ($fsize > MAX_FILE_SIZE) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Image exceeds 10 MB limit.']);
        exit;
    }

    // Create upload dir if it doesn't exist
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Safe unique filename
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
    $dest     = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save image. Check folder permissions.']);
        exit;
    }

    $car_image_path = $dest;
}

// ── 6. CONNECT TO DATABASE ───────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
    exit;
}

// ── 7. INSERT INTO DATABASE ──────────────────────────────────
try {
    $sql = "INSERT INTO registrations 
            (full_name, phone, email, club_name, car_type, car_model, plate_number, car_color, car_image, registered_at)
            VALUES 
            (:full_name, :phone, :email, :club_name, :car_type, :car_model, :plate_number, :car_color, :car_image, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':full_name'    => $full_name,
        ':phone'        => $phone,
        ':email'        => $email,
        ':club_name'    => $club_name,
        ':car_type'     => $car_type,
        ':car_model'    => $car_model,
        ':plate_number' => $plate,
        ':car_color'    => $car_color,
        ':car_image'    => $car_image_path,
    ]);

    $new_id = $pdo->lastInsertId();

    echo json_encode([
        'success'    => true,
        'message'    => 'Registration successful!',
        'reg_id'     => $new_id,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save registration. Please try again.']);
    exit;
}
?>