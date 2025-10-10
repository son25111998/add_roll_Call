<?php
// File: includes/admin.function.inc.php
// Khai báo biến trước khi bind
/**
 * SỬA LỖI: Đổi tên hàm để tránh xung đột.
 * Lấy danh sách tất cả giáo viên từ CSDL cho trang Admin.
 */
function adminGetAllTeachers($conn)
{
    $sql = "
        SELECT 
            t.teacher_id,
            t.name,
            t.email,
            t.status,
            st.salary_method,
            sth.salary_value
        FROM teacher t
        LEFT JOIN (
            SELECT sth1.*
            FROM salary_type_history sth1
            INNER JOIN (
                SELECT teacher_id, MAX(effective_from) AS max_date
                FROM salary_type_history
                WHERE effective_from <= CURDATE()
                GROUP BY teacher_id
            ) latest ON sth1.teacher_id = latest.teacher_id AND sth1.effective_from = latest.max_date
        ) sth ON t.teacher_id = sth.teacher_id
        LEFT JOIN salary_type st ON sth.salary_type_id = st.salary_type_id
        ORDER BY t.name ASC;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($id, $name, $email, $status, $salary_method, $salary_value);

    $teachers = [];
    while ($stmt->fetch()) {
        $teachers[] = [
            'teacher_id'     => $id,
            'name'           => $name,
            'email'          => $email,
            'status'         => $status,
            'salary_method'  => $salary_method ?? 'Chưa thiết lập',
            'salary_value'   => $salary_value ?? 0
        ];
    }

    $stmt->close();
    return $teachers;
}


/**
 * Lấy danh sách tất cả học sinh từ CSDL.
 */
function getAllStudents($conn)
{
    // $id = null;
    // $name = null;
    // $email = null;
    // $phone = null;
    // $school = null;

    $sql = "SELECT student_id, name, email, phone_number, school_name FROM student ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($id, $name, $email, $phone, $school);
    $students = [];
    while ($stmt->fetch()) {
        $students[] = ['student_id' => $id, 'name' => $name, 'email' => $email, 'phone_number' => $phone, 'school_name' => $school];
    }
    $stmt->close();
    return $students;
}

function adminGetAllTeacherBonus($conn)
{
    // $id = null;
    // $name = null;
    // $email = null;
    // $phone = null;
    // $school = null;

    $sql = "SELECT tb.id,tb.teacher_id,t.name,tb.bonus_amount,tb.bonus_reason,tb.effective_from,tb.effective_to FROM teacher_bonus as tb INNER JOIN teacher as t WHERE tb.teacher_id = t.teacher_id order by effective_to ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($id, $teacher_id, $name, $bonus_amount, $bonus_reason, $effective_from, $effective_to);
    $teacher_bonus = [];
    while ($stmt->fetch()) {
        $teacher_bonus[] = [
            'id' => $id,
            'teacher_id' => $teacher_id,
            'name' => $name,
            'bonus_amount' => $bonus_amount,
            'bonus_reason' => $bonus_reason,
            'effective_from' => $effective_from,
            'effective_to' => $effective_to
        ];
    }
    $stmt->close();
    return $teacher_bonus;
}


/**
 * Lấy danh sách tất cả phụ huynh từ CSDL.
 */
function getAllParents($conn)
{
    // $id = null;
    // $name = null;
    // $status = null;
    // $phone = null;
    // $created_at = null;
    // $discount_type = null;
    // $discount_value = null;

    $sql = "SELECT parent_id, name, phone_number, status, created_at, discount_type, discount_value FROM parents ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($id, $name, $phone, $status, $created_at, $discount_type, $discount_value);
    $parents = [];
    while ($stmt->fetch()) {
        $parents[] = [
            'parent_id' => $id,
            'name' => $name,
            'phone_number' => $phone,
            'status' => $status,
            'created_at' => $created_at,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value
        ];
    }
    $stmt->close();
    return $parents;
}

/**
 * Cập nhật trạng thái tài khoản cho giáo viên hoặc phụ huynh.
 */
function updateUserStatus($conn, $user_type, $user_id, $new_status)
{
    $table = ($user_type === 'teacher') ? 'teacher' : 'parents';
    $id_column = ($user_type === 'teacher') ? 'teacher_id' : 'parent_id';

    $sql = "UPDATE $table SET status = ? WHERE $id_column = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $user_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        return true;
    }
    $stmt->close();
    return false;
}

function insertTeacherBonus($conn, $user_id, $bonus_amount, $bonus_reason, $effective_from, $effective_to)
{
    $sql = "INSERT INTO teacher_bonus (teacher_id, bonus_amount, bonus_reason, effective_from, effective_to)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idsss", $user_id, $bonus_amount, $bonus_reason, $effective_from, $effective_to);
    return $stmt->execute();
}

function deleteTeacherBonus($conn, $teacher_bonus_id)
{
    $sql = "DELETE FROM teacher_bonus WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_bonus_id);
    return $stmt->execute();
}

function getFilterTeacherBonus($conn, $user_id = 0, $filter_month = null)
{
    // Khởi tạo mảng để lưu các tham số và mảng điều kiện SQL
    $params = [];
    $conditions = [];

    // Kiểm tra và thêm điều kiện cho user_id nếu có
    if ($user_id !== null && $user_id !== 0) {
        $conditions[] = "tb.teacher_id = ?";
        $params[] = (int) $user_id; // Ép kiểu sang số nguyên
    }

    // Kiểm tra và thêm điều kiện cho filter_month nếu có
    if ($filter_month !== null) {
        $conditions[] = "MONTH(tb.effective_from) = ?";
        $params[] = (int) $filter_month; // Ép kiểu sang số nguyên
    }

    // Xây dựng câu truy vấn SQL với các điều kiện đã xác định
    $sql = "SELECT tb.id,tb.teacher_id,t.name,tb.bonus_amount,tb.bonus_reason,tb.effective_from,tb.effective_to FROM teacher_bonus as tb INNER JOIN teacher as t WHERE tb.teacher_id = t.teacher_id";
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    // Chuẩn bị câu truy vấn
    if ($stmt = $conn->prepare($sql)) {
        // Liên kết tham số với câu truy vấn
        if (!empty($params)) {
            $types = str_repeat('i', count($params)); // Tạo chuỗi kiểu dữ liệu 'i' cho từng tham số
            $stmt->bind_param($types, ...$params);
        }

        // Thực thi câu truy vấn
        $stmt->execute();

        // Lấy kết quả
        $result = $stmt->get_result();
        $bonuses = [];
        while ($row = $result->fetch_assoc()) {
            $bonuses[] = $row;
        }

        // Đóng kết nối
        $stmt->close();

        return $bonuses;
    } else {
        // Nếu không thể chuẩn bị câu truy vấn, trả về thông báo lỗi
        return "Lỗi chuẩn bị câu truy vấn: " . $conn->error;
    }
}

/**
 * Cập nhật lương của giáo viên.
 */
