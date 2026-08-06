<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../vendor/autoload.php';
require '../db_client.php';
$now = date('Y-m-d H:i:s');

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
            SET last_ping = $now
            WHERE id='{$device['id']}'
        ");

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
                                employee_code, employee_name, status, created_at, updated_at
                            ) VALUES (
                                '$emp_code_db', '$emp_name_db', '1',$now, $now
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

        // =======================================================================
        // SPEED OPTIMIZATION: Load all existing punches for this device into RAM
        // =======================================================================
        $existing_punches = [];
        $db_punches = mysqli_query($conn, "SELECT employee_code, punch_time FROM att_machine_punches WHERE device_id='{$device['id']}'");
        if ($db_punches) {
            while ($dbp = mysqli_fetch_assoc($db_punches)) {
                // Create a unique key like: "105_2023-10-12 09:30:00"
                $existing_punches[$dbp['employee_code'] . '_' . $dbp['punch_time']] = true;
            }
        }

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

            $timestamp = $row['timestamp'] ?? '';

            if ($timestamp == '') {
                continue;
            }

            $punch_time = date('Y-m-d H:i:s', strtotime($timestamp));
            $punch_date = date('Y-m-d', strtotime($timestamp));

            // =======================================================================
            // MAGIC FIX: Instantly check if this exact punch is already in our array
            // =======================================================================
            $punch_key = $employee_code . '_' . $punch_time;
            if (isset($existing_punches[$punch_key])) {
                $duplicate++;
                continue;
            }

            $employee_name = $users[$employee_code] ?? '';

            if (is_array($employee_name)) {
                $employee_name = implode(' ', $employee_name);
            }
            
            $employee_name = preg_replace('/[[:cntrl:]]/', '', $employee_name);
            $employee_name = trim($employee_name);

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

            // 1. Insert Raw Punch (No need to run SELECT check, we already validated it above!)
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
                '$verify_type', '$raw', $now, $now
            )";

            if (!mysqli_query($conn, $sql)) {
                echo "<div style='color:red'>Attendance Insert Error: ".mysqli_error($conn)."</div>";
            } else {
                $inserted++;
                
                // Add to array so we don't accidentally insert it twice if the machine sends duplicates
                $existing_punches[$punch_key] = true; 
                
                // --- 2. Manage Actual Attendance (time_entries) ---
                $emp_id_query = mysqli_query($conn, "SELECT id FROM employees WHERE employee_code='$employee_code_db' LIMIT 1");
                $emp_id_row = mysqli_fetch_assoc($emp_id_query);
                $employee_id_db = $emp_id_row ? mysqli_real_escape_string($conn, $emp_id_row['id']) : '0';

                $te_check = mysqli_query($conn, "
                    SELECT id, check_in_time 
                    FROM time_entries 
                    WHERE employee_code='$employee_code_db' 
                    AND entry_date='$punch_date' 
                    LIMIT 1
                ");

                if (mysqli_num_rows($te_check) == 0) {
                    $te_insert = "
                        INSERT INTO time_entries (
                            employee_id, employee_code, employee_name, entry_date, 
                            check_in_time, record_status, created_at, updated_at
                        ) VALUES (
                            '$employee_id_db', '$employee_code_db', '$employee_name_db', 
                            '$punch_date', '$punch_time', 'System', $now, $now
                        )
                    ";
                    mysqli_query($conn, $te_insert);
                } else {
                    $te_row = mysqli_fetch_assoc($te_check);
                    $te_id = $te_row['id'];
                    
                    if ($te_row['check_in_time'] != $punch_time) {
                        $te_update = "
                            UPDATE time_entries 
                            SET check_out_time = '$punch_time', 
                                hours_worked = TIMEDIFF('$punch_time', check_in_time),
                                updated_at = $now 
                            WHERE id = '$te_id'
                        ";
                        mysqli_query($conn, $te_update);
                    }
                }
            }
        }

        mysqli_query($conn, "
            UPDATE att_devices
            SET last_download=$now
            WHERE id='{$device['id']}'
        ");

        $zk->disconnect();

        // Print final statistics
        echo "<div style='padding:10px;background:#d4edda;border:1px solid #28a745;margin:10px 0;border-radius:4px;'>";
        echo "<b>New Employees Synced :</b> ".$new_employees."<br>";
        echo "<b>Total Machine Records Fetched :</b> ".count($attendance)."<br>";
        echo "<b>Newly Synced Punches (Today + Missing Old) :</b> ".$inserted."<br>";
        echo "<b>Existing Records Ignored :</b> ".$duplicate."<br>";
        echo "</div>";

    } catch (Throwable $e) {
        echo "<div style='color:#721c24;background:#f8d7da;padding:10px;border:1px solid #f5c6cb;margin:10px 0;border-radius:4px;'>";
        echo "<b>Connection/Sync Error:</b> ".$e->getMessage();
        echo "</div>";
    }
}
?>