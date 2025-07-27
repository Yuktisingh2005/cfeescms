<?php
session_start();

if (!isset($_SESSION['user_name'])) {
    header("Location: ../../login/login_employee.html");
    exit();
}

$emp_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$emp_desig_name = '';



$conn = mysqli_connect("localhost", "root", "", "cfees");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}


$sql1 = "SELECT id FROM id_emp WHERE user_name = '" . mysqli_real_escape_string($conn, $user_name) . "' LIMIT 1";
$result1 = mysqli_query($conn, $sql1);
if ($result1 && mysqli_num_rows($result1) > 0) {
    $row1 = mysqli_fetch_assoc($result1);
    $emp_id = $row1['id'];

   
    $sql2 = "SELECT desig_fullname FROM id_desig WHERE id = '" . mysqli_real_escape_string($conn, $emp_id) . "' LIMIT 1";
    $result2 = mysqli_query($conn, $sql2);
    if ($result2 && mysqli_num_rows($result2) > 0) {
        $row2 = mysqli_fetch_assoc($result2);
        $emp_desig_name = $row2['desig_fullname'];
    }

    $sql2 = "SELECT fullname FROM id_group WHERE id = '" . mysqli_real_escape_string($conn, $emp_id) . "' LIMIT 1";
    $result2 = mysqli_query($conn, $sql2);
    if ($result2 && mysqli_num_rows($result2) > 0) {
        $row2 = mysqli_fetch_assoc($result2);
        $emp_group_name = $row2['fullname'];
    }
}



$complaint_success = false;
$complaint_id_generated = '';

function hasPendingFeedback($conn, $user_name) {
  $sql = "SELECT c.complaint_id FROM complaints c
          LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
          WHERE c.status = 'Resolved' AND c.employee_user_name = ? AND f.complaint_id IS NULL";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $user_name);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->num_rows > 0;
}

$blockRegistration = hasPendingFeedback($conn, $user_name);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_action'])) {
  $cid = intval($_POST['edit_complaint_id']);
  $stmt = $conn->prepare("SELECT title, description, type FROM complaints WHERE complaint_id = ?");
  $stmt->bind_param("i", $cid);
  $stmt->execute();
  $stmt->bind_result($title, $desc, $type);
  $stmt->fetch();
  $stmt->close();

  echo "<script>
    sessionStorage.setItem('editMode', 'true');
    sessionStorage.setItem('editComplaintId', '$cid');
    sessionStorage.setItem('editTitle', `" . addslashes($title) . "`);
    sessionStorage.setItem('editDesc', `" . addslashes($desc) . "`);
    sessionStorage.setItem('editType', '$type');
    window.location.href = '" . $_SERVER['PHP_SELF'] . "';
  </script>";
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_action']) && isset($_POST['delete_complaint_id'])) {
  $cid = intval($_POST['delete_complaint_id']);
  $stmt = $conn->prepare("DELETE FROM complaints WHERE complaint_id = ?");
  $stmt->bind_param("i", $cid);
  $stmt->execute();
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
  if (hasPendingFeedback($conn, $user_name)) {
    echo "<script>alert('Please submit feedback for all resolved complaints before registering a new one.'); window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
    exit();
  }

$title = $_POST['title'];
$description = $_POST['description'];
$type = $_POST['type'];
$location = $_POST['location'];
$intercom = $_POST['intercom']; 
$telecom_number = isset($_POST['telecom_number']) ? $_POST['telecom_number'] : null;




  if (isset($_POST['edit_complaint_id'])) {
    $cid = intval($_POST['edit_complaint_id']);
    $stmt = $conn->prepare("UPDATE complaints SET title=?, description=?, location=?, type=?, updated_at=NOW() WHERE complaint_id=?");
    $stmt->bind_param("ssssi", $title, $description, $location, $type, $cid);
    if ($stmt->execute()) {
      $complaint_id_generated = $cid;
      $complaint_success = true;
    }
    $stmt->close();
  } else {


$stmt = $conn->prepare("INSERT INTO complaints (title, description, location, intercom, type, employee_user_name, telecom_number, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
$stmt->bind_param("sssssss", $title, $description, $location, $intercom, $type, $user_name, $telecom_number);


    if ($stmt->execute()) {
      $complaint_id_generated = $stmt->insert_id;
      $complaint_success = true;
    }
    $stmt->close();
  }
}

