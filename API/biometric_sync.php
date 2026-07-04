<?php
// Set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
// ==========================================
// DIRECT DATABASE CONNECTION
// ==========================================
$host = 'localhost';
$username = 'root'; 
$password = '';    
$dbname = 'ramkrishna_ivf_db'; // Make sure this matches your actual database name in phpMyAdmin
$port = 3307; // Default MySQL port

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

require_once '../includes/config.php';



// Allow only POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only POST method is allowed."
    ]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid JSON data."
    ]);
    exit;
}

// Validate required fields
if (empty($data['employee_code']) || empty($data['entry_date'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "employee_code and entry_date are required."
    ]);
    exit;
}

// Escape values
$employee_id   = mysqli_real_escape_string($conn, $data['employee_id'] ?? '');
$employee_code = mysqli_real_escape_string($conn, trim($data['employee_code']));
$employee_name = mysqli_real_escape_string($conn, $data['employee_name'] ?? '');

$day_status_1  = mysqli_real_escape_string($conn, $data['day_status_1'] ?? '');
$day_status_2  = mysqli_real_escape_string($conn, $data['day_status_2'] ?? '');
$shift_name    = mysqli_real_escape_string($conn, $data['shift_name'] ?? '');
$hours_worked  = mysqli_real_escape_string($conn, $data['hours_worked'] ?? '');
$record_status = mysqli_real_escape_string($conn, $data['record_status'] ?? 'System');

// Format entry date
$entry_date = date('Y-m-d', strtotime($data['entry_date']));

if ($entry_date == '1970-01-01') {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid entry_date."
    ]);
    exit;
}

// Format check-in time
$check_in_time = "";

if (!empty($data['check_in_time'])) {
    $check_in_time = date('H:i:s', strtotime($data['check_in_time']));
    $check_in_time = mysqli_real_escape_string($conn, $check_in_time);
}

// Format check-out time
$check_out_time = "";

if (!empty($data['check_out_time'])) {
    $check_out_time = date('H:i:s', strtotime($data['check_out_time']));
    $check_out_time = mysqli_real_escape_string($conn, $check_out_time);
}

// Convert empty values to NULL
$check_in_sql = ($check_in_time == "") ? "NULL" : "'$check_in_time'";
$check_out_sql = ($check_out_time == "") ? "NULL" : "'$check_out_time'";

// Check existing record
$checkQuery = "SELECT id
               FROM time_entries
               WHERE employee_code='$employee_code'
               AND entry_date='$entry_date'
               LIMIT 1";

$checkResult = mysqli_query($conn, $checkQuery);

if (!$checkResult) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => mysqli_error($conn)
    ]);
    exit;
}

if (mysqli_num_rows($checkResult) > 0) {

    // Existing record -> UPDATE
    $row = mysqli_fetch_assoc($checkResult);
    $record_id = $row['id'];

    $updateQuery = "
        UPDATE time_entries SET
            employee_name='$employee_name',
            day_status_1='$day_status_1',
            day_status_2='$day_status_2',
            shift_name='$shift_name',
            check_in_time=$check_in_sql,
            check_out_time=$check_out_sql,
            hours_worked='$hours_worked',
            record_status='$record_status',
            updated_at=NOW()
        WHERE id='$record_id'
    ";

    if (mysqli_query($conn, $updateQuery)) {

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Attendance record updated successfully."
        ]);

    } else {

        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => mysqli_error($conn)
        ]);

    }

} else {

    // New record -> INSERT
    $insertQuery = "
        INSERT INTO time_entries (
            employee_id,
            employee_code,
            employee_name,
            entry_date,
            day_status_1,
            day_status_2,
            shift_name,
            check_in_time,
            check_out_time,
            hours_worked,
            record_status,
            created_at,
            updated_at
        ) VALUES (
            '$employee_id',
            '$employee_code',
            '$employee_name',
            '$entry_date',
            '$day_status_1',
            '$day_status_2',
            '$shift_name',
            $check_in_sql,
            $check_out_sql,
            '$hours_worked',
            '$record_status',
            NOW(),
            NOW()
        )
    ";

    if (mysqli_query($conn, $insertQuery)) {

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Attendance record created successfully."
        ]);

    } else {

        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => mysqli_error($conn)
        ]);

    }

}

mysqli_close($conn);
?>