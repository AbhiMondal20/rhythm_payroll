<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

require_once '../includes/db_client.php';
require_once '../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$commonCols = [
    'organisation_id',
    'signatory_id',
    'code',
    'name',
    'registration_number',
    'address1',
    'address2',
    'city',
    'state',
    'country',
    'pincode',
    'phone1',
    'phone2',
    'fax',
    'website',
    'location_id',
    'note'
];

try {

    switch ($action) {

        /* =====================================================
           LIST
        ===================================================== */
        case 'list':

            $type = mysqli_real_escape_string(
                $conn,
                trim($_GET['type'] ?? '')
            );

            if (empty($type)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Type required'
                ]);
                break;
            }

            $sql = "
                SELECT
                    tp.id,
                    tp.code,
                    tp.name,
                    tp.registration_number,
                    tp.pan,
                    o.client_name AS org_name
                FROM tax_profiles tp
                LEFT JOIN companies o
                    ON o.id = tp.organisation_id
                WHERE tp.tax_type = '$type'
                  AND tp.is_deleted = 0
                ORDER BY tp.created_at DESC
            ";

            $result = mysqli_query($conn, $sql);

            $data = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);

            break;


        /* =====================================================
           GET SINGLE
        ===================================================== */
        case 'get':

            $id = (int)($_GET['id'] ?? 0);

            $sql = "
                SELECT *
                FROM tax_profiles
                WHERE id = $id
                  AND is_deleted = 0
            ";

            $result = mysqli_query($conn, $sql);
            $row = mysqli_fetch_assoc($result);

            if (!$row) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Not found'
                ]);

                break;
            }

            if (!empty($row['signatory_id'])) {

                $signatoryId = (int)$row['signatory_id'];

                // FIXED: Use CONCAT_WS to safely handle NULL values in first/last names
                $empSql = "
                    SELECT
                        COALESCE(NULLIF(TRIM(employee_name), ''), CONCAT_WS(' ', first_name, last_name)) AS name,
                        employee_code
                    FROM employees
                    WHERE id = $signatoryId
                ";

                $empResult = mysqli_query($conn, $empSql);
                $emp = mysqli_fetch_assoc($empResult);

                $row['signatory_name'] = $emp
                    ? $emp['name'] . ' – #' . $emp['employee_code']
                    : '';
            }

            echo json_encode([
                'success' => true,
                'data' => $row
            ]);

            break;


        /* =====================================================
           ADD
        ===================================================== */
        case 'add':

            $type = trim($_POST['tax_type'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');

            if (!$type || !$code || !$name) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Tax type, Code and Name are required.'
                ]);

                break;
            }

            $typeEsc = mysqli_real_escape_string($conn, $type);
            $codeEsc = mysqli_real_escape_string($conn, $code);

            $chkSql = "
                SELECT id
                FROM tax_profiles
                WHERE tax_type='$typeEsc'
                  AND code='$codeEsc'
                  AND is_deleted=0
            ";

            $chkResult = mysqli_query($conn, $chkSql);

            if (mysqli_num_rows($chkResult) > 0) {

                echo json_encode([
                    'success' => false,
                    'message' => 'A profile with this Code already exists for '.$type
                ]);

                break;
            }

            $cols = [
                'tax_type',
                'created_by'
            ];

            $vals = [
                "'".$typeEsc."'",
                (int)($_SESSION['user_id'] ?? 0)
            ];

            $fields = array_merge(
                $commonCols,
                $type === 'TDS'
                    ? [
                        'pan',
                        'tan',
                        'description_s_no',
                        'city_tds_address1',
                        'city_tds_address2',
                        'city_tds_city',
                        'city_tds_state',
                        'city_tds_country',
                        'city_tds_pincode',
                        'city_tds_phone1',
                        'city_tds_phone2',
                        'city_tds_fax',
                        'city_tds_website',
                        'it_ward',
                        'it_circle',
                        'it_range',
                        'tds_ward',
                        'tds_circle',
                        'tds_range'
                    ]
                    : []
            );

            foreach ($fields as $field) {

                $cols[] = $field;

                $value = mysqli_real_escape_string(
                    $conn,
                    trim($_POST[$field] ?? '')
                );

                $vals[] = "'$value'";
            }

            $sql = "
                INSERT INTO tax_profiles
                (" . implode(',', $cols) . ")
                VALUES
                (" . implode(',', $vals) . ")
            ";

            if (!mysqli_query($conn, $sql)) {

                throw new Exception(mysqli_error($conn));
            }

            echo json_encode([
                'success' => true,
                'message' => $type . ' profile created.',
                'id' => mysqli_insert_id($conn)
            ]);

            break;


        /* =====================================================
           UPDATE
        ===================================================== */
        case 'update':

            $id = (int)($_POST['id'] ?? 0);
            $type = trim($_POST['tax_type'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');

            if (!$id || !$type || !$code || !$name) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Code and Name are required.'
                ]);

                break;
            }

            $typeEsc = mysqli_real_escape_string($conn, $type);
            $codeEsc = mysqli_real_escape_string($conn, $code);

            $chkSql = "
                SELECT id
                FROM tax_profiles
                WHERE tax_type='$typeEsc'
                  AND code='$codeEsc'
                  AND id != $id
                  AND is_deleted = 0
            ";

            $chkResult = mysqli_query($conn, $chkSql);

            if (mysqli_num_rows($chkResult) > 0) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Another profile already uses this Code.'
                ]);

                break;
            }

            $fields = array_merge(
                $commonCols,
                $type === 'TDS'
                    ? [
                        'pan',
                        'tan',
                        'description_s_no',
                        'city_tds_address1',
                        'city_tds_address2',
                        'city_tds_city',
                        'city_tds_state',
                        'city_tds_country',
                        'city_tds_pincode',
                        'city_tds_phone1',
                        'city_tds_phone2',
                        'city_tds_fax',
                        'city_tds_website',
                        'it_ward',
                        'it_circle',
                        'it_range',
                        'tds_ward',
                        'tds_circle',
                        'tds_range'
                    ]
                    : []
            );

            $updates = [];

            foreach ($fields as $field) {

                $value = mysqli_real_escape_string(
                    $conn,
                    trim($_POST[$field] ?? '')
                );

                $updates[] = "$field='$value'";
            }

            $updates[] = "updated_at=NOW()";

            $sql = "
                UPDATE tax_profiles
                SET " . implode(',', $updates) . "
                WHERE id = $id
            ";

            if (!mysqli_query($conn, $sql)) {

                throw new Exception(mysqli_error($conn));
            }

            echo json_encode([
                'success' => true,
                'message' => $type . ' profile updated.'
            ]);

            break;


        /* =====================================================
           DELETE
        ===================================================== */
        case 'delete':

            $id = (int)($_POST['id'] ?? 0);

            if (!$id) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid ID'
                ]);

                break;
            }

            $sql = "
                UPDATE tax_profiles
                SET
                    is_deleted = 1,
                    updated_at = NOW()
                WHERE id = $id
            ";

            mysqli_query($conn, $sql);

            echo json_encode([
                'success' => true,
                'message' => 'Profile deleted.'
            ]);

            break;


        /* =====================================================
           SEARCH SIGNATORY
        ===================================================== */
        case 'search_signatory':

            $q = mysqli_real_escape_string(
                $conn,
                trim($_GET['q'] ?? '')
            );

            // FIXED: Using CONCAT_WS allows first/last name to connect properly even if one is NULL
            // Made the status check more flexible for integer/string 'Active' types
            $sql = "
                SELECT
                    id,
                    COALESCE(NULLIF(TRIM(employee_name), ''), CONCAT_WS(' ', first_name, last_name)) AS name,
                    employee_code,
                    designation
                FROM employees
                WHERE status IN (1, '1', 'Active', 'active')
                  AND (
                        employee_name LIKE '%$q%'
                     OR first_name LIKE '%$q%'
                     OR last_name LIKE '%$q%'
                     OR employee_code LIKE '%$q%'
                  )
                ORDER BY name
                LIMIT 20
            ";

            $result = mysqli_query($conn, $sql);

            $data = [];

            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $data[] = $row;
                }
            }

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);

            break;


        default:

            echo json_encode([
                'success' => false,
                'message' => 'Invalid action.'
            ]);
    }

} catch (Exception $e) {

    error_log('Tax Profiles API: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ]);
}
?>