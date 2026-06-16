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

$ENTITY_TYPES = ['Employee', 'Department', 'Location', 'Organisation', 'Category', 'Designation'];
$FIELD_TYPES  = ['Text', 'Number', 'Date', 'Yes/No'];

try {

    switch ($action) {

        /* ──────────────────────────────────────────────
         * LIST
         * ────────────────────────────────────────────── */
        case 'list':

            $sql = "SELECT
                        id,
                        entity_type,
                        field_name,
                        field_type,
                        regular_expression,
                        created_at
                    FROM additional_fields
                    WHERE is_deleted = 0
                    ORDER BY created_at DESC";

            $result = mysqli_query($conn, $sql);

            if (!$result) {
                throw new Exception(mysqli_error($conn));
            }

            $data = [];

            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;

        /* ──────────────────────────────────────────────
         * ADD
         * ────────────────────────────────────────────── */
        case 'add':

            $entityType = trim($_POST['entity_type'] ?? '');
            $fieldName  = trim($_POST['field_name'] ?? '');
            $fieldType  = trim($_POST['field_type'] ?? 'Text');
            $regex      = trim($_POST['regular_expression'] ?? '');

            if (!$entityType || !$fieldName) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Entity Type and Field Name are required.'
                ]);
                break;
            }

            if (!in_array($fieldType, $FIELD_TYPES)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid field type.'
                ]);
                break;
            }

            $entityTypeEsc = mysqli_real_escape_string($conn, $entityType);
            $fieldNameEsc  = mysqli_real_escape_string($conn, $fieldName);

            $chkSql = "SELECT id
                       FROM additional_fields
                       WHERE entity_type='$entityTypeEsc'
                       AND field_name='$fieldNameEsc'
                       AND is_deleted=0";

            $chkResult = mysqli_query($conn, $chkSql);

            if (!$chkResult) {
                throw new Exception(mysqli_error($conn));
            }

            if (mysqli_num_rows($chkResult) > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "Field '{$fieldName}' already exists for {$entityType}."
                ]);
                break;
            }

            $fieldTypeEsc = mysqli_real_escape_string($conn, $fieldType);
            $regexEsc     = mysqli_real_escape_string($conn, $regex);
            $createdBy    = (int)($_SESSION['user_id'] ?? 0);

            $insertSql = "INSERT INTO additional_fields
                            (
                                entity_type,
                                field_name,
                                field_type,
                                regular_expression,
                                created_by
                            )
                          VALUES
                            (
                                '$entityTypeEsc',
                                '$fieldNameEsc',
                                '$fieldTypeEsc',
                                '$regexEsc',
                                $createdBy
                            )";

            if (!mysqli_query($conn, $insertSql)) {
                throw new Exception(mysqli_error($conn));
            }

            $id = mysqli_insert_id($conn);

            echo json_encode([
                'success' => true,
                'message' => 'Field added.',
                'id' => $id
            ]);
            break;

        /* ──────────────────────────────────────────────
         * UPDATE
         * ────────────────────────────────────────────── */
        case 'update':

            $id         = (int)($_POST['id'] ?? 0);
            $entityType = trim($_POST['entity_type'] ?? '');
            $fieldName  = trim($_POST['field_name'] ?? '');
            $fieldType  = trim($_POST['field_type'] ?? 'Text');
            $regex      = trim($_POST['regular_expression'] ?? '');

            if (!$id || !$entityType || !$fieldName) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Entity Type and Field Name are required.'
                ]);
                break;
            }

            $entityTypeEsc = mysqli_real_escape_string($conn, $entityType);
            $fieldNameEsc  = mysqli_real_escape_string($conn, $fieldName);

            $chkSql = "SELECT id
                       FROM additional_fields
                       WHERE entity_type='$entityTypeEsc'
                       AND field_name='$fieldNameEsc'
                       AND id!=$id
                       AND is_deleted=0";

            $chkResult = mysqli_query($conn, $chkSql);

            if (!$chkResult) {
                throw new Exception(mysqli_error($conn));
            }

            if (mysqli_num_rows($chkResult) > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "Field '{$fieldName}' already exists for {$entityType}."
                ]);
                break;
            }

            $fieldTypeEsc = mysqli_real_escape_string($conn, $fieldType);
            $regexEsc     = mysqli_real_escape_string($conn, $regex);

            $updateSql = "UPDATE additional_fields
                          SET
                              entity_type='$entityTypeEsc',
                              field_name='$fieldNameEsc',
                              field_type='$fieldTypeEsc',
                              regular_expression='$regexEsc',
                              updated_at=NOW()
                          WHERE id=$id
                          AND is_deleted=0";

            if (!mysqli_query($conn, $updateSql)) {
                throw new Exception(mysqli_error($conn));
            }

            echo json_encode([
                'success' => true,
                'message' => 'Field updated.'
            ]);
            break;

        /* ──────────────────────────────────────────────
         * DELETE
         * ────────────────────────────────────────────── */
        case 'delete':

            $id = (int)($_POST['id'] ?? 0);

            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid ID'
                ]);
                break;
            }

            $deleteSql = "UPDATE additional_fields
                          SET
                              is_deleted=1,
                              updated_at=NOW()
                          WHERE id=$id";

            if (!mysqli_query($conn, $deleteSql)) {
                throw new Exception(mysqli_error($conn));
            }

            echo json_encode([
                'success' => true,
                'message' => 'Field deleted.'
            ]);
            break;

        /* ──────────────────────────────────────────────
         * ENTITY TYPES
         * ────────────────────────────────────────────── */
        case 'entity_types':

            echo json_encode([
                'success' => true,
                'data' => $ENTITY_TYPES
            ]);
            break;

        /* ──────────────────────────────────────────────
         * DEFAULT
         * ────────────────────────────────────────────── */
        default:

            echo json_encode([
                'success' => false,
                'message' => 'Invalid action.'
            ]);
            break;
    }

} catch (Exception $e) {

    error_log('Additional Fields API: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ]);
}
?>