function getComplaints($conn, $status, $user_name) {
  $sql = "SELECT * FROM complaints WHERE status = ? AND employee_user_name = ? ORDER BY created_at DESC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $status, $user_name);
  $stmt->execute();
  return $stmt->get_result();
}

function getEngineerName($conn, $engineer_username) {
  if (!$engineer_username) return "-";
  $stmt = $conn->prepare("SELECT first_name, last_name FROM id_engineer WHERE user_name = ?");
  $stmt->bind_param("s", $engineer_username);
  $stmt->execute();
  $stmt->bind_result($fname, $lname);
  $stmt->fetch();
  $stmt->close();
  return $fname . ' ' . $lname;
}

$active_complaints = getComplaints($conn, 'Active', $user_name);
$pending_complaints = getComplaints($conn, 'Pending', $user_name);
$pending_feedback = getComplaints($conn, 'Resolved', $user_name);
$has_pending_feedback = hasPendingFeedback($conn, $user_name);

function getSolution($conn, $complaint_id) {
    $stmt = $conn->prepare("SELECT solution FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $stmt->bind_result($solution);
    $stmt->fetch();
    $stmt->close();
    return isset($solution) ? $solution : "-";

}

function getAdminFeedback($conn, $complaint_id) {
  $stmt = $conn->prepare("SELECT admin_feedback FROM complaints WHERE complaint_id = ?");
  $stmt->bind_param("i", $complaint_id);
  $stmt->execute();
  $stmt->bind_result($admin_feedback);
  $stmt->fetch();
  $stmt->close();
  return isset($admin_feedback) ? $admin_feedback : "-";

}

$feedback_sql = "
  SELECT c.*, 
         IF(f.complaint_id IS NULL, 0, 1) AS feedback_exists 
  FROM complaints c
  LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
  WHERE c.status = 'Resolved' AND c.employee_user_name = ?
  ORDER BY c.created_at DESC
  ";

  $stmt = $conn->prepare($feedback_sql);
  $stmt->bind_param("s", $user_name);
  $stmt->execute();
  $feedback_results = $stmt->get_result();
  $stmt->close();
  ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Dashboard - DRDO CFEES</title>
  <link rel="stylesheet" href="../../css/employee/dashboard.css">
  <link rel="stylesheet" href="../../css_icons/all.min.css" />
  <style>
    .timeline { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }
    .timeline-item { position: relative; padding-left: 20px; }
    .timeline-item::before {
      content: "";
      position: absolute;
      width: 10px;
      height: 10px;
      background: #007bff;
      border-radius: 50%;
      left: 0;
      top: 3px;
    }
    .modal-tag {
  display: inline-block;
  margin-bottom: 10px;
}
.close-btn {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 20px;
  cursor: pointer;
}
.feedback-close {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 22px;
  color: #333;
  cursor: pointer;
  z-index: 1001;
}
#feedbackModal .modal-content {
  position: relative;
}


  </style>
  <script>
  if (window.history && window.history.pushState) {
    window.history.pushState('', null, './');
    window.onpopstate = function () {
      window.location.href = "../../login/login_employee.html";
    };
  }
</script>
</head>
<body>

<!-- Header -->
<header class="main-header">
  <div class="header-inner">
    <div class="logo-box left">
      <img src="../../logos/logo-left.png" alt="Left Logo" />
    </div>
    <div class="header-center">
      <h1 class="hindi-bold">अग्नि, पर्यावरण तथा विस्फोटक सुरक्षा केंद्र (CFEES)</h1>
      <p class="hindi-regular">रक्षा मन्त्रालय, भारत सरकार</p>
      <h2 class="eng-bold">Centre for Fire, Explosive and Environment Safety (CFEES)</h2>
      <p class="eng-regular">Ministry of Defence, Government of India</p>
    </div>
    <div class="logo-box right">
      <img src="../../logos/logo-right.png" alt="Right Logo" />
    </div>
  </div>
</header>

<div class="dashboard">
<!-- Sidebar -->
  <aside class="sidebar">
    <div class="profile-box">
      <div class="avatar-box">
        <img src="../../logos/default_user.jpg" alt="Profile Picture" />
      </div>
      <h3><?php echo htmlspecialchars($emp_name); ?></h3>
    
    </div>
    <nav class="sidebar-nav">
      <ul>
        <li><a href="MyProfile.php"><i class="fa fa-user"></i> My Profile </a></li>
        <li><a href="records.php"><i class="fa fa-folder-open"></i> Complaint Records</a></li>
        <li><a href="logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a></li>

      </ul>
    </nav>
  </aside>