function updateTeacherSalaryMethod($conn, $user_id, $salary_method, $salary_value)
{
    $salary_type_id = -1;

    $salary_value = floatval(str_replace(',', '', $salary_value));
    if (is_numeric($salary_value)) {
        // Bước 1: Lấy salary_type_id từ bảng salary_type
        $sql1 = "SELECT salary_type_id FROM salary_type WHERE salary_method = ?";
        $stmt1 = $conn->prepare($sql1);
        if (!$stmt1) {
            die("Lỗi prepare: " . $conn->error);
        }
        $stmt1->bind_param("s", $salary_method);
        $stmt1->execute();
        $stmt1->bind_result($salary_type_id);
        if (!$stmt1->fetch()) {
            // Không tìm thấy salary_method hợp lệ => không cập nhật
            $stmt1->close();
            die("Không tìm thấy phương thức lương hợp lệ.");
        }
        $stmt1->close();

        // Bước 2: Đóng hiệu lực bản ghi cũ
        $sql2 = "SELECT id FROM salary_type_history WHERE teacher_id = ? AND effective_to IS NULL";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i",  $user_id);
        $stmt2->execute();
        $stmt2->store_result();
        if ($stmt2->num_rows > 0) {
            // Nếu có, đóng hiệu lực bản ghi cũ
            $sql3 = "UPDATE salary_type_history SET effective_to = CURDATE() WHERE teacher_id = ? AND effective_to IS NULL";
            $stmt3 = $conn->prepare($sql3);
            $stmt3->bind_param("i",  $user_id);
            $stmt3->execute();
            $stmt3->close();
        }
        $stmt2->close();

        // Bước 3: Thêm bản ghi mới vào salary_type_history
        $sql3 = "INSERT INTO salary_type_history (teacher_id, salary_type_id, effective_from, salary_value)
             VALUES (?, ?, CURDATE(), ?)";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param("iid",  $user_id, $salary_type_id, $salary_value);
        $stmt3->execute();
        $stmt3->close();
    } else {
        echo "Giá trị lương không hợp lệ!";
        exit();
    }

    $conn->close();
}

function updateTeacherSalaryValue($conn, $user_id, $salary_value)
{
    // 2. Cập nhật teacher với salary_type_id và salary_value
    $sql = "UPDATE teacher 
             SET salary_value = ?
             WHERE teacher_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $salary_value, $user_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        return true;
    }
    $stmt->close();
    return false;
}

/**
 * Xóa tài khoản người dùng (giáo viên, phụ huynh, học sinh).
 */
function deleteUserAccount($conn, $user_type, $user_id)
{
    $conn->begin_transaction();
    try {
        if ($user_type === 'teacher') {
            $stmt1 = $conn->prepare("DELETE FROM class_teacher_member WHERE teacher_id = ?");
            $stmt1->bind_param("i", $user_id);
            $stmt1->execute();
            $stmt1->close();
            $stmt2 = $conn->prepare("DELETE FROM teacher WHERE teacher_id = ?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $stmt2->close();
        } elseif ($user_type === 'parent') {
            $stmt1 = $conn->prepare("UPDATE student SET parent_id = NULL WHERE parent_id = ?");
            $stmt1->bind_param("i", $user_id);
            $stmt1->execute();
            $stmt1->close();
            $stmt2 = $conn->prepare("DELETE FROM parents WHERE parent_id = ?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $stmt2->close();
        } elseif ($user_type === 'student') {
            require_once __DIR__ . '/action.function.inc.php';
            deleteStudent($conn, $user_id);
        } else {
            throw new Exception("Loại người dùng không hợp lệ.");
        }
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Lỗi xóa tài khoản: " . $e->getMessage());
        return false;
    }
}

/**
 * Hàm trợ giúp để lấy màu cho badge trạng thái.
 */
function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'approved':
        case 'active':
            return 'success';
        case 'pending':
            return 'warning';
        case 'rejected':
        case 'inactive':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Cập nhật ưu đãi cho phụ huynh.
 */
function updateParentDiscount($conn, $parent_id, $discount_type, $discount_value)
{
    if ($discount_type === 'none' || $discount_type === 'free') {
        $discount_value = 0;
    }

    $sql = "UPDATE parents SET discount_type = ?, discount_value = ? WHERE parent_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdi", $discount_type, $discount_value, $parent_id);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    }
    $stmt->close();
    return false;
}

// *** THAY ĐỔI: Thêm các hàm quản lý phụ phí mới ***

/**
 * Lấy tất cả các khoản phụ phí từ CSDL.
 */
function getOtherFees($conn)
{
    $sql = "SELECT fee_id, fee_name, fee_value FROM other_fees ORDER BY fee_id ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Thêm một khoản phụ phí mới.
 */
function addOtherFee($conn, $fee_name, $fee_value)
{
    $sql = "INSERT INTO other_fees (fee_name, fee_value) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sd", $fee_name, $fee_value);
    return $stmt->execute();
}

/**
 * Xóa một khoản phụ phí.
 */
function deleteOtherFee($conn, $fee_id)
{
    $sql = "DELETE FROM other_fees WHERE fee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $fee_id);
    return $stmt->execute();
}

// *** KẾT THÚC THAY ĐỔI ***

/**
 * Tự động tạo hóa đơn cho tất cả học sinh cho một tháng cụ thể.
 * *** THAY ĐỔI: Tính tổng tất cả các khoản phụ phí từ bảng `other_fees` ***
 */
