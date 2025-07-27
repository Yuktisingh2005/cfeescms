<?php
session_start();

if (!isset($_SESSION['user_name'])) {
    header("Location: ../../login/login_engineer.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "cfees");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_name = $_SESSION['user_name'];


$sql = "SELECT first_name, middle_name, last_name FROM id_engineer WHERE user_name = '$user_name' LIMIT 1";
$result = $conn->query($sql);

$engineerName = '';
if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $engineerName = trim(
        (isset($row['first_name']) ? $row['first_name'] : '') . ' ' .
        (isset($row['middle_name']) ? $row['middle_name'] : '') . ' ' .
        (isset($row['last_name']) ? $row['last_name'] : '')
    );
}


$emp_desig_name = '';
$emp_group_name = '';

$sql1 = "SELECT desig_id, group_id FROM id_emp WHERE user_name = '" . mysqli_real_escape_string($conn, $user_name) . "' LIMIT 1";
$result1 = mysqli_query($conn, $sql1);
if ($result1 && mysqli_num_rows($result1) > 0) {
    $row1 = mysqli_fetch_assoc($result1);
    $desig_id = $row1['desig_id'];
    $group_id = $row1['group_id'];


    $sql2 = "SELECT desig_fullname FROM id_desig WHERE id = '" . mysqli_real_escape_string($conn, $desig_id) . "' LIMIT 1";
    $result2 = mysqli_query($conn, $sql2);
    if ($result2 && mysqli_num_rows($result2) > 0) {
        $row2 = mysqli_fetch_assoc($result2);
        $emp_desig_name = $row2['desig_fullname'];
    }

  
    $sql3 = "SELECT fullname FROM id_group WHERE id = '" . mysqli_real_escape_string($conn, $group_id) . "' LIMIT 1";
    $result3 = mysqli_query($conn, $sql3);
    if ($result3 && mysqli_num_rows($result3) > 0) {
        $row3 = mysqli_fetch_assoc($result3);
        $emp_group_name = $row3['fullname'];
    }
}


$complaints = $conn->query("
    SELECT 
        c.complaint_id,
        c.title,
        c.description,
        c.location,
        c.engineer_status,
        c.intercom,
        c.telecom_number,
        e.first_name, e.middle_name, e.last_name,
        d.desig_fullname,
        g.fullname AS group_name
    FROM complaints c
    LEFT JOIN id_emp e ON c.employee_user_name = e.user_name
    LEFT JOIN id_desig d ON e.id = d.id
    LEFT JOIN id_group g ON e.id = g.id
    WHERE c.assigned_engineer_username = '$user_name'
      AND (c.engineer_status IS NULL OR c.engineer_status = '' OR TRIM(c.engineer_status) = '' OR c.engineer_status IN ('Pending', 'Active'))
    ORDER BY c.created_at DESC
");



function getCount($conn, $query) {
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) return $row['count'];
    return 0;
}

$counts = array();
$counts['Total'] = getCount($conn, "SELECT COUNT(*) AS count FROM complaints WHERE assigned_engineer_username = '$user_name'");
$counts['Active'] = getCount($conn, "SELECT COUNT(*) AS count FROM complaints WHERE engineer_status = 'Active' AND assigned_engineer_username = '$user_name'");
$counts['Pending'] = getCount($conn, "SELECT COUNT(*) AS count FROM complaints WHERE (engineer_status IS NULL OR engineer_status = 'Pending') AND assigned_engineer_username = '$user_name'");
$counts['Resolved'] = getCount($conn, "SELECT COUNT(*) AS count FROM complaints WHERE engineer_status = 'Resolved' AND assigned_engineer_username = '$user_name'");

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Engineer Dashboard</title>
  <link rel="stylesheet" href="../../css/engineer/dashboard.css" />
    <link rel="stylesheet" href="../../css_icons/all.min.css" />

  <style>
       .status.pending { color: orange; font-weight: bold; }
    .status.active { color: blue; font-weight: bold; }
    .status.resolved { color: green; font-weight: bold; }
    .status.not-resolved { color: red; font-weight: bold; }
    .resolved-indicator,
    .not-resolved-indicator {
    background-color: green;
    color: white;
    border: none;
    padding: 9px 10px;
    border-radius: 8px;
    font-weight: bold;
    cursor: not-allowed;
    font-size: 15px;
}
.not-resolved-indicator{
  background-color:rgb(164, 21, 21);
}

.fa-solid, .fas {
    font-weight: 900;
    font-size: 25px;
    margin-bottom: 10px;
}

.action-box {
color: white;
    text-align: center;
    cursor: pointer;
    flex: 1 1 200px;
    background: linear-gradient(to right, rgb(0, 51, 102), rgb(0, 89, 179));
    padding: 30px 20px;
    border-radius: 15px;
    transition: transform 0.3s, box-shadow 0.3s;
    min-width: 180px;
    max-width: 180px;
    min-height: 46px;
}

  </style>
</head>
<body>
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
    <aside class="sidebar">
      <div class="profile-box">
        <div class="avatar-box">
        <img src="../../logos/default_user.jpg" alt="Profile Picture" />
        </div>
       <h3><?php echo htmlspecialchars(isset($engineerName) ? $engineerName : ''); ?></h3>


      </div>
      <nav class="sidebar-nav">
        <ul>
          <li><a href="MyProfile.php"><i class="fa fa-user"></i> My Profile</a></li>
         
           <li><a href="logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a></li>
        </ul>
      </nav>
    </aside>

    <main class="main-content">
      <div class="welcome-box">
       <h1 class="left-align">Welcome, <?php echo htmlspecialchars(isset($engineerName) ? $engineerName : ''); ?></h1>

       <div class="action-boxes">
  <div class="action-box" data-status="All">
    <i class="fas fa-layer-group"></i>
    <h3>Total Complaints: <?php echo $counts['Total']; ?></h3>
  </div>
  <div class="action-box" data-status="Active">
    <i class="fa-solid fa-bolt"></i>
    <h3>Active Complaints: <?php echo $counts['Active']; ?></h3>
  </div>
  <div class="action-box" data-status="Pending">
    <i class="fa-solid fa-hourglass-half"></i>
    <h3>Pending Complaints: <?php echo $counts['Pending']; ?></h3>
  </div>
  <div class="action-box" data-status="Resolved">
    <i class="fa-solid fa-circle-check"></i>
    <h3>Resolved Complaints: <?php echo $counts['Resolved']; ?></h3>
  </div>

</div>

<h2 class="center-align">Complaints</h2>

      <div class="table-container">
        <table class="complaint-table">
          <thead>
            <tr>
              <th>S.No.</th>
              <th>Complaint ID</th>
              <th>Employee Name</th>
              <th>Designation</th>
              <th>Group</th> 
              <th>Intercom</th>
              <th>Location</th>
              <th>Complaint Title</th>
              <th>Status</th>
              <th>View</th>
              <th>Resolve</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($complaints && $complaints->num_rows > 0):
          $index = 1;
            while ($row = $complaints->fetch_assoc()): ?>
            <tr>
  <td><?php echo $index++;?></td>
  <td>CMP<?php echo $row['complaint_id']; ?></td>
 <td>
  <?php
    echo htmlspecialchars(trim(
      (isset($row['first_name']) ? $row['first_name'] : '') . ' ' .
      (isset($row['middle_name']) ? $row['middle_name'] : '') . ' ' .
      (isset($row['last_name']) ? $row['last_name'] : '')
    ));
  ?>
</td>

<td><?php echo htmlspecialchars(isset($row['desig_fullname']) ? $row['desig_fullname'] : ''); ?></td> 
<td><?php echo htmlspecialchars(isset($row['group_name']) ? $row['group_name'] : ''); ?></td>


  <td><?php echo htmlspecialchars($row['intercom'] !== null ? $row['intercom'] : 'N/A'); ?></td>
 <td><?php echo htmlspecialchars(isset($row['location']) ? $row['location'] : ''); ?></td>

 <td><?php echo htmlspecialchars(isset($row['title']) ? $row['title'] : ''); ?></td>

 <td>
  <span class="status <?php echo strtolower(str_replace(' ', '-', isset($row['engineer_status']) && $row['engineer_status'] ? $row['engineer_status'] : 'pending')); ?>">
    <?php echo htmlspecialchars(isset($row['engineer_status']) && $row['engineer_status'] ? $row['engineer_status'] : 'Pending'); ?>
  </span>
</td>




  <td>
    <button class="review-btn"
      data-id="<?php echo $row['complaint_id']; ?>"
    data-title="<?php echo htmlspecialchars(isset($row['title']) ? $row['title'] : ''); ?>"
data-desc="<?php echo htmlspecialchars(isset($row['description']) ? $row['description'] : ''); ?>"
data-intercom="<?php echo htmlspecialchars(isset($row['intercom']) ? $row['intercom'] : ''); ?>"
data-location="<?php echo htmlspecialchars(isset($row['location']) ? $row['location'] : ''); ?>"


data-status="<?php echo htmlspecialchars(isset($row['engineer_status']) ? $row['engineer_status'] : '', ENT_QUOTES); ?>"

    >View</button>
  </td>
  <td>
    <?php if ($row['engineer_status'] === 'Resolved'): ?>
      <button class="resolved-indicator" disabled>Resolved</button>
    <?php elseif ($row['engineer_status'] === 'Not Resolved'): ?>
      <button class="not-resolved-indicator" disabled>Not Resolved</button>
    <?php else: ?>
      <button class="resolve-btn" data-id="<?php echo $row['complaint_id']; ?>">Resolve</button>
    <?php endif; ?>
  </td>
</tr>

          <?php endwhile; else: ?>
            <tr><td colspan="12">No complaints assigned.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
         

      </div>
    </main>
  </div>

  <footer class="main-footer">
    <p>Copyright © 2025, DRDO, Ministry of Defence, Government of India</p>
  </footer>


  <div class="modal" id="reviewModal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeReviewModal()">&times;</span>
      <h3>Complaint Review</h3>
      <p><strong>Complaint ID:</strong> <span id="r_id"></span></p>
      <p><strong>Title:</strong> <span id="r_title"></span></p>
      <p><strong>Description:</strong> <span id="r_desc"></span></p>
      <p><strong>Intercom:</strong> <span id="r_intercom"></span></p>
    

      <p><strong>Location:</strong> <span id="r_location"></span></p>
      <button id="markReviewBtn" onclick="markReviewed()">Mark as Viewed</button>
    </div>
  </div>


  <div class="modal" id="resolveModal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeResolveModal()">&times;</span>
      <h3>Resolve Complaint</h3>
      <p><strong>Complaint ID:</strong> <span id="res_id"></span></p>
   <button class="resolve-resolved" onclick="showFeedbackBox('resolved')">Resolved</button>
<button class="resolve-not-resolved" onclick="showFeedbackBox('not_resolved')">Not Resolved</button>

      <div id="feedbackSection" style="display:none;">
      <textarea id="f_reason" placeholder="Enter Remarks" rows="3" ></textarea>
      <button id="submit-feedback" onclick="submitResolution()">Submit</button>
      </div>
    </div>
  </div>
<script>
let currentComplaintId = '';
let resolutionType = '';

function closeReviewModal() {
  document.getElementById('reviewModal').style.display = 'none';
}

function closeResolveModal() {
  document.getElementById('resolveModal').style.display = 'none';
  document.getElementById('feedbackSection').style.display = 'none';
  document.getElementById('feedbackText').value = '';
  document.querySelector('.resolve-resolved').style.display = 'inline-block';
  document.querySelector('.resolve-not-resolved').style.display = 'inline-block';
}

function showFeedbackBox(type) {
  resolutionType = type;
  document.getElementById('feedbackSection').style.display = 'block';
  const resolvedBtn = document.querySelector('#resolveModal button.resolve-resolved');
  const notResolvedBtn = document.querySelector('#resolveModal button.resolve-not-resolved');

  if (type === 'resolved') {
    resolvedBtn.style.display = 'inline-block';
    notResolvedBtn.style.display = 'none';
  } else {
    resolvedBtn.style.display = 'none';
    notResolvedBtn.style.display = 'inline-block';
  }
}

document.querySelectorAll('.review-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    currentComplaintId = btn.dataset.id;
    document.getElementById('r_id').textContent = 'CMP' + btn.dataset.id;
    document.getElementById('r_title').textContent = btn.dataset.title;
    document.getElementById('r_desc').textContent = btn.dataset.desc;
    document.getElementById('r_intercom').textContent = btn.dataset.intercom;
    document.getElementById('r_location').textContent = btn.dataset.location;

    

   const status = (btn.dataset.status || '').trim().toLowerCase();

    const reviewBtn = document.getElementById('markReviewBtn');
    reviewBtn.style.display = (status === 'pending' || status === '') ? 'inline-block' : 'none';

    document.getElementById('reviewModal').style.display = 'block';
  });
});
document.querySelectorAll('.action-box').forEach(box => {
  box.style.cursor = 'pointer';
  box.addEventListener('click', function() {
    const status = this.getAttribute('data-status');
    let url = 'records.php';
    if (status && status !== 'All') {
      url += '?status=' + encodeURIComponent(status);
    }
    window.location.href = url;
  });
});