<!-- Welcome Box -->
  <main class="main-content">
    <div class="welcome-box">
      <h2>Welcome, <span class="username"><?php echo htmlspecialchars($emp_name); ?></span></h2>
    </div>

    <div class="action-boxes">
      <div class="action-box" onclick="document.getElementById('complaint-form').scrollIntoView({ behavior: 'smooth' })">
      <i class="fa-solid fa-pen-to-square"></i>   
      <h3>Register a Complaint</h3>
      </div>
      <div class="action-box" onclick="document.getElementById('active-section').scrollIntoView({ behavior: 'smooth' })">
      <i class="fa-solid fa-bolt"></i>  
      <h3>Active Complaints</h3>
      </div>
      <div class="action-box" onclick="document.getElementById('pending-section').scrollIntoView({ behavior: 'smooth' })">
      <i class="fa-solid fa-hourglass-half"></i>  
      <h3>Pending Complaints</h3>
      </div>
      <div class="action-box" onclick="document.getElementById('resolved-section').scrollIntoView({ behavior: 'smooth' })">
      <i class="fa-solid fa-circle-check"></i>  
      <h3>Pending Feedback</h3>
      </div>
    </div>

<!-- Complaint Registration -->
    <section id="complaint-form" class="complaint-form">
  <h2 class="form-title">Register Your Complaint</h2>

  <div id="complaint-success" style="display: <?php echo $complaint_success ? 'block' : 'none'; ?>">
    <h3>Your complaint has been successfully registered.</h3>
    <p>Your complaint ID is <strong>CMP<?php echo $complaint_id_generated; ?></strong>.</p>
  </div>

 <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="register-form" style="display: <?php echo $complaint_success ? 'none' : 'block'; ?>">

    <input type="hidden" name="submit_complaint" value="1">
        <div>
          <label><i class="fa fa-user"></i> Name</label>
          <input type="text" value="<?php echo htmlspecialchars($emp_name); ?>" disabled>
        </div>
        <div>
          <div>
  <label><i class="fa fa-id-badge"></i> Designation</label>
  <input type="text" value="<?php echo htmlspecialchars($emp_desig_name); ?>" disabled>
</div>
<div>
  <label><i class="fa fa-id-badge"></i> Group</label>
  <input type="text" value="<?php echo htmlspecialchars($emp_group_name); ?>" disabled>
</div>

        <div>
  <label><i class="fa fa-phone"></i> Intercom</label>
  <input type="text" name="intercom" placeholder="Enter Intercom Number" required>
</div>

        <!-- Complaint Type Select -->
<div>
  <label><i class="fa fa-layer-group"></i> Select Complaint Type</label>
  <select name="type" id="complaint-type" required>
    <option disabled selected>Select Complaint type</option>
    <option value="Hardware">IT Hardware</option>
    <option value="Software">Software</option>
    <option value="Network">Network</option>
    <option value="Telecom">Telecom</option>
  </select>
</div>

<!-- Telecom Number Field -->
<div id="reportTelecomDiv" style="display: none; margin-top: 10px;">
  <label for="telecom_number">Report Telecom</label>
  <input type="text" name="telecom_number" id="telecom_number" placeholder="Enter your telecom number" />
</div>

        <div>
          <label><i class="fa fa-clipboard"></i> Complaint Title</label>
          <input type="text" name="title" placeholder="Enter Complaint Title" required>
        </div>
        <div>
          <label><i class="fa fa-align-left"></i> Description</label>
          <input type="text" name="description" placeholder="Describe your Complaint" required>
        </div>
        <div>
  <label><i class="fa fa-map-marker-alt"></i> Location</label>
  <input type="text" name="location" placeholder="Enter Location" required>
</div>
        <button type="submit" class="submit-btn">Submit Complaint</button>
      </form>

    </section>
  <script>
  window.addEventListener("load", function () {
    const successBox = document.getElementById("complaint-success");
    const form = document.getElementById("register-form");

    if (successBox && successBox.style.display === "block") {
      setTimeout(() => {
        successBox.style.display = "none";
        form.style.display = "block";
        form.scrollIntoView({ behavior: "smooth" });
      }, 3000);
    }
  });
</script>

<!-- Active Complaints -->
<section id="active-section" class="complaint-records">
  <h2>Active Complaints</h2>
  <table>
    <thead>
      <tr><th>Complaint ID</th><th>Complaint Title</th><th>Registered Date & Time</th><th>Status</th><th>Details</th></tr>
    </thead>
    <tbody>
        <?php if ($active_complaints->num_rows === 0): ?>
  <tr><td colspan="5" style="text-align:center;">No active complaints</td></tr>