function generateMonthlyInvoices($conn, $month_to_generate)
{
    $year = date('Y', strtotime($month_to_generate));
    $month = date('m', strtotime($month_to_generate));
    $report = ['created' => 0, 'updated' => 0, 'failed' => 0, 'no_session' => 0];

    // *** THAY ĐỔI: Lấy tất cả phụ phí và tính tổng thay vì lấy từ settings ***
    $all_other_fees = getOtherFees($conn);
    $total_other_fee = 0;
    if (!empty($all_other_fees)) {
        $total_other_fee = array_sum(array_column($all_other_fees, 'fee_value'));
    }

    $sql_sessions = "
        SELECT
            s.student_id,
            s.parent_id,
            SUM(c.fee) AS total_fee,
            COUNT(a.attendance_id) AS session_count
        FROM
            student s
        JOIN class_student_member csm ON s.student_id = csm.student_id
        JOIN attendance a ON csm.class_id = a.class_id
        JOIN class c ON a.class_id = c.class_id
        WHERE
            YEAR(a.date) = ? AND MONTH(a.date) = ?
            AND NOT EXISTS (
                SELECT 1 FROM absentees ab
                WHERE ab.attendance_id = a.attendance_id AND ab.student_id = s.student_id
            )
            AND NOT EXISTS (
                SELECT 1 FROM on_leave ol
                WHERE ol.attendance_id = a.attendance_id AND ol.student_id = s.student_id
            )
        GROUP BY s.student_id, s.parent_id";

    $stmt_sessions = $conn->prepare($sql_sessions);
    $stmt_sessions->bind_param("ii", $year, $month);
    $stmt_sessions->execute();
    $result_sessions = $stmt_sessions->get_result();
    $student_fees_data = $result_sessions->fetch_all(MYSQLI_ASSOC);
    $stmt_sessions->close();

    if (empty($student_fees_data)) {
        return $report;
    }

    $sql_parents = "SELECT parent_id, discount_type, discount_value FROM parents";
    $result_parents = $conn->query($sql_parents);
    $discounts = [];
    while ($row = $result_parents->fetch_assoc()) {
        $discounts[$row['parent_id']] = $row;
    }
    $result_parents->close();

    $sql_existing = "SELECT student_id, invoice_id FROM monthly_invoices WHERE month = ?";
    $stmt_existing = $conn->prepare($sql_existing);
    $stmt_existing->bind_param("s", $month_to_generate);
    $stmt_existing->execute();
    $result_existing = $stmt_existing->get_result();
    $existing_invoices = [];
    while ($row = $result_existing->fetch_assoc()) {
        $existing_invoices[$row['student_id']] = $row['invoice_id'];
    }
    $stmt_existing->close();

    $stmt_insert = $conn->prepare("INSERT INTO monthly_invoices (student_id, month, sessions_attended, base_fee, other_fees, applied_discount, final_due_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'unpaid')");
    $stmt_update = $conn->prepare("UPDATE monthly_invoices SET sessions_attended = ?, base_fee = ?, other_fees = ?, applied_discount = ?, final_due_amount = ?, status = IF(amount_paid >= final_due_amount, 'paid', 'unpaid') WHERE invoice_id = ?");

    foreach ($student_fees_data as $student_data) {
        $student_id = $student_data['student_id'];
        $parent_id = $student_data['parent_id'];
        $base_fee = $student_data['total_fee'];
        $sessions_attended = $student_data['session_count'];

        $applied_discount = 0;
        if ($parent_id && isset($discounts[$parent_id])) {
            $d = $discounts[$parent_id];
            if ($d['discount_type'] == 'percentage') $applied_discount = ($base_fee * $d['discount_value']) / 100;
            elseif ($d['discount_type'] == 'fixed') $applied_discount = $base_fee + $total_other_fee - $d['discount_value'];
            elseif ($d['discount_type'] == 'free') $applied_discount = $base_fee + $total_other_fee;
        }
        // *** THAY ĐỔI: Sử dụng tổng phụ phí đã tính toán ở trên ***
        $final_due = max(0, $base_fee + $total_other_fee - $applied_discount);

        if (isset($existing_invoices[$student_id])) {
            $invoice_id = $existing_invoices[$student_id];
            // *** THAY ĐỔI: Truyền biến $total_other_fee vào câu lệnh update ***
            $stmt_update->bind_param("iddddi", $sessions_attended, $base_fee, $total_other_fee, $applied_discount, $final_due, $invoice_id);
            if ($stmt_update->execute()) $report['updated']++;
            else $report['failed']++;
        } else {
            // *** THAY ĐỔI: Truyền biến $total_other_fee vào câu lệnh insert ***
            $stmt_insert->bind_param("isidddd", $student_id, $month_to_generate, $sessions_attended, $base_fee, $total_other_fee, $applied_discount, $final_due);
            if ($stmt_insert->execute()) $report['created']++;
            else $report['failed']++;
        }
    }
    $stmt_insert->close();
    $stmt_update->close();

    return $report;
}

/**
 * Xóa tất cả hóa đơn của một tháng.
 */
