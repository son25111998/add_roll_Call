<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// File: includes/admin_action.inc.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../config/dbh.inc.php';
require_once __DIR__ . '/admin.function.inc.php';
// *** SỬA LỖI: Thêm dòng require_once còn thiếu ***
require_once __DIR__ . '/action.function.inc.php';


if (!isset($_SESSION['admin_id'])) {
    header("location: " . BASE_URL . "admin/?error=notloggedin");
    exit();
}

$action = $_POST['action'] ?? '';

// echo $_POST['action'];
// exit();

$redirect_url = BASE_URL . "admin/dashboard.php";

switch ($action) {
    case 'update_teacher_status':
        updateUserStatus($conn, 'teacher', $_POST['user_id'], $_POST['new_status']);
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã cập nhật trạng thái giáo viên.'];
        $redirect_url .= "?tab=accounts";
        break;

    case 'update_teacher_salary_method':
        updateTeacherSalaryMethod($conn, $_POST['user_id'], $_POST['salary_method'], $_POST['salary_value']);
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã cập nhật lương và phương thức tính lương của giáo viên.'];
        $redirect_url .= "?tab=accounts";
        break;

    case 'insert_teacher_bonus':
        insertTeacherBonus($conn, $_POST['user_id'], $_POST['bonus_amount'], $_POST['bonus_reason'], $_POST['effective_from'], $_POST['effective_to']);
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã thêm thưởng của giáo viên.'];
        $redirect_url .= "?tab=bonus";
        break;

    case 'delete_teacher_bonus':
        deleteTeacherBonus($conn, $_POST['teacher_bonus_id']);
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã xóa thưởng của giáo viên.'];
        $redirect_url .= "?tab=bonus";
        break;

        $method = $_POST['salary_method'] ?? null;
        // var_dump($_POST);
        $salaryValue = null;
        switch ($method) {
            case 'fixed_session':
                if (isset($_POST['salary_fixed_session'])) {
                    $salaryValue = (float)  $_POST['salary_fixed_session'];
                }
                break;
            case 'percentage':
                if (isset($_POST['salary_percentage'])) {
                    $salaryValue = (float)  $_POST['salary_percentage'];
                }
                break;
            case 'monthly':
                if (isset($_POST['salary_monthly'])) {
                    $salaryValue = (float) $_POST['salary_monthly'];
                }
                break;
        }

        updateTeacherSalaryValue($conn, $_POST['user_id'], $salaryValue);
        echo $salaryValue;
        var_dump($salaryValue);
        exit();
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã cập nhật lương và phương thức tính lương của giáo viên.'];
        $redirect_url .= "?tab=accounts";
        break;

    case 'delete_user': // Xóa đơn lẻ
        deleteUserAccount($conn, $_POST['user_type'], $_POST['user_id']);
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã xóa tài khoản thành công.'];
        $redirect_url .= "?tab=accounts";
        break;

    case 'bulk_delete_user': // Xóa hàng loạt
        $user_ids = $_POST['user_ids'] ?? [];
        deleteMultipleUsers($conn, $_POST['user_type'], $user_ids);
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã xóa các tài khoản được chọn.'];
        $redirect_url .= "?tab=accounts";
        break;

    case 'update_discount':
        updateParentDiscount($conn, $_POST['parent_id'], $_POST['discount_type'], $_POST['discount_value'] ?? 0);
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã cập nhật ưu đãi cho phụ huynh.'];
        $redirect_url .= "?tab=accounts";
        break;

    case 'delete_invoice':
        deleteInvoice($conn, $_POST['invoice_id']);
        $_SESSION['system_message'] = ['type' => 'success', 'message' => 'Đã xóa hóa đơn.'];
        $redirect_url .= "?tab=tuition&month=" . $_POST['month'];
        break;

    case 'generate_invoices':
        $month = $_POST['month'];
        $report = generateMonthlyInvoices($conn, $month);
        $_SESSION['invoice_report'] = $report;
        $redirect_url .= "?tab=tuition&month=$month";
        break;

    case 'regenerate_invoices':
        $month = $_POST['month'];
        deleteAllInvoicesForMonth($conn, $month);
        $_SESSION['system_message'] = ['type' => 'info', 'message' => 'Đã xóa' . date('m/Y', strtotime($month)) . '.'];
        $redirect_url .= "?tab=tuition&month=$month";
        break;

    case 'calculate_salary':
        $teacher_id = $_POST['teacher_id'];
        $class_id = $_POST['class_id'];
        $month = $_POST['month'];
        calculateAndSaveTeacherSalary($conn, $teacher_id, $class_id, $month);
        $redirect_url .= "?tab=salary&salary_teacher_id=$teacher_id&salary_month=$month";
        break;
    case 'approve_parent':
        $parent_id = $_POST['parent_id'] ?? 0;
        if ($parent_id > 0) {
            $stmt = $conn->prepare("UPDATE parents SET status = 'approved' WHERE parent_id = ?");
            $stmt->bind_param("i", $parent_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Parent approved successfully.'];
            } else {
                $response['message'] = 'Failed to approve parent.';
            }
            $stmt->close();
        }
        break;

    case 'approve_teacher':
        $teacher_id = $_POST['teacher_id'] ?? 0;
        if ($teacher_id > 0) {
            $stmt = $conn->prepare("UPDATE teacher SET status = 'approved' WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Teacher approved successfully.'];
            } else {
                $response['message'] = 'Failed to approve teacher.';
            }
            $stmt->close();
        }
        break;

    case 'assign_student_to_class':
        $student_id = $_POST['student_id'] ?? 0;
        $class_id = $_POST['class_id'] ?? 0;
        if ($student_id > 0 && $class_id > 0) {
            // Check if the student is already in the class
            $check_stmt = $conn->prepare("SELECT member_id FROM class_student_member WHERE student_id = ? AND class_id = ?");
            $check_stmt->bind_param("ii", $student_id, $class_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                $response['message'] = 'Học sinh đã có trong lớp này.';
            } else {
                $stmt = $conn->prepare("INSERT INTO class_student_member (student_id, class_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $student_id, $class_id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Phân lớp thành công!'];
                } else {
                    $response['message'] = 'Lỗi khi phân lớp.';
                }
                $stmt->close();
            }
            $check_stmt->close();
        } else {
            $response['message'] = 'Vui lòng chọn học sinh và lớp học.';
        }
        break;

    case 'confirm_payment':
        $payment_id = $_POST['payment_id'] ?? 0;
        if ($payment_id > 0) {
            $stmt = $conn->prepare("UPDATE tuition_payments SET status = 'confirmed' WHERE payment_id = ?");
            $stmt->bind_param("i", $payment_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Payment confirmed.'];
            } else {
                $response['message'] = 'Failed to confirm payment.';
            }
            $stmt->close();
        }
        break;

    case 'confirm_all_payments_for_teacher':
        $payment_ids_json = $_POST['payment_ids'] ?? '[]';
        $payment_ids = json_decode($payment_ids_json, true);

        if (!empty($payment_ids) && is_array($payment_ids)) {
            $conn->begin_transaction();
            try {
                $ids_placeholder = implode(',', array_fill(0, count($payment_ids), '?'));
                $stmt = $conn->prepare("UPDATE tuition_payments SET status = 'confirmed' WHERE payment_id IN ($ids_placeholder)");

                $types = str_repeat('i', count($payment_ids));
                $stmt->bind_param($types, ...$payment_ids);

                $stmt->execute();
                $conn->commit();
                $response = ['success' => true, 'message' => 'Đã xác nhận tất cả thanh toán cho giáo viên.'];
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Lỗi: ' . $e->getMessage();
            }
        } else {
            $response['message'] = 'Không có thanh toán nào để xác nhận.';
        }
        break;
}

header("Location: " . $redirect_url);
exit();