<?php endif; ?>

      <?php while ($row = $active_complaints->fetch_assoc()): ?>
        <tr>
          <td>CMP<?php echo $row['complaint_id']; ?></td>
          <td><?php echo htmlspecialchars($row['title']); ?></td>
          <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
          <td><span class="status active">Active - Engineer Working</span></td>
          <td>
            <button class="view-btn" onclick="showDetails(
              'CMP<?php echo $row['complaint_id']; ?>',
              '<?php echo addslashes($row['title']); ?>',
              '<?php echo addslashes($row['description']); ?>',
              '<?php echo $row['type']; ?>',
              'Active',
              '<?php echo getEngineerName($conn, $row['assigned_engineer_username']); ?>',
              '<?php echo $row['created_at']; ?>',
              '<?php echo $row['updated_at']; ?>',
              '<?php echo addslashes(isset($row["location"]) ? $row["location"] : ""); ?>'

              )">View</button>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</section>

<!-- Pending Complaints Section -->
<section id="pending-section" class="complaint-records">
  <h2>Pending Complaints</h2>
  <table>
    <thead>
      <tr><th>Complaint ID</th><th>Complaint Title</th><th>Registered Date & Time</th><th>Status</th><th>Details</th><th>Edit</th><th>Delete</th></tr>
    </thead>
    <tbody>
  <?php if ($pending_complaints->num_rows === 0): ?>
    <tr><td colspan="7" style="text-align:center;">No pending complaints</td></tr>
  <?php endif; ?>

  <?php while ($row = $pending_complaints->fetch_assoc()): ?>
    <tr>
      <td>CMP<?php echo $row['complaint_id']; ?></td>
      <td><?php echo htmlspecialchars($row['title']); ?></td>
      <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
      <td><span class="status pending">Awaiting Admin's Review</span></td>

      <td>
        <button class="view-btn" onclick="showDetails(
          'CMP<?php echo $row['complaint_id']; ?>',
          '<?php echo addslashes($row['title']); ?>',
          '<?php echo addslashes($row['description']); ?>',
          '<?php echo $row['type']; ?>',
          'Pending - Awaiting Admin to review the complaint',
          'Engineer not assigned yet',
          '<?php echo $row['created_at']; ?>',
          '<?php echo $row['updated_at']; ?>',
          '<?php echo addslashes(isset($row['location']) ? $row['location'] : ''); ?>',
          true,
          <?php echo $row['complaint_id']; ?>
        )">View</button>
      </td>

      <!-- Edit Button -->
      <td>
        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
          <input type="hidden" name="edit_complaint_id" value="<?php echo $row['complaint_id']; ?>">
          <input type="hidden" name="edit_action" value="1">
          <button type="submit" class="edit-btn">Edit</button>
        </form>
      </td>

      <!-- Delete Button -->
      <td>
        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" onsubmit="return confirm('Are you sure you want to delete this complaint?');">
          <input type="hidden" name="delete_complaint_id" value="<?php echo $row['complaint_id']; ?>">
          <input type="hidden" name="delete_action" value="1">
          <button type="submit" class="delete-btn">Delete</button>
        </form>
      </td>

    </tr>
  <?php endwhile; ?>
</tbody>

  </table>
</section>

<!-- Pending Feedback -->
<section id="resolved-section" class="complaint-records">
  <h2>Pending Feedback</h2>
  <table>
    <thead>
      <tr><th>Complaint ID</th><th>Complaint Title</th><th>Registered Date & Time</th><th>Status</th><th>Details</th></tr>
    </thead>
    <tbody>
      <?php
      if ($feedback_results->num_rows === 0): ?>
        <tr><td colspan="5" style="text-align:center;">No pending feedbacks</td></tr>
      <?php endif;
      while ($row = $feedback_results->fetch_assoc()):
        if (!$row['feedback_exists']):
      ?>
        <tr>
          <td>CMP<?php echo $row['complaint_id']; ?></td>
          <td><?php echo htmlspecialchars($row['title']); ?></td>
          <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
          <td><span class="status resolved">Resolved (Admin Approved)</span></td>

          <td>
            <button class="view-btn" onclick="showFeedbackPopup(
              <?php echo $row['complaint_id']; ?>,
              'CMP<?php echo $row['complaint_id']; ?>',
              '<?php echo addslashes($row['title']); ?>',
              '<?php echo addslashes($row['description']); ?>',
              '<?php echo $row['type']; ?>',
              '<?php echo addslashes($row['location']); ?>',
              '<?php echo getEngineerName($conn, $row['assigned_engineer_username']); ?>',
              '<?php echo addslashes(getSolution($conn, $row['complaint_id'])); ?>',
              '<?php echo isset($row["status"]) ? $row["status"] : "-"; ?>'

            )">View</button>
          </td>
        </tr>
      <?php endif; endwhile; ?>
    </tbody>
  </table>
