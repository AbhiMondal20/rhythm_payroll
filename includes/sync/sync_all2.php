<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent PHP from killing the script if syncing many locations takes longer than 120 seconds
set_time_limit(0);
ini_set('default_socket_timeout', 5);

require '../../vendor/autoload.php';
require '../db_client.php';
$now = date('Y-m-d H:i:s');

use Rats\Zkteco\Lib\ZKTeco;

date_default_timezone_set('Asia/Kolkata');

// =======================================================================
// HELPER FUNCTION 1: OS-Level Ping to prevent the ZKTeco library from freezing
// =======================================================================
function isDevicePingable($ip) {
    $ip = escapeshellarg($ip);
    $os = strtoupper(substr(PHP_OS, 0, 3));
    
    if ($os === 'WIN') {
        exec("ping -n 1 -w 1000 $ip", $output, $status);
    } else {
        exec("ping -c 1 -W 1 $ip", $output, $status);
    }
    
    return $status === 0;
}

// =======================================================================
// HELPER FUNCTION 2: Determine Shift Based on Auto Shift Rules
// =======================================================================
function determineAutoShift($punch_time, $rules) {
    $time_only = date('H:i:s', strtotime($punch_time));
    
    foreach ($rules as $r) {
        $start = $r['in_start'];
        $end = $r['in_end'];
        
        if ($start <= $end) {
            if ($time_only >= $start && $time_only <= $end) {
                return $r['shift_name'];
            }
        } else { 
            if ($time_only >= $start || $time_only <= $end) {
                return $r['shift_name'];
            }
        }
    }
    return 'General';
}

// =======================================================================
// PRE-LOAD: Fetch all Auto Shift Rules
// =======================================================================
$shift_rules = [];
$rules_query = mysqli_query($conn, "SELECT shift_name, in_start, in_end FROM auto_shift_rules WHERE direction='IN' ORDER BY sort_order ASC");
if ($rules_query) {
    while ($rule = mysqli_fetch_assoc($rules_query)) {
        $shift_rules[] = $rule;
    }
}

$devices = mysqli_query($conn, "SELECT * FROM att_devices WHERE status='online'");

if (!$devices) {
    http_response_code(500);
    die("Database Error: " . mysqli_error($conn));
}