function deleteAllInvoicesForMonth($conn, $month)
{
    $sql = "DELETE FROM monthly_invoices WHERE month = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $month);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

/**
 * Lấy tổng quan học phí theo tháng.
 */
function getTuitionSummaryForMonth($conn, $month)
{
    $sql = "SELECT 
                COALESCE(SUM(final_due_amount), 0) as total_due, 
                COALESCE(SUM(amount_paid), 0) as total_paid
            FROM monthly_invoices 
            WHERE month = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $result['total_remaining'] = $result['total_due'] - $result['total_paid'];
    return $result;
}

function getOverviewStats($conn)
{
    $stats = ['total_classes' => 0, 'total_teachers' => 0, 'total_students' => 0];
    $stats['total_classes'] = $conn->query("SELECT COUNT(*) FROM class")->fetch_row()[0] ?? 0;
    $stats['total_teachers'] = $conn->query("SELECT COUNT(*) FROM teacher")->fetch_row()[0] ?? 0;
    $stats['total_students'] = $conn->query("SELECT COUNT(*) FROM student")->fetch_row()[0] ?? 0;
    return $stats;
}

function getAccountsGroupedByClass($conn)
{
    $sql = "SELECT c.class_id, c.class_name, 
                   s.student_id, s.name as student_name, 
                   p.parent_id, p.name as parent_name, p.phone_number, p.discount_type, p.discount_value
            FROM class c
            LEFT JOIN class_student_member csm ON c.class_id = csm.class_id
            LEFT JOIN student s ON csm.student_id = s.student_id
            LEFT JOIN parents p ON s.parent_id = p.parent_id
            ORDER BY c.class_name, s.name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $all_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $grouped_data = [];
    foreach ($all_data as $row) {
        $class_id = $row['class_id'];
        if (!isset($grouped_data[$class_id])) {
            $grouped_data[$class_id] = ['class_name' => $row['class_name'], 'students' => [], 'parents' => []];
        }
        if ($row['student_id']) {
            $grouped_data[$class_id]['students'][$row['student_id']] = ['name' => $row['student_name']];
            if ($row['parent_id'] && !isset($grouped_data[$class_id]['parents'][$row['parent_id']])) {
                $grouped_data[$class_id]['parents'][$row['parent_id']] = [
                    'parent_name' => $row['parent_name'],
                    'phone_number' => $row['phone_number'],
                    'discount_type' => $row['discount_type'],
                    'discount_value' => $row['discount_value'],
                    'students' => []
                ];
            }
            if ($row['parent_id']) {
                $grouped_data[$class_id]['parents'][$row['parent_id']]['students'][] = $row['student_name'];
            }
        }
    }

    foreach ($grouped_data as &$class_data) {
        if (isset($class_data['parents'])) {
            foreach ($class_data['parents'] as &$parent_data) {
                $parent_data['students_str'] = implode(', ', $parent_data['students']);
            }
        }
    }
    return $grouped_data;
}

/**
 * SỬA LỖI: Đảm bảo key 'summary' luôn được khởi tạo.
 */
function getTuitionStatusByClass($conn, $month)
{
    // Bước 1: Lấy tất cả các lớp và học sinh của lớp đó
    $all_classes_sql = "SELECT c.class_id, c.class_name, s.student_id, s.name as student_name
                        FROM class c
                        JOIN class_student_member csm ON c.class_id = csm.class_id
                        JOIN student s ON csm.student_id = s.student_id
                        ORDER BY c.class_name, s.name";
    $result_classes = $conn->query($all_classes_sql);

    $grouped_data = [];
    $all_student_ids = [];
    while ($row = $result_classes->fetch_assoc()) {
        $class_id = $row['class_id'];
        if (!isset($grouped_data[$class_id])) {
            $grouped_data[$class_id] = [
                'class_name' => $row['class_name'],
                'invoices' => [],
                'summary' => ['total_due' => 0, 'total_paid' => 0, 'total_remaining' => 0]
            ];
        }
        // Khởi tạo thông tin học sinh, chờ dữ liệu hóa đơn
        $grouped_data[$class_id]['invoices'][$row['student_id']] = [
            'student_name' => $row['student_name'],
            'invoice_id' => null,
            'final_due_amount' => 0,
            'amount_paid' => 0
        ];
        $all_student_ids[] = $row['student_id'];
    }
    $result_classes->close();

    if (empty($all_student_ids)) {
        return []; // Trả về rỗng nếu không có học sinh nào trong bất kỳ lớp nào
    }

    // Bước 2: Lấy tất cả hóa đơn trong tháng của các học sinh đó
    $placeholders = implode(',', array_fill(0, count($all_student_ids), '?'));
    $types = str_repeat('i', count($all_student_ids));

    $sql_invoices = "SELECT student_id, invoice_id, final_due_amount, amount_paid 
                     FROM monthly_invoices 
                     WHERE month = ? AND student_id IN ($placeholders)";
    $stmt = $conn->prepare($sql_invoices);
    $stmt->bind_param("s" . $types, $month, ...$all_student_ids);
    $stmt->execute();
    $result_invoices = $stmt->get_result();

    $invoices_by_student = [];
    while ($row = $result_invoices->fetch_assoc()) {
        $invoices_by_student[$row['student_id']] = $row;
    }
    $stmt->close();

    // Bước 3: Gộp dữ liệu hóa đơn vào cấu trúc đã có
    foreach ($grouped_data as $class_id => &$class_data) {
        foreach ($class_data['invoices'] as $student_id => &$invoice_data) {
            if (isset($invoices_by_student[$student_id])) {
                $invoice_data['invoice_id'] = $invoices_by_student[$student_id]['invoice_id'];
                $invoice_data['final_due_amount'] = $invoices_by_student[$student_id]['final_due_amount'];
                $invoice_data['amount_paid'] = $invoices_by_student[$student_id]['amount_paid'];
            }
        }
        // Chuyển từ key student_id sang mảng tuần tự
        $class_data['invoices'] = array_values($class_data['invoices']);

        // Tính toán summary
        $class_data['summary']['total_due'] = array_sum(array_column($class_data['invoices'], 'final_due_amount'));
        $class_data['summary']['total_paid'] = array_sum(array_column($class_data['invoices'], 'amount_paid'));
        $class_data['summary']['total_remaining'] = $class_data['summary']['total_due'] - $class_data['summary']['total_paid'];
    }

    return $grouped_data;
}


function getRevenueByClassForMonth($conn, $month)
{
    $sql = "SELECT c.class_name, SUM(mi.amount_paid) as total_paid
            FROM monthly_invoices mi
            JOIN student s ON mi.student_id = s.student_id
            JOIN class_student_member csm ON s.student_id = csm.student_id
            JOIN class c ON csm.class_id = c.class_id
            WHERE mi.month = ?
            GROUP BY c.class_id, c.class_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $data;
}

function deleteInvoice($conn, $invoice_id)
{
    $sql = "DELETE FROM monthly_invoices WHERE invoice_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoice_id);
    return $stmt->execute();
}
function getSalaryReportForMonth($conn, $month)
{
    $sql = "SELECT t.teacher_id, t.name as teacher_name, c.class_id, c.class_name
            FROM teacher t 
            JOIN class_teacher_member ctm ON t.teacher_id = ctm.teacher_id
            JOIN class c ON ctm.class_id = c.class_id
            ORDER BY t.name, c.class_name";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher_class_pairs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $report = [];
    foreach ($teacher_class_pairs as $pair) {
        $teacher_id = $pair['teacher_id'];
        $teacher_name = $pair['teacher_name'];
        $class_id = $pair['class_id'];
        $class_name = $pair['class_name'];

        if (!isset($report[$teacher_id])) {
            $report[$teacher_id] = ['teacher_name' => $teacher_name, 'classes' => [], 'total_salary' => 0];
        }

        $salary_data = calculateAndSaveTeacherSalary($conn, $teacher_id, $class_id, $month);

        $report[$teacher_id]['classes'][] = [
            'class_name' => $class_name,
            'class_revenue' => $salary_data['class_revenue'],
            'total_sessions' => $salary_data['total_sessions'],
            'sessions_taught' => $salary_data['sessions_taught'],
            'calculated_salary' => $salary_data['calculated_salary'],
            'total_bonus' => $salary_data['total_bonus'],
            'salary_method' => $salary_data['salary_method'],
            'salary_value' => $salary_data['salary_value']
        ];
        $report[$teacher_id]['total_salary'] += $salary_data['calculated_salary'];
    }

    return $report;
}

function calculateAndSaveTeacherSalary($conn, $teacher_id, $class_id, $month)
{
    // $class_revenue = null;
    // $total_sessions = null;
    // $sessions_taught = null;

    $year = date('Y', strtotime($month));
    $month_num = date('m', strtotime($month));

    // 1. Lấy phương pháp + giá trị lương hiện thời
    $sql = "
      SELECT sth.salary_type_id, sth.salary_value, st.salary_method
      FROM salary_type_history sth
      JOIN salary_type st ON sth.salary_type_id = st.salary_type_id
      WHERE sth.teacher_id = ? AND sth.effective_from <= ? AND effective_to IS NULL
      ORDER BY sth.effective_from DESC
      LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $month_end = date('Y-m-t', strtotime($month));
    $stmt->bind_param("is", $teacher_id, $month_end);
    $stmt->execute();
    $stmt->bind_result($salary_type_id, $salary_value, $salary_method);
    if (!$stmt->fetch()) {
        // Nếu không có lịch sử nào → không tính
        $salary_type_id = null;
        $salary_value = 0;
        $salary_method = null;
    }
    $stmt->close();

    $stmt_rev = $conn->prepare("SELECT SUM(amount_paid) FROM monthly_invoices mi JOIN class_student_member csm ON mi.student_id = csm.student_id WHERE csm.class_id = ? AND mi.month = ?");
    $stmt_rev->bind_param("is", $class_id, $month);
    $stmt_rev->execute();
    $stmt_rev->bind_result($class_revenue);
    $stmt_rev->fetch();
    $stmt_rev->close();
    $class_revenue = $class_revenue ?? 0;

    // 3. Lấy tổng số buổi dạy trong tháng
    $stmt_total = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE class_id = ? AND YEAR(date) = ? AND MONTH(date) = ?");
    $stmt_total->bind_param("iii", $class_id, $year, $month_num);
    $stmt_total->execute();
    $stmt_total->bind_result($total_sessions);
    $stmt_total->fetch();
    $stmt_total->close();
    $total_sessions = $total_sessions ?? 0;

    // 4. Lấy số buổi dạy thực tế của giáo viên trong tháng
    $stmt_taught = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE class_id = ? AND teacher_id = ? AND YEAR(date) = ? AND MONTH(date) = ?");
    $stmt_taught->bind_param("iiii", $class_id, $teacher_id, $year, $month_num);
    $stmt_taught->execute();
    $stmt_taught->bind_result($sessions_taught);
    $stmt_taught->fetch();
    $stmt_taught->close();
    $sessions_taught = $sessions_taught ?? 0;

    // 5. Tính lương theo phương pháp
    $calculated_salary = 0;
    switch ($salary_method) {
        case 'percentage':
            $calculated_salary = ($class_revenue * $salary_value) / 100;
            break;

        case 'fixed_session':
            $calculated_salary = $salary_value * $sessions_taught;
            break;

        case 'monthly':
            //tổng số buổi dạy trong tháng $total_sessions 
            //tổng số buổi dạy thực tế của giáo viên trong tháng $sessions_taught
            $salary_per_class = $salary_value / $total_sessions;
            $calculated_salary =  $salary_per_class * $sessions_taught;;
            break;

        default:
            if ($total_sessions > 0) {
                $calculated_salary = ($class_revenue * $sessions_taught) / $total_sessions;
            }
            break;
    }

    // 6. Lấy tổng tiền thưởng cho giáo viên trong tháng
    $formatted_date = (string) (date('Y-m-d', strtotime($month_end)));
    $sql_bonus = "
        SELECT IFNULL(SUM(bonus_amount), 0) FROM teacher_bonus
        WHERE teacher_id = ? AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)";
    $stmt_bonus = $conn->prepare($sql_bonus);
    $stmt_bonus->bind_param("iss", $teacher_id, $formatted_date, $formatted_date);
    $stmt_bonus->execute();
    $stmt_bonus->bind_result($total_bonus);
    $stmt_bonus->fetch();
    $stmt_bonus->close();

    // 7. Tính tổng lương bao gồm thưởng
    // $calculated_salary = $calculated_salary + $total_bonus;
    $salary_type_id = $salary_type_id ?? 1; // Sử dụng giá trị mặc định nếu không có
    $sql_save = "INSERT INTO teacher_salaries (teacher_id, class_id, month, class_revenue, total_sessions, sessions_taught, calculated_salary,salary_type_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?,?)
                 ON DUPLICATE KEY UPDATE 
                    class_revenue = VALUES(class_revenue),
                    total_sessions = VALUES(total_sessions),
                    sessions_taught = VALUES(sessions_taught),
                    calculated_salary = VALUES(calculated_salary),
                    salary_type_id = VALUES(salary_type_id)";
    $stmt_save = $conn->prepare($sql_save);
    $stmt_save->bind_param("iisdiidi", $teacher_id, $class_id, $month, $class_revenue, $total_sessions, $sessions_taught, $calculated_salary, $salary_type_id);
    $stmt_save->execute();
    $stmt_save->close();

    return [
        'class_revenue' => $class_revenue,
        'total_sessions' => $total_sessions,
        'sessions_taught' => $sessions_taught,
        'calculated_salary' => $calculated_salary,
        'total_bonus' => $total_bonus,
        'salary_method' => $salary_method,
        'salary_value' => $salary_value
    ];
}