</section>

<!-- Active-Pending View -->
<div class="modal" id="infoModal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <span class="modal-tag" id="modal-tag" style="display: none;"></span>
    <h3>Complaint Details</h3>

    <p><strong>Complaint ID:</strong> <span id="modal-id"></span></p>
    <p><strong>Title:</strong> <span id="modal-title"></span></p>
    <p><strong>Description:</strong> <span id="modal-desc"></span></p>
   

    <p><strong>Complaint Type:</strong> <span id="modal-type"></span></p>
    <p><strong>Status:</strong> <span id="modal-status"></span></p>
    <p><strong>Location:</strong> <span id="modal-location"></span></p>
    <p><strong>Assigned Engineer:</strong> <span id="modal-engg"></span></p>
    
    <p><strong>Registered at:</strong> <span id="modal-created"></span></p>
    <p><strong>Last Updated:</strong> <span id="modal-updated"></span> <em id="engg-working-msg" style="color: green;"></em></p>


       <!-- <div id="edit-delete-btns" style="display: none;">
  <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <input type="hidden" name="edit_complaint_id" id="edit-id">
    <input type="hidden" name="edit_action" value="1">
    <button type="submit" class="submit-btn">Edit</button>
  </form>
  <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <input type="hidden" name="delete_complaint_id" id="delete-id">
    <input type="hidden" name="delete_action" value="1">
    <button type="submit" class="submit-btn">Delete</button>
  </form>
</div> -->
  </div>
</div>




<!-- Feedback View -->
    </div>
  </div>
</div>
<div class="modal" id="feedbackModal">
  <div class="modal-content">
    <span class="close-btn feedback-close" onclick="document.getElementById('feedbackModal').style.display='none'">&times;</span>

    <h3>Complaint Details</h3>
    <p><strong>Complaint ID:</strong> <span id="f_id"></span></p>
    <p><strong>Title:</strong> <span id="f_title"></span></p>
    <p><strong>Description:</strong> <span id="f_desc"></span></p>
    <p><strong>Complaint Type:</strong> <span id="f_type"></span></p>
    <p><strong>Location:</strong> <span id="f_location"></span></p>
    <p><strong>Assigned Engineer:</strong> <span id="f_engineer"></span></p>
    <p><strong>Engineer Feedback:</strong> <span id="f_solution"></span></p>
    <p><strong>Admin's Feedback:</strong> <span id="f_admin"></span></p>

    <div>
      <label><strong>Rate your experience:</strong></label><br>
      <div id="star-rating">
  <input type="radio" name="rating" id="star5" value="5"><label for="star5">&#9733;</label>
  <input type="radio" name="rating" id="star4" value="4"><label for="star4">&#9733;</label>
  <input type="radio" name="rating" id="star3" value="3"><label for="star3">&#9733;</label>
  <input type="radio" name="rating" id="star2" value="2"><label for="star2">&#9733;</label>
  <input type="radio" name="rating" id="star1" value="1"><label for="star1">&#9733;</label>
    </div>
    </div>
    <div class="feedback-review">
      <label><strong>Remarks:</strong></label><br>
      <textarea id="f_reason" placeholder="Enter reason for your rating..." rows="3" style="width: 288px; height: 37px; resize:none;" ></textarea>
    </div>
    <button id="submit-feedback" onclick="submitFeedback()">Submit Feedback</button>
  </div>
</div>