document.querySelectorAll('.resolve-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    currentComplaintId = btn.dataset.id;
    document.getElementById('res_id').textContent = 'CMP' + currentComplaintId;
    document.getElementById('f_reason').value = ''; 
    document.getElementById('resolveModal').style.display = 'block';
  });
});


function markReviewed() {
  fetch('mark_reviewed.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'complaint_id=' + currentComplaintId
  })
  .then(res => res.text())
  .then(data => {
    if (data === 'success') {
      const row = document.querySelector(`button.review-btn[data-id="${currentComplaintId}"]`).closest('tr');
  
      const statusCell = row.querySelector('td:nth-child(9) > span');
      statusCell.textContent = 'Active';
      statusCell.className = 'status active';
      
      document.getElementById('markReviewBtn').style.display = 'none';
     
    
      closeReviewModal();
    } else {
      alert('Error marking reviewed');
    }
  });
}

function submitResolution() {
  const feedback = document.getElementById('f_reason').value.trim();
  if (!feedback) {
    alert('Please enter feedback or reason.');
    return;
  }
  const formData = new URLSearchParams();
  formData.append('complaint_id', currentComplaintId);
  formData.append('resolution', resolutionType);
  formData.append('message', feedback);

  fetch('resolve_complaint.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
  .then(res => res.text())
  .then(data => {
    if (data === 'success') {
      const row = document.querySelector(`button.resolve-btn[data-id="${currentComplaintId}"]`).closest('tr');
      row.remove();
      closeResolveModal();
    } else {
      alert('Error submitting resolution.');
    }
  });
}

</script>

</body>
</html>