function getUnassignedStudents($conn)
{
    $sql = "SELECT s.student_id, s.name 
            FROM student s 
            LEFT JOIN class_student_member csm ON s.student_id = csm.student_id 
            WHERE csm.class_id IS NULL 
            ORDER BY s.name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $students;
}
function deleteMultipleUsers($conn, $user_type, $user_ids)
{
    if (empty($user_ids) || !is_array($user_ids)) {
        return false;
    }

    $table = $user_type;
    $id_column = $user_type . '_id';
    if ($user_type === 'parent') {
        $table = 'parents';
    }

    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $types = str_repeat('i', count($user_ids));

    $sql = "DELETE FROM $table WHERE $id_column IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$user_ids);

    return $stmt->execute();
}

/**
 * HÀM MỚI: Thêm giáo viên
 */
function addTeacher($conn, $name, $email, $password)
{
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO teacher (name, email, password, status) VALUES (?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $name, $email, $hashed_password);
    if ($stmt->execute()) {
        return true;
    }
    return false; // Lỗi (có thể do email trùng)
}

/**
 * HÀM MỚI: Lấy chi tiết một giáo viên
 */
function getTeacherDetails($conn, $teacher_id)
{
    $sql = "SELECT teacher_id, name, email FROM teacher WHERE teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getTeacherBonusDetails($conn, $id)
{
    $sql = "SELECT id,teacher_id, bonus_amount, bonus_reason, effective_from, effective_to  FROM teacher_bonus WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function adminUpdateTeacherBonus($conn, $bonus_id, $teacher_id, $bonus_amount, $bonus_reason, $effective_from, $effective_to)
{
    $sql = "UPDATE teacher_bonus SET teacher_id = ?, bonus_amount = ?, bonus_reason = ?, effective_from = ?, effective_to = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // Xử lý lỗi
        die('Lỗi chuẩn bị câu lệnh: ' . $conn->error);
    }
    $stmt->bind_param("idsssi", $teacher_id, $bonus_amount, $bonus_reason, $effective_from, $effective_to, $bonus_id);
    if ($stmt->execute()) {
        return true;
    }
    return false; // Lỗi (có thể do email trùng)
}
/**
 * HÀM MỚI: Cập nhật thông tin giáo viên
 */
function updateTeacher($conn, $teacher_id, $name, $email, $password)
{
    if (!empty($password)) {
        // Nếu có mật khẩu mới, cập nhật cả mật khẩu
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE teacher SET name = ?, email = ?, password = ? WHERE teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $name, $email, $hashed_password, $teacher_id);
    } else {
        // Nếu không, chỉ cập nhật tên và email
        $sql = "UPDATE teacher SET name = ?, email = ? WHERE teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $email, $teacher_id);
    }
    if ($stmt->execute()) {
        return true;
    }
    return false; // Lỗi (có thể do email trùng)
}

function formatDiscountInfo($type, $value)
{
    $type_map = [
        'none' => 'Không có',
        'percentage' => 'Giảm theo %',
        'fixed' => 'Giảm cố định',
        'free' => 'Miễn phí'
    ];
    $display_type = $type_map[$type] ?? ucfirst($type);

    if ($type === 'percentage') {
        return "<span class='badge bg-primary'><i class='fas fa-tag me-1'></i>" . htmlspecialchars($display_type) . ": " . htmlspecialchars($value) . "%</span>";
    } elseif ($type === 'fixed') {
        return "<span class='badge bg-info text-dark'><i class='fas fa-tag me-1'></i>" . htmlspecialchars($display_type) . ": " . number_format($value) . " VNĐ</span>";
    } elseif ($type === 'free') {
        return "<span class='badge bg-success'><i class='fas fa-star me-1'></i>" . htmlspecialchars($display_type) . "</span>";
    } else {
        return "<span class='badge bg-secondary'>" . htmlspecialchars($display_type) . "</span>";
    }
}
// function getAllStudentsWithParentInfo($conn) {
//     $sql = "SELECT 
//                 s.student_id, 
//                 s.name as student_name,
//                 p.name as parent_name,
//                 p.phone_number
//             FROM student s
//             LEFT JOIN parents p ON s.parent_id = p.parent_id
//             ORDER BY s.name ASC";