<script>
function showDetails(id, title, desc, type, status, engg, created, updated, location) {
  document.getElementById('modal-id').textContent = id;
  document.getElementById('modal-title').textContent = title;
  document.getElementById('modal-desc').textContent = desc;
  document.getElementById('modal-type').textContent = type;
 

  let statusLabel = "";
  if (status === 'Pending') statusLabel = "Pending - Awaiting Admin's Review";
  else if (status === 'Active') statusLabel = "Active - Engineer Working";
  else if (status === 'Review Pending') statusLabel = "Review Pending - Awaiting Admin Feedback Decision";
  else if (status === 'Resolved') statusLabel = "Resolved (Admin Approved)";
  else statusLabel = status;

  document.getElementById('modal-status').textContent = statusLabel;
  document.getElementById('modal-engg').textContent = engg;
  document.getElementById('modal-location').textContent = location;
  document.getElementById('modal-created').textContent = formatDate(created);
  document.getElementById('modal-updated').textContent = formatDate(updated);

  const enggWorkingMsg = document.getElementById('engg-working-msg');
  if (status === 'Active') {
    enggWorkingMsg.textContent = 'Engineer is working...';
  } else {
    enggWorkingMsg.textContent = '';
  }

  document.getElementById('modal-tag').textContent = status.toUpperCase();
  document.getElementById('modal-tag').className = 'modal-tag ' + status.toLowerCase();

  document.getElementById('infoModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('infoModal').style.display = 'none';
}

function formatDate(datetime) {
  const dt = new Date(datetime);
  return dt.toLocaleString('en-GB', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}
</script>
<script>
window.addEventListener("load", () => {
  if (sessionStorage.getItem("editMode") === "true") {
    const form = document.querySelector("#register-form");
    const type = sessionStorage.getItem("editType");
    const title = sessionStorage.getItem("editTitle");
    const desc = sessionStorage.getItem("editDesc");
    const id = sessionStorage.getItem("editComplaintId");

    form.querySelector("select[name='type']").value = type;
    form.querySelector("input[name='title']").value = title;
    form.querySelector("input[name='description']").value = desc;


    const hidden = document.createElement("input");
    hidden.type = "hidden";
    hidden.name = "edit_complaint_id";
    hidden.value = id;
    form.appendChild(hidden);

    sessionStorage.clear(); 
    document.getElementById("complaint-form").scrollIntoView({ behavior: "smooth" });
  }
});
</script>
<script>
function showFeedbackPopup(cid, id, title, desc, type, location, engineer, solution, adminFeedback) {
  document.getElementById('f_id').textContent = id;
  document.getElementById('f_title').textContent = title;
  document.getElementById('f_desc').textContent = desc;
  document.getElementById('f_type').textContent = type;
  document.getElementById('f_location').textContent = location;
  document.getElementById('f_engineer').textContent = engineer;
  document.getElementById('f_solution').textContent = solution;
  document.getElementById('f_admin').textContent = adminFeedback;
  document.getElementById('feedbackModal').dataset.cid = cid;
  document.getElementById('feedbackModal').style.display = 'flex';
}

function submitFeedback() {
  const rating = document.querySelector('#star-rating input[name="rating"]:checked');
  const reason = document.getElementById('f_reason').value.trim();
  const cid = document.getElementById('feedbackModal').dataset.cid;

  if (!rating || reason === '') {
    alert("Please select a rating and enter a reason.");
    return;
  }

  const formData = new URLSearchParams();
  formData.append('complaint_id', cid);
  formData.append('rating', rating.value);
  formData.append('reason', reason);

  fetch('submit_feedback.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
  .then(res => res.text())
  .then(data => {
    if (data === 'success') {
      alert('Thank you for your feedback!');
      location.reload();
    } else {
      alert('Error submitting feedback.');
    }
  });
}
</script>


<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('complaint-type');
    const telecomField = document.getElementById('reportTelecomDiv');
    const telecomInput = document.getElementById('telecom_number'); 

    function toggleTelecomField() {
      if (typeSelect.value === 'Telecom') {
        telecomField.style.display = 'block';
        telecomInput.required = true;
      } else {
        telecomField.style.display = 'none';
        telecomInput.value = '';
        telecomInput.required = false;
      }
    }

    typeSelect.addEventListener('change', toggleTelecomField);
    toggleTelecomField();
  });
</script>





<script>
window.addEventListener("DOMContentLoaded", function() {
  const form = document.getElementById("register-form");
  const pending = <?php echo $has_pending_feedback ? 'true' : 'false'; ?>;
  if (pending) {
    form.style.display = 'none';
    const msg = document.createElement('div');
    msg.innerHTML = '<h3 style="color:red;text-align:center">Please submit feedback for all resolved complaints before registering a new one.</h3>';
    form.parentNode.insertBefore(msg, form);
  }
});




  <footer class="main-footer">
    <p>Copyright © 2025, DRDO, Ministry of Defence, Government of India</p>
  </footer>
</body>
</html>