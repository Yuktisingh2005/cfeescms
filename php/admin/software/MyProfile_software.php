<?php
session_start();


if (!isset($_SESSION['username'])) {
    header("Location: ../../login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "cfees");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$username = $_SESSION['username'];
$sql = "SELECT 
            id_admin.*, 
            id_emp.first_name, 
            id_emp.middle_name, 
            id_emp.last_name, 
            id_emp.gen, 
            id_emp.email_id, 
            id_emp.mobile_no, 
            id_emp.telephone_no, 
            id_emp.dob, 
            id_emp.cadre_id,
            id_emp.desig_id,
            id_desig.desig_fullname,
            id_emp.internal_desig_id,
            id_emp.group_id,
            id_group.name AS group_name,
            id_group.fullname AS group_fullname,
            id_emp.user_type,
            id_emp.status,
            id_emp.is_gazetted
        FROM id_emp
        JOIN id_admin ON id_admin.id = id_emp.id
        LEFT JOIN id_desig ON id_emp.id = id_desig.id
        LEFT JOIN id_group ON id_emp.group_id = id_group.id
        WHERE id_admin.username = '$username' 
        LIMIT 1";

$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo "<script>alert('Admin not found.'); window.location.href='../../login.html';</script>";
    exit();
}


$data = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Profile - DRDO CFEES</title>
  <link rel="stylesheet" href="../../../css/index/index.css" />
  <link rel="stylesheet" href="../../../css/admin/MyProfile.css" />
        <link rel="stylesheet" href="../../../css_icons/all.min.css" />

</head>
<body>
  <header class="main-header">
    <div class="header-inner">
      <div class="logo-box left">
        <img src="../../../logos/logo-left.png" alt="Left Logo" />
      </div>
      <div class="header-center">
        <h1 class="title-main">Defence Research and Development Organisation</h1>
        <p class="govt-line">Ministry of Defence, Government of India</p>
        <p class="sub-title">Centre for Fire, Explosive and Environment Safety (CFEES)</p>
      </div>
      <div class="logo-box right">
        <img src="../../../logos/logo-right.png" alt="Right Logo" />
      </div>
    </div>
  </header>

  <main class="main-content" style="background-color: transparent;">
      <a href="software_dashboard.php" class="back-top-btn">
  <i class="fa fa-arrow-left"></i> Back to Dashboard
</a>    <div class="whitebg">
      <h2>Credentials</h2>
      <form>
        <div class="row">
          <div class="group"><label><i class="fas fa-user"></i>First Name:</label><input type="text" value="<?php echo $data['first_name']; ?>" readonly /></div>
          <div class="group"><label><i class="fas fa-user"></i>Middle Name:</label><input type="text" value="<?php echo $data['middle_name']; ?>" readonly /></div>
          <div class="group"><label><i class="fas fa-user"></i>Last Name:</label><input type="text" value="<?php echo $data['last_name']; ?>" readonly /></div>
          <div class="group"><label><i class="fas fa-venus-mars"></i>Gender:</label><input type="text" value="<?php echo $data['gen']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-envelope"></i>Email:</label><input type="email" value="<?php echo $data['email_id']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-phone"></i>Mobile Number:</label><input type="tel" value="<?php echo $data['mobile_no']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-tty"></i>Telephone Number:</label><input type="tel" value="<?php echo $data['telephone_no']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-cake-candles"></i>Date of Birth:</label><input type="date" value="<?php echo $data['dob']; ?>" readonly /></div>
          <div class="group"><label><i class="fas fa-id-card"></i>Cadre ID:</label><input type="text" value="<?php echo $data['cadre_id']; ?>" readonly /></div>
          <div class="group"><label><i class="fas fa-id-card"></i>Designation ID:</label><input type="text" value="<?php echo $data['desig_id']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-address-card"></i>Designation:</label><input type="text" value="<?php echo $data['desig_fullname']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-id-badge"></i>Internal Designation ID:</label><input type="text" value="<?php echo $data['internal_desig_id']; ?>" readonly /></div>
          <div class="group"><label><i class="fas fa-id-card"></i>Group ID:</label><input type="text" value="<?php echo $data['group_id']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-id-card-clip"></i>Group:</label><input type="text" value="<?php echo $data['group_fullname']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-user-tie"></i>User Type:</label><input type="text" value="<?php echo $data['user_type']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-c"></i>Status:</label><input type="tel" value="<?php echo $data['status']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-newspaper"></i>Is Gazetted:</label><input type="tel" value="<?php echo $data['is_gazetted']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-plus"></i>Is Created:</label><input type="tel" value="<?php echo $data['is_created']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-trash"></i>Is Deleted:</label><input type="tel" value="<?php echo $data['is_deleted']; ?>" readonly /></div>
          <div class="group"><label><i class="fa-solid fa-address-book"></i>Username:</label><input type="text" value="<?php echo $data['username']; ?>" readonly /></div>
          <div class="group password-group"><label><i class="fa-solid fa-key"></i>Password:</label><input type="password" id="pass" value="<?php echo $data['password']; ?>" readonly /><i class="fa fa-eye toggle-password" onclick="togglePassword()"></i></div>  
        </div>
        </div>
      </form>
    </div>
  </main>

  <footer class="main-footer">
    <p>Copyright © 2025, DRDO, Ministry of Defence, Government of India</p>
  </footer>

  <script>
    function togglePassword() {
      const passField = document.getElementById("pass");
      const icon = document.querySelector(".toggle-password");
      if (passField.type === "password") {
        passField.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        passField.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    }
  </script>
</body>
</html>