while ($device = mysqli_fetch_assoc($devices)) {

    echo "<h3>Device : {$device['device_name']}</h3>";

    try {
        $ip = $device['ip_address'];
        
        if (!isDevicePingable($ip)) {
            throw new Exception("Device at $ip is completely offline (Ping timeout). Skipped to prevent system freeze.");
        }

        $zk = new ZKTeco($ip, (int)$device['port_no']);
        
        if (!$zk->connect()) {
            throw new Exception("Ping succeeded, but could not connect to port {$device['port_no']} on $ip.");
        }

        mysqli_query($conn, "
            UPDATE att_devices
            SET last_ping = '$now'
            WHERE id='{$device['id']}'
        ");

        $users = [];
        $deviceUsers = $zk->getUser();
        $new_employees = 0;

        if (is_array($deviceUsers)) {
            foreach ($deviceUsers as $u) {
                $userid = trim((string)($u['userid'] ?? $u['id'] ?? ''));
                $name = '';

                if (isset($u['name'])) {
                    $name = is_array($u['name']) ? implode(' ', $u['name']) : (string)$u['name'];
                    $name = trim(preg_replace('/[[:cntrl:]]/', '', $name));
                }

                if ($userid != '') {
                    $users[$userid] = $name;

                    $emp_code_db = mysqli_real_escape_string($conn, $userid);
                    $emp_name_db = mysqli_real_escape_string($conn, $name);

                    $emp_check = mysqli_query($conn, "SELECT id FROM employees WHERE employee_code='$emp_code_db' LIMIT 1");

                    if (mysqli_num_rows($emp_check) == 0) {
                        $insert_emp = "
                            INSERT INTO employees (
                                employee_code, employee_name, ctc_template_id, status, created_at, updated_at
                            ) VALUES (
                                '$emp_code_db', '$emp_name_db', '1', '1', '$now', '$now'
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

        // Load ALL existing punches into RAM to prevent duplicate machine punches
        $existing_punches = [];
        $db_punches = mysqli_query($conn, "SELECT employee_code, punch_time FROM att_machine_punches WHERE device_id='{$device['id']}'");
        if ($db_punches) {
            while ($dbp = mysqli_fetch_assoc($db_punches)) {
                $existing_punches[$dbp['employee_code'] . '_' . $dbp['punch_time']] = true;
            }
        }

        $attendance = $zk->getAttendance();
        $inserted_punches = 0;
        $inserted_time_entries = 0;
        $updated_time_entries = 0;
        $duplicate_punches = 0;

        if (!is_array($attendance)) {
            $attendance = [];
        }

        foreach ($attendance as $row) {
            if (!is_array($row)) continue;

            $timestamp = $row['timestamp'] ?? '';
            if ($timestamp == '') continue;

            $employee_code = trim((string)($row['id'] ?? ''));
            if ($employee_code == '') continue;

            $punch_time = date('Y-m-d H:i:s', strtotime($timestamp));
            $punch_date = date('Y-m-d', strtotime($timestamp));
            $punch_key = $employee_code . '_' . $punch_time;
            $day_status_1 = "P"; // Default day status for check-in
            $day_status_2 = "P"; // Default day status for check-out

            $employee_name = trim(preg_replace('/[[:cntrl:]]/', '', is_array($users[$employee_code] ?? '') ? implode(' ', $users[$employee_code] ?? '') : ($users[$employee_code] ?? '')));
            $punch_type = $row['type'] ?? 0;
            $verify_type = $row['state'] ?? 0;

            $raw = mysqli_real_escape_string($conn, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $employee_code_db = mysqli_real_escape_string($conn, $employee_code);
            $employee_name_db = mysqli_real_escape_string($conn, $employee_name);

            $mapped_shift = determineAutoShift($punch_time, $shift_rules);
            $shift_name_db = mysqli_real_escape_string($conn, $mapped_shift);

            // ==========================================
            // 1. Handle att_machine_punches 
            // ==========================================
            if (!isset($existing_punches[$punch_key])) {
                $sql = "
                INSERT INTO att_machine_punches (
                    device_id, device_code, serial_number, employee_code,
                    employee_name, punch_time, punch_date, punch_type,
                    verify_type, raw_data, synced_at, created_at
                ) VALUES (
                    '{$device['id']}', '{$device['code']}', '{$device['serial_number']}', '$employee_code_db',
                    '$employee_name_db', '$punch_time', '$punch_date', '$punch_type',
                    '$verify_type', '$raw', '$now', '$now'
                )";

                if (!mysqli_query($conn, $sql)) {
                    echo "<div style='color:red'>Machine Punch Insert Error: ".mysqli_error($conn)."</div>";
                } else {
                    $inserted_punches++;
                    $existing_punches[$punch_key] = true; 
                }
            } else {
                $duplicate_punches++;
            }

            // ==========================================
            // 2. Handle time_entries (RUNS FOR ALL DATA)
            // ==========================================
            $emp_id_query = mysqli_query($conn, "SELECT id FROM employees WHERE employee_code='$employee_code_db' LIMIT 1");
            $emp_id_row = mysqli_fetch_assoc($emp_id_query);
            
            // Fix: Use NULL if employee ID isn't found to prevent Foreign Key constraints from failing
            $employee_id_db = $emp_id_row ? "'" . mysqli_real_escape_string($conn, $emp_id_row['id']) . "'" : "NULL";

            $te_check = mysqli_query($conn, "
                SELECT id, check_in_time, check_out_time 
                FROM time_entries 
                WHERE employee_code='$employee_code_db' 
                AND entry_date='$punch_date' 
                LIMIT 1
            ");

            if (mysqli_num_rows($te_check) == 0) {
                // INSERT NEW TIME ENTRY
                $te_insert = "
                    INSERT INTO time_entries (
                        employee_id, employee_code, employee_name, shift_name, entry_date, day_status_1,
                        check_in_time, record_status, created_at, updated_at
                    ) VALUES (
                        $employee_id_db, '$employee_code_db', '$employee_name_db', '$shift_name_db',
                        '$punch_date', '$day_status_1', '$punch_time', 'System', '$now', '$now'
                    )
                ";
                if (!mysqli_query($conn, $te_insert)) {
                    echo "<div style='color:red'><strong>Time Entry Insert Error for Emp {$employee_code_db}:</strong> " . mysqli_error($conn) . "</div>";
                } else {
                    $inserted_time_entries++;
                }
            } else {
                // UPDATE EXISTING TIME ENTRY (Checkout)
                $te_row = mysqli_fetch_assoc($te_check);
                $te_id = $te_row['id'];
                
                // Only update check_out_time if this punch is later than the check_in_time
                if ($te_row['check_in_time'] != $punch_time && $te_row['check_out_time'] != $punch_time && $punch_time > $te_row['check_in_time']) {
                    $te_update = "
                        UPDATE time_entries 
                        SET check_out_time = '$punch_time', 
                            hours_worked = TIMEDIFF('$punch_time', check_in_time),
                            updated_at = '$now' 
                        WHERE id = '$te_id'
                    ";
                    if (!mysqli_query($conn, $te_update)) {
                        echo "<div style='color:red'><strong>Time Entry Update Error for Emp {$employee_code_db}:</strong> " . mysqli_error($conn) . "</div>";
                    } else {
                        $updated_time_entries++;
                    }
                }
            }
        }

        mysqli_query($conn, "
            UPDATE att_devices
            SET last_download='$now'
            WHERE id='{$device['id']}'
        ");

        $zk->disconnect();

        echo "<div style='padding:10px;background:#d4edda;border:1px solid #28a745;margin:10px 0;border-radius:4px;font-size:14px;line-height:1.6;'>";
        echo "<b>New Employees Synced :</b> ".$new_employees."<br>";
        echo "<b>Total Machine Records Fetched :</b> ".count($attendance)."<br>";
        echo "<b>Existing Records Ignored (Machine Punches) :</b> ".$duplicate_punches."<br>";
        echo "<b style='color:#10B981'>Newly Synced Machine Punches :</b> ".$inserted_punches."<br>";
        echo "<b style='color:#2563EB'>Newly Created Time Entries :</b> ".$inserted_time_entries."<br>";
        echo "<b style='color:#2563EB'>Updated Time Entries (Checkouts) :</b> ".$updated_time_entries."<br>";
        echo "</div>";

    } catch (Throwable $e) {
        echo "<div style='color:#721c24;background:#f8d7da;padding:10px;border:1px solid #f5c6cb;margin:10px 0;border-radius:4px;'>";
        echo "<b>Connection/Sync Error:</b> ".$e->getMessage();
        echo "</div>";
    }
}
?>