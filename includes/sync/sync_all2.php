<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../vendor/autoload.php';
require '../db_client.php';

use Rats\Zkteco\Lib\ZKTeco;

date_default_timezone_set('Asia/Kolkata');

$devices = mysqli_query($conn, "SELECT * FROM att_devices WHERE status='online'");

if (!$devices) {
    die(mysqli_error($conn));
}

while ($device = mysqli_fetch_assoc($devices)) {

    echo "<h3>Device : {$device['device_name']}</h3>";

    try {

        $zk = new ZKTeco($device['ip_address'], (int)$device['port_no']);
        $zk->connect();

        mysqli_query($conn, "
            UPDATE att_devices
            SET last_ping = NOW()
            WHERE id='{$device['id']}'
        ");

        /*
        |--------------------------------------------------------------------------
        | Load Device Users & Sync to Employees Table
        |--------------------------------------------------------------------------
        */

        $users = [];
        $deviceUsers = $zk->getUser();
        
        $new_employees = 0;

        if (is_array($deviceUsers)) {

            foreach ($deviceUsers as $u) {

                $userid = '';

                if (isset($u['userid'])) {
                    $userid = trim((string)$u['userid']);
                } elseif (isset($u['id'])) {
                    $userid = trim((string)$u['id']);
                }

                $name = '';

                if (isset($u['name'])) {
                    if (is_array($u['name'])) {
                        $name = implode(' ', $u['name']);
                    } else {
                        $name = (string)$u['name'];
                    }
                    
                    // --- REMOVE INVISIBLE "BOX" CHARACTERS ---
                    // This removes all control characters (the boxes) and trims extra spaces
                    $name = preg_replace('/[[:cntrl:]]/', '', $name);
                    $name = trim($name);
                }

                if ($userid != '') {
                    $users[$userid] = $name;

                    $emp_code_db = mysqli_real_escape_string($conn, $userid);
                    $emp_name_db = mysqli_real_escape_string($conn, $name);

                    $emp_check = mysqli_query($conn, "
                        SELECT id 
                        FROM employees 
                        WHERE employee_code='$emp_code_db' 
                        LIMIT 1
                    ");

                    if (mysqli_num_rows($emp_check) == 0) {
                        
                        $insert_emp = "
                            INSERT INTO employees (
                                employee_code, 
                                employee_name, 
                                status, 
                                created_at, 
                                updated_at
                            ) VALUES (
                                '$emp_code_db', 
                                '$emp_name_db', 
                                '1', 
                                NOW(), 
                                NOW()
                            )
                        ";

                        if (!mysqli_query($conn, $insert_emp)) {
                            echo "<div style='color:red'>Employee Insert Error: ".mysqli_error($conn)."</div>";
                        } else {
                            $new_employees++;
                        }
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Attendance & Time Entries
        |--------------------------------------------------------------------------
        */

        $attendance = $zk->getAttendance();

        $inserted = 0;
        $duplicate = 0;

        if (!is_array($attendance)) {
            $attendance = [];
        }

        foreach ($attendance as $row) {

            if (!is_array($row)) {
                continue;
            }

            $employee_code = trim((string)($row['id'] ?? ''));

            if ($employee_code == '') {
                continue;
            }

            $employee_name = $users[$employee_code] ?? '';

            if (is_array($employee_name)) {
                $employee_name = implode(' ', $employee_name);
            }
            
            // Clean the name again just in case
            $employee_name = preg_replace('/[[:cntrl:]]/', '', $employee_name);
            $employee_name = trim($employee_name);

            $timestamp = $row['timestamp'] ?? '';

            if ($timestamp == '') {
                continue;
            }

            $punch_time = date('Y-m-d H:i:s', strtotime($timestamp));
            $punch_date = date('Y-m-d', strtotime($timestamp));

            $punch_type = $row['type'] ?? 0;
            $verify_type = $row['state'] ?? 0;

            $raw = mysqli_real_escape_string(
                $conn,
                json_encode(
                    $row,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );

            $employee_code_db = mysqli_real_escape_string($conn, $employee_code);
            $employee_name_db = mysqli_real_escape_string($conn, $employee_name);

            // Check if Raw Punch exists
            $check = mysqli_query($conn, "
                SELECT id
                FROM att_machine_punches
                WHERE device_id='{$device['id']}'
                AND employee_code='$employee_code_db'
                AND punch_time='$punch_time'
                LIMIT 1
            ");

            if (mysqli_num_rows($check) == 0) {

                // 1. Insert Raw Punch
                $sql = "
                INSERT INTO att_machine_punches
                (
                    device_id, device_code, serial_number, employee_code,
                    employee_name, punch_time, punch_date, punch_type,
                    verify_type, raw_data, synced_at, created_at
                )
                VALUES
                (
                    '{$device['id']}', '{$device['code']}', '{$device['serial_number']}', '$employee_code_db',
                    '$employee_name_db', '$punch_time', '$punch_date', '$punch_type',
                    '$verify_type', '$raw', NOW(), NOW()
                )";

                if (!mysqli_query($conn, $sql)) {
                    echo "<div style='color:red'>Attendance Insert Error: ".mysqli_error($conn)."</div>";
                } else {
                    $inserted++;
                    
                    // --- 2. Manage Actual Attendance (time_entries) ---
                    
                    // Fetch real employee_id from employees table
                    $emp_id_query = mysqli_query($conn, "SELECT id FROM employees WHERE employee_code='$employee_code_db' LIMIT 1");
                    $emp_id_row = mysqli_fetch_assoc($emp_id_query);
                    $employee_id_db = $emp_id_row ? mysqli_real_escape_string($conn, $emp_id_row['id']) : '0';

                    // Check if entry exists for this specific day
                    $te_check = mysqli_query($conn, "
                        SELECT id, check_in_time 
                        FROM time_entries 
                        WHERE employee_code='$employee_code_db' 
                        AND entry_date='$punch_date' 
                        LIMIT 1
                    ");

                    if (mysqli_num_rows($te_check) == 0) {
                        // NO RECORD FOUND: This is the Check-In
                        $te_insert = "
                            INSERT INTO time_entries (
                                employee_id, 
                                employee_code, 
                                employee_name, 
                                entry_date, 
                                check_in_time, 
                                record_status, 
                                created_at, 
                                updated_at
                            ) VALUES (
                                '$employee_id_db', 
                                '$employee_code_db', 
                                '$employee_name_db', 
                                '$punch_date', 
                                '$punch_time', 
                                'Present', 
                                NOW(), 
                                NOW()
                            )
                        ";
                        mysqli_query($conn, $te_insert);
                    } else {
                        // RECORD FOUND: This is a Check-Out
                        $te_row = mysqli_fetch_assoc($te_check);
                        $te_id = $te_row['id'];
                        
                        // Prevent updating if the check-in time is exactly the same as punch time
                        if ($te_row['check_in_time'] != $punch_time) {
                            
                            // Update Check-Out Time and dynamically calculate Hours Worked
                            $te_update = "
                                UPDATE time_entries 
                                SET check_out_time = '$punch_time', 
                                    hours_worked = TIMEDIFF('$punch_time', check_in_time),
                                    updated_at = NOW() 
                                WHERE id = '$te_id'
                            ";
                            mysqli_query($conn, $te_update);
                        }
                    }
                    // ---------------------------------------------------
                }

            } else {
                $duplicate++;
            }
        }

        mysqli_query($conn, "
            UPDATE att_devices
            SET last_download=NOW()
            WHERE id='{$device['id']}'
        ");

        $zk->disconnect();

        // Print final statistics
        echo "<div style='padding:10px;background:#d4edda;border:1px solid #28a745;margin:10px 0;border-radius:4px;'>";
        echo "<b>New Employees Synced :</b> ".$new_employees."<br>";
        echo "<b>Total Machine Records Fetched :</b> ".count($attendance)."<br>";
        echo "<b>Punches Inserted & Calculated :</b> ".$inserted."<br>";
        echo "<b>Punches Ignored (Duplicates) :</b> ".$duplicate."<br>";
        echo "</div>";

    } catch (Throwable $e) {

        echo "<div style='color:#721c24;background:#f8d7da;padding:10px;border:1px solid #f5c6cb;margin:10px 0;border-radius:4px;'>";
        echo "<b>Connection/Sync Error:</b> ".$e->getMessage();
        echo "</div>";

    }
}
?>