<?php
session_start();

$conn = new mysqli("localhost", "root", "", "cfees");


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$username = $_POST['username'];
$password = $_POST['password'];


$sql = "SELECT * FROM id_admin WHERE username = '$username' AND password = '$password' LIMIT 1";
$result = $conn->query($sql);


if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();

    $_SESSION['admin_id'] = $row['id'];
    $_SESSION['admin_name'] = $row['name'];
    $_SESSION['admin_role'] = $row['user_type']; //role?

    $_SESSION['intercom'] = $row['intercom'];
    $_SESSION['username'] = $row['username'];  //user_name?


switch (trim($row['user_type'])) {
    case 'Super Admin':
        header("Location: superadmin/superadmin_dashboard.php");
        break;
    case 'IT Hardware':
        header("Location: IThardware/IThardware_dashboard.php");
        break;
    case 'Network':
        header("Location: network/network_dashboard.php");
        break;
    case 'Software':
        header("Location: software/software_dashboard.php");
        break;
    case 'Telecom':
        header("Location: telecom/telecom_dashboard.php");
        break;
    default:
        echo "<script>alert('Unknown admin role: " . addslashes($row['user_type']) . "'); window.history.back();</script>";
        exit();
}
} else {
    echo "<script>alert('Invalid username or password!'); window.history.back();</script>";
    exit();
}
?>
