<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli("localhost", "root", "", "cfees");
    if ($conn->connect_error) die("DB Error");

    $id = intval($_POST['complaint_id']);
    $engineer = $conn->real_escape_string($_POST['engineer']);

    $query = "UPDATE complaints SET assigned_engineer_username = '$engineer', status = 'Active', assignment_time = NOW() WHERE complaint_id = $id";
    if ($conn->query($query)) {
        echo "success";
    } else {
        echo "fail";
    }
}
?>