//     $result = $conn->query($sql);
//     if (!$result) {
//         return []; // Trả về mảng rỗng nếu có lỗi truy vấn
//     }
//     return $result->fetch_all(MYSQLI_ASSOC);
// }
function getStudentPackageHistory($conn, $student_id)
{
    $sql = "SELECT package_id, total_sessions, remaining_sessions, purchase_date, is_active, notes 
            FROM student_course_packages 
            WHERE student_id = ? 
            ORDER BY purchase_date DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * *** HÀM MỚI ***
 * Thêm một gói học phí mới cho học sinh và ghi nhận thanh toán.
 */
function adminAddStudentPackage($conn, $student_id, $total_sessions, $payment_amount, $notes)
{
    // 1. Lấy parent_id của học sinh
    $stmt = $conn->prepare("SELECT parent_id FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_data = $result->fetch_assoc();

    if (!$student_data || empty($student_data['parent_id'])) {
        return ['success' => false, 'message' => 'Học sinh này chưa được liên kết với phụ huynh.'];
    }
    $parent_id = $student_data['parent_id'];

    $conn->begin_transaction();
    try {
        // 2. Thêm vào bảng thanh toán
        $sql_payment = "INSERT INTO tuition_payments (parent_id, student_id, amount, payment_date, notes) VALUES (?, ?, ?, CURDATE(), ?)";
        $stmt_payment = $conn->prepare($sql_payment);
        $payment_note = "Thanh toán gói " . $total_sessions . " buổi. Ghi chú: " . $notes;
        $stmt_payment->bind_param("iids", $parent_id, $student_id, $payment_amount, $payment_note);
        $stmt_payment->execute();
        $payment_id = $conn->insert_id;

        // 3. Thêm vào bảng gói học
        $sql_package = "INSERT INTO student_course_packages (student_id, parent_id, payment_id, total_sessions, remaining_sessions, purchase_date, is_active, notes) VALUES (?, ?, ?, ?, ?, CURDATE(), 1, ?)";
        $stmt_package = $conn->prepare($sql_package);
        $stmt_package->bind_param("iiiiis", $student_id, $parent_id, $payment_id, $total_sessions, $total_sessions, $notes);
        $stmt_package->execute();

        // 4. Cập nhật tuition_model cho phụ huynh
        $sql_parent = "UPDATE parents SET tuition_model = 'package' WHERE parent_id = ?";
        $stmt_parent = $conn->prepare($sql_parent);
        $stmt_parent->bind_param("i", $parent_id);
        $stmt_parent->execute();

        $conn->commit();
        return ['success' => true, 'message' => 'Thêm gói học phí thành công!'];
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Thao tác thất bại do lỗi cơ sở dữ liệu.'];
    }
}
function getAllStudentsWithParentInfo($conn)
{
    $sql = "SELECT s.student_id, s.name as student_name, p.name as parent_name, p.phone_number
            FROM student s LEFT JOIN parents p ON s.parent_id = p.parent_id
            ORDER BY s.name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * === HÀM NÂNG CẤP ===
 * Lấy tất cả các khóa học đã đăng ký và các đợt thanh toán của một học sinh.
 */
function getStudentEnrollmentsWithInstallments($conn, $student_id)
{
    $enrollments = [];
    $sql_enroll = "SELECT * FROM course_enrollments WHERE student_id = ? ORDER BY purchase_date DESC";
    $stmt_enroll = $conn->prepare($sql_enroll);
    $stmt_enroll->bind_param("i", $student_id);
    $stmt_enroll->execute();
    $result_enroll = $stmt_enroll->get_result();

    while ($enrollment = $result_enroll->fetch_assoc()) {
        $enrollment_id = $enrollment['package_id'];
        $enrollments[$enrollment_id] = $enrollment;
        $enrollments[$enrollment_id]['installments'] = [];

        $sql_install = "SELECT * FROM payment_installments WHERE enrollment_id = ? ORDER BY due_date ASC";
        $stmt_install = $conn->prepare($sql_install);
        $stmt_install->bind_param("i", $enrollment_id);
        $stmt_install->execute();
        $result_install = $stmt_install->get_result();
        while ($installment = $result_install->fetch_assoc()) {
            $enrollments[$enrollment_id]['installments'][] = $installment;
        }
    }
    return array_values($enrollments); // Trả về mảng không có key
}

/**
 * === HÀM MỚI ===
 * Tạo một bản ghi đăng ký khóa học mới cho học sinh.
 */
function adminCreateCourseEnrollment($conn, $student_id, $course_name, $total_fee, $total_sessions, $notes)
{
    // Lấy parent_id
    $stmt_parent = $conn->prepare("SELECT parent_id FROM student WHERE student_id = ?");
    $stmt_parent->bind_param("i", $student_id);
    $stmt_parent->execute();
    $parent_id = $stmt_parent->get_result()->fetch_assoc()['parent_id'];
    if (!$parent_id) return ['success' => false, 'message' => 'Học sinh chưa có phụ huynh.'];

    $sql = "INSERT INTO course_enrollments (student_id, parent_id, course_name, total_fee, total_sessions, remaining_sessions, purchase_date, notes) 
            VALUES (?, ?, ?, ?, ?, 0, CURDATE(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisids", $student_id, $parent_id, $course_name, $total_fee, $total_sessions, $notes);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Tạo khóa học thành công!'];
    }
    return ['success' => false, 'message' => 'Lỗi khi tạo khóa học.'];
}

/**
 * === HÀM MỚI ===
 * Thêm một đợt thanh toán mới cho một khóa học.
 */
function adminAddPaymentInstallment($conn, $enrollment_id, $amount_due, $due_date)
{
    $sql = "INSERT INTO payment_installments (enrollment_id, amount_due, due_date) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ids", $enrollment_id, $amount_due, $due_date);
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Thêm đợt thanh toán thành công.'];
    }
    return ['success' => false, 'message' => 'Lỗi khi thêm đợt thanh toán.'];
}

/**
 * === HÀM MỚI ===
 * Ghi nhận thanh toán cho một đợt và kích hoạt số buổi học tương ứng.
 */
function adminRecordInstallmentPayment($conn, $installment_id)
{
    $conn->begin_transaction();
    try {
        // 1. Lấy thông tin đợt thanh toán và khóa học
        $sql_info = "SELECT pi.amount_due, pi.enrollment_id, ce.total_fee, ce.total_sessions, ce.student_id, ce.parent_id
                     FROM payment_installments pi
                     JOIN course_enrollments ce ON pi.enrollment_id = ce.package_id
                     WHERE pi.installment_id = ?";
        $stmt_info = $conn->prepare($sql_info);
        $stmt_info->bind_param("i", $installment_id);
        $stmt_info->execute();
        $info = $stmt_info->get_result()->fetch_assoc();
        if (!$info) throw new Exception("Không tìm thấy đợt thanh toán.");

        // 2. Ghi nhận thanh toán vào bảng `tuition_payments`
        $sql_payment = "INSERT INTO tuition_payments (parent_id, student_id, amount, payment_date, notes) VALUES (?, ?, ?, CURDATE(), ?)";
        $stmt_payment = $conn->prepare($sql_payment);
        $note = "Thanh toán đợt cho khóa học ID: " . $info['enrollment_id'];
        $stmt_payment->bind_param("iids", $info['parent_id'], $info['student_id'], $info['amount_due'], $note);
        $stmt_payment->execute();
        $payment_id = $conn->insert_id;

        // 3. Cập nhật trạng thái đợt thanh toán
        $sql_update_install = "UPDATE payment_installments SET status = 'paid', payment_id = ? WHERE installment_id = ?";
        $stmt_update_install = $conn->prepare($sql_update_install);
        $stmt_update_install->bind_param("ii", $payment_id, $installment_id);
        $stmt_update_install->execute();

        // 4. Tính toán và kích hoạt số buổi học
        $sessions_to_activate = 0;
        if ($info['total_fee'] > 0) {
            $sessions_to_activate = floor(($info['amount_due'] / $info['total_fee']) * $info['total_sessions']);
        }

        // 5. Cập nhật khóa học: cộng dồn số tiền đã đóng và số buổi còn lại
        $sql_update_enroll = "UPDATE course_enrollments 
                              SET amount_paid = amount_paid + ?, remaining_sessions = remaining_sessions + ? 
                              WHERE package_id = ?";
        $stmt_update_enroll = $conn->prepare($sql_update_enroll);
        $stmt_update_enroll->bind_param("dii", $info['amount_due'], $sessions_to_activate, $info['enrollment_id']);
        $stmt_update_enroll->execute();

        $conn->commit();
        return ['success' => true, 'message' => "Thanh toán thành công! Đã kích hoạt thêm $sessions_to_activate buổi học."];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
function getAccountManagementData($conn)
{
    $sql = "SELECT 
                c.class_id, c.class_name,
                s.student_id, s.name as student_name, s.parent_id as student_parent_id,
                p.parent_id, p.name as parent_name, p.phone_number, p.tuition_model, p.discount_type, p.discount_value,
                (SELECT SUM(ce.remaining_sessions) FROM course_enrollments ce WHERE ce.student_id = s.student_id AND ce.status = 'active') as remaining_sessions,
                (SELECT COUNT(*) FROM payment_installments pi JOIN course_enrollments ce ON pi.enrollment_id = ce.package_id WHERE ce.parent_id = p.parent_id AND pi.status = 'overdue' AND pi.due_date < CURDATE()) as overdue_installments
            FROM class_student_member csm
            JOIN student s ON csm.student_id = s.student_id
            JOIN class c ON csm.class_id = c.class_id
            LEFT JOIN parents p ON s.parent_id = p.parent_id
            ORDER BY c.class_name, p.name, s.name";

    $result = $conn->query($sql);
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $grouped_data = [];
    foreach ($results as $row) {
        $class_id = $row['class_id'];
        if (!isset($grouped_data[$class_id])) {
            $grouped_data[$class_id] = [
                'class_name' => $row['class_name'],
                'parents' => [],
                'unlinked_students' => []
            ];
        }

        $student_info = [
            'name' => $row['student_name'],
            'remaining_sessions' => $row['remaining_sessions']
        ];

        if ($row['parent_id']) {
            $parent_id = $row['parent_id'];
            if (!isset($grouped_data[$class_id]['parents'][$parent_id])) {
                $grouped_data[$class_id]['parents'][$parent_id] = [
                    'parent_name' => $row['parent_name'],
                    'phone_number' => $row['phone_number'],
                    'tuition_model' => $row['tuition_model'],
                    'discount_type' => $row['discount_type'],
                    'discount_value' => $row['discount_value'],
                    'overdue_count' => $row['overdue_installments'],
                    'students' => []
                ];
            }
            $grouped_data[$class_id]['parents'][$parent_id]['students'][$row['student_id']] = $student_info;
        } else {
            // Học sinh không có phụ huynh được liên kết
            $grouped_data[$class_id]['unlinked_students'][$row['student_id']] = $student_info;
        }
    }

    return $grouped_data;
}


/**
 * === HÀM MỚI: Xử lý logic thêm gia đình mới ===
 */
function adminAddNewFamily($conn, $parent_data, $students_data)
{
    // Kiểm tra SĐT phụ huynh
    $stmt_check = $conn->prepare("SELECT parent_id FROM parents WHERE phone_number = ?");
    $stmt_check->bind_param("s", $parent_data['phone']);
    $stmt_check->execute();
    if ($stmt_check->get_result()->fetch_assoc()) {
        return ['success' => false, 'message' => 'Số điện thoại của phụ huynh đã tồn tại.'];
    }

    $conn->begin_transaction();
    try {
        // 1. Tạo tài khoản phụ huynh
        $sql_parent = "INSERT INTO parents (name, phone_number, discount_type, discount_value, tuition_model, status) VALUES (?, ?, ?, ?, ?, 'approved')";
        $stmt_parent = $conn->prepare($sql_parent);
        $stmt_parent->bind_param("sssss", $parent_data['name'], $parent_data['phone'], $parent_data['discount_type'], $parent_data['discount_value'], $parent_data['tuition_model']);
        $stmt_parent->execute();
        $parent_id = $conn->insert_id;

        // 2. Tạo tài khoản học sinh và liên kết
        $sql_student = "INSERT INTO student (name, email, password, token, parent_id, school_name, student_class) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_student = $conn->prepare($sql_student);

        foreach ($students_data as $student) {
            // Kiểm tra email học sinh
            $stmt_check_student = $conn->prepare("SELECT student_id FROM student WHERE email = ?");
            $stmt_check_student->bind_param("s", $student['email']);
            $stmt_check_student->execute();
            if ($stmt_check_student->get_result()->fetch_assoc()) {
                throw new Exception("Email của học sinh " . $student['name'] . " đã tồn tại.");
            }

            $hashed_password = password_hash($student['password'], PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(16));
            $stmt_student->bind_param("ssssiss", $student['name'], $student['email'], $hashed_password, $token, $parent_id, $student['school'], $student['class']);
            $stmt_student->execute();
        }

        $conn->commit();
        return ['success' => true, 'message' => 'Thêm gia đình mới thành công!'];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
function getPendingPayments($conn)
{
    $sql = "SELECT 
                tp.payment_id, tp.amount, tp.payment_date, tp.notes,
                s.name as student_name,
                t.name as teacher_name
            FROM tuition_payments tp
            JOIN student s ON tp.student_id = s.student_id
            LEFT JOIN teacher t ON tp.recorded_by_teacher_id = t.teacher_id
            WHERE tp.status = 'pending'
            ORDER BY tp.payment_date DESC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * === HÀM MỚI ===
 * Admin xác nhận một khoản thanh toán và cập nhật vào hóa đơn tương ứng.
 */
function adminConfirmPayment($conn, $payment_id)
{
    $conn->begin_transaction();
    try {
        // 1. Lấy thông tin thanh toán
        $stmt_info = $conn->prepare("SELECT * FROM tuition_payments WHERE payment_id = ? AND status = 'pending'");
        $stmt_info->bind_param("i", $payment_id);
        $stmt_info->execute();
        $payment = $stmt_info->get_result()->fetch_assoc();

        if (!$payment) {
            throw new Exception("Không tìm thấy thanh toán hoặc đã được xác nhận.");
        }

        // 2. Kiểm tra xem đây là thanh toán hóa đơn hay nạp số dư
        $invoice_id_to_update = $payment['invoice_id'];

        if ($invoice_id_to_update) {
            // Nếu là thanh toán hóa đơn, cập nhật vào hóa đơn cụ thể
            $stmt_update_invoice = $conn->prepare(
                "UPDATE monthly_invoices SET amount_paid = amount_paid + ? WHERE invoice_id = ?"
            );
            $stmt_update_invoice->bind_param("di", $payment['amount'], $invoice_id_to_update);
            $stmt_update_invoice->execute();

            // Cập nhật lại trạng thái của hóa đơn đó
            $stmt_get_invoice = $conn->prepare("SELECT final_due_amount, amount_paid FROM monthly_invoices WHERE invoice_id = ?");
            $stmt_get_invoice->bind_param("i", $invoice_id_to_update);
            $stmt_get_invoice->execute();
            $invoice = $stmt_get_invoice->get_result()->fetch_assoc();

            if ($invoice) {
                $new_status = 'unpaid';
                if ($invoice['amount_paid'] >= $invoice['final_due_amount']) {
                    $new_status = ($invoice['amount_paid'] > $invoice['final_due_amount']) ? 'overpaid' : 'paid';
                } else if ($invoice['amount_paid'] > 0) {
                    $new_status = 'debt';
                }
                $stmt_update_status = $conn->prepare("UPDATE monthly_invoices SET status = ? WHERE invoice_id = ?");
                $stmt_update_status->bind_param("si", $new_status, $invoice_id_to_update);
                $stmt_update_status->execute();
            }
        } else {
            // Nếu là nạp số dư hoặc thanh toán lẻ không có hóa đơn
            $is_balance_deposit = strpos($payment['notes'], 'Nạp tiền vào số dư') !== false;
            if ($is_balance_deposit) {
                $stmt_update_balance = $conn->prepare("UPDATE parents SET balance = balance + ? WHERE parent_id = ?");
                $stmt_update_balance->bind_param("di", $payment['amount'], $payment['parent_id']);
                $stmt_update_balance->execute();
            }
            // Nếu không phải nạp số dư, đây là một khoản lẻ sẽ được Admin phân bổ sau.
        }

        // 3. Cập nhật trạng thái thanh toán thành 'confirmed'
        $stmt_confirm = $conn->prepare("UPDATE tuition_payments SET status = 'confirmed' WHERE payment_id = ?");
        $stmt_confirm->bind_param("i", $payment_id);
        $stmt_confirm->execute();

        $conn->commit();
        return ['success' => true, 'message' => 'Xác nhận thanh toán thành công!'];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
function get_parent_package_details($conn, $student_id)
{
    $sql = "SELECT 
                ce.package_id,
                ce.course_name,
                ce.total_fee,
                ce.amount_paid,
                ce.remaining_sessions,
                (SELECT MIN(pi.due_date) FROM payment_installments pi WHERE pi.enrollment_id = ce.package_id AND pi.status = 'pending') AS next_due_date,
                (SELECT pi.amount_due FROM payment_installments pi WHERE pi.enrollment_id = ce.package_id AND pi.status = 'pending' ORDER BY pi.due_date ASC LIMIT 1) AS next_due_amount
            FROM course_enrollments ce
            WHERE ce.student_id = ? AND ce.is_active = 1
            LIMIT 1;";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;

    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    return $data;
}
function get_dashboard_summary($conn)
{
    $summary = [
        'total_students' => 0,
        'active_classes' => 0,
        'total_teachers' => 0,
        'pending_parents' => 0,
    ];

    // Total students
    $result = $conn->query("SELECT COUNT(*) as count FROM student");
    if ($result) $summary['total_students'] = $result->fetch_assoc()['count'];

    // Active classes
    $result = $conn->query("SELECT COUNT(*) as count FROM class");
    if ($result) $summary['active_classes'] = $result->fetch_assoc()['count'];

    // Total teachers
    $result = $conn->query("SELECT COUNT(*) as count FROM teacher WHERE status = 'approved'");
    if ($result) $summary['total_teachers'] = $result->fetch_assoc()['count'];

    // Pending parents
    $result = $conn->query("SELECT COUNT(*) as count FROM parents WHERE status = 'pending'");
    if ($result) $summary['pending_parents'] = $result->fetch_assoc()['count'];

    return $summary;
}


// Function to get all accounts data
function get_all_accounts($conn)
{
    $accounts = [
        'teachers' => [],
        'pending_teachers' => [],
        'students' => [],
        'unassigned_students' => [],
        'parents' => [],
        'pending_parents' => []
    ];

    // Approved Teachers
    $accounts['teachers'] = $conn->query("SELECT teacher_id, name, email FROM teacher WHERE status = 'approved'")->fetch_all(MYSQLI_ASSOC);

    // Pending Teachers
    $accounts['pending_teachers'] = $conn->query("SELECT teacher_id, name, email FROM teacher WHERE status = 'pending'")->fetch_all(MYSQLI_ASSOC);

    // All Students
    $accounts['students'] = $conn->query("SELECT student_id, name, email FROM student")->fetch_all(MYSQLI_ASSOC);

    // Unassigned Students
    $accounts['unassigned_students'] = $conn->query("SELECT s.student_id, s.name, s.email FROM student s LEFT JOIN class_student_member csm ON s.student_id = csm.student_id WHERE csm.class_id IS NULL")->fetch_all(MYSQLI_ASSOC);

    // Approved Parents
    $accounts['parents'] = $conn->query("SELECT parent_id, name, phone_number, discount_type, discount_value FROM parents WHERE status = 'approved'")->fetch_all(MYSQLI_ASSOC);

    // Pending Parents
    $accounts['pending_parents'] = $conn->query("SELECT parent_id, name, phone_number FROM parents WHERE status = 'pending'")->fetch_all(MYSQLI_ASSOC);

    return $accounts;
}

// Function to get all tuition packages categorized
function get_all_package_enrollments_categorized($conn)
{
    $sql = "SELECT 
                ce.package_id,
                s.name as student_name,
                p.name as parent_name,
                ce.course_name,
                ce.total_fee,
                ce.amount_paid,
                ce.remaining_sessions,
                MIN(pi.due_date) as next_due_date
            FROM course_enrollments ce
            JOIN student s ON ce.student_id = s.student_id
            JOIN parents p ON ce.parent_id = p.parent_id
            LEFT JOIN payment_installments pi ON ce.package_id = pi.enrollment_id AND pi.status = 'pending'
            WHERE ce.is_active = 1
            GROUP BY ce.package_id
            ORDER BY next_due_date ASC, s.name ASC";

    $result = $conn->query($sql);
    $all_packages = $result->fetch_all(MYSQLI_ASSOC);

    $categorized = [
        'due_soon' => [],
        'in_progress' => [],
        'completed' => []
    ];

    $seven_days_from_now = date('Y-m-d', strtotime('+7 days'));

    foreach ($all_packages as $pkg) {
        $is_completed = ($pkg['total_fee'] - $pkg['amount_paid']) <= 0;

        if ($is_completed) {
            $categorized['completed'][] = $pkg;
        } elseif ($pkg['next_due_date'] && $pkg['next_due_date'] <= $seven_days_from_now) {
            $categorized['due_soon'][] = $pkg;
        } else {
            $categorized['in_progress'][] = $pkg;
        }
    }

    return $categorized;
}

// Function to get all pending payments grouped by teacher
function get_pending_payments_grouped_by_teacher($conn)
{
    $sql = "SELECT 
                tp.payment_id,
                tp.amount,
                tp.payment_date,
                tp.notes,
                s.name as student_name,
                p.name as parent_name,
                t.teacher_id,
                t.name as teacher_name
            FROM tuition_payments tp
            JOIN student s ON tp.student_id = s.student_id
            JOIN parents p ON tp.parent_id = p.parent_id
            JOIN teacher t ON tp.recorded_by_teacher_id = t.teacher_id
            WHERE tp.status = 'pending'
            ORDER BY t.name, tp.payment_date ASC";

    $result = $conn->query($sql);
    $payments = $result->fetch_all(MYSQLI_ASSOC);

    $grouped_payments = [];
    foreach ($payments as $payment) {
        $teacher_id = $payment['teacher_id'];
        if (!isset($grouped_payments[$teacher_id])) {
            $grouped_payments[$teacher_id] = [
                'teacher_name' => $payment['teacher_name'],
                'payments' => [],
                'total_amount' => 0
            ];
        }
        $grouped_payments[$teacher_id]['payments'][] = $payment;
        $grouped_payments[$teacher_id]['total_amount'] += $payment['amount'];
    }

    return $grouped_payments;
}
