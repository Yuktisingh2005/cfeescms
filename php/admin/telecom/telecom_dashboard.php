<?php
session_start();


if (!isset($_SESSION['username']) || !isset($_SESSION['admin_role'])) {
    header("Location: ../../../login/login_admin.html");
    exit();
}


if (trim($_SESSION['admin_role']) !== 'Telecom') {
    session_unset();
    session_destroy();
    header("Location: ../login.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "cfees");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_name = $_SESSION['username'];

$sql = "SELECT name, username FROM id_admin WHERE username = '$user_name' LIMIT 1";
$result = $conn->query($sql);

$admin_name = "Unknown Admin";
$admin_code = "N/A";

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $name = isset($row['name']) ? $row['name'] : '';
    $admin_name = trim("$name");
    $admin_code = $row['username'];
}



$count_query = "SELECT status, COUNT(*) AS count FROM complaints WHERE type = 'Telecom' GROUP BY status";
$result = $conn->query($count_query);

$counts = [
    'Total' => 0,
    'Pending' => 0,
    'Active' => 0,
    'Review Pending' => 0,
    'Resolved' => 0
];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        $counts[$status] = (int)$row['count'];
        $counts['Total'] += (int)$row['count'];
    }
}

$complaints = $conn->query("
  SELECT c.complaint_id, c.title, c.description, c.status, c.created_at,
         c.assigned_engineer_username, c.location, c.telecom_number,
         e.first_name, e.last_name, c.intercom, d.desig_fullname, g.fullname
  FROM complaints c 
  JOIN id_emp e ON c.employee_user_name = e.user_name
  LEFT JOIN id_desig d ON e.id = d.id
  LEFT JOIN id_group g ON e.id = g.id
  WHERE c.type = 'Telecom' AND c.status IN ('Pending', 'Active', 'Review Pending')
  ORDER BY c.created_at DESC
");

if (!$complaints) {
    die("Query failed: " . $conn->error);
}
//

$engineers = $conn->query("SELECT user_name, CONCAT(first_name, ' ', last_name) AS full_name FROM id_engineer");
$engineer_list = [];
while ($eng = $engineers->fetch_assoc()) {
    $engineer_list[] = [
        'username' => $eng['user_name'],
        'full_name' => $eng['full_name']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Telecom Dashboard</title>
  <link rel="stylesheet" href="../../../css/index/index.css" />
  <link rel="stylesheet" href="../../../css/admin/Telecom_dashboard.css" />
      <link rel="stylesheet" href="../../../css_icons/all.min.css" />
<style>
.fa-solid, .fas {
    font-weight: 900;
    font-size: 25px;
    margin-bottom: 10px;
}
.fa-user{
  font-size:16px;
}
</style>
</head>

<body>
<!-- Header -->
  <header class="main-header">
    <div class="header-inner">
      <div class="logo-box left">
        <img src="../../../logos/logo-left.png" alt="Left Logo" />
      </div>
      <div class="header-center">
      <h1 class="hindi-bold">अग्नि, पर्यावरण तथा विस्फोटक सुरक्षा केंद्र (CFEES)</h1>
      <p class="hindi-regular">रक्षा मन्त्रालय, भारत सरकार</p>
      <h2 class="eng-bold">Centre for Fire, Explosive and Environment Safety (CFEES)</h2>
      <p class="eng-regular">Ministry of Defence, Government of India</p>
    </div>
      <div class="logo-box right">
        <img src="../../../logos/logo-right.png" alt="Right Logo" />
      </div>
    </div>
  </header>


  <div class="page-layout">
<!-- Sidebar -->
    <aside class="sidebar">
      <div class="profile-box">
        <div class="avatar-box">
        <img src="../../../logos/default_user.jpg" alt="Profile Picture" />
        </div>
        <h3><?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></h3>
      </div>
      <nav class="sidebar-nav">
        <ul>
          <li><a href="MyProfile_telecom.php"><i class="fa fa-user"></i> User Profile</a></li>
          <!-- <li><a href="./records.php"><i class="fa fa-folder-open"></i> Complaints Record</a>
          </li> -->
          <li><a href="./logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a></li>
        </ul>
      </nav>
    </aside>

<!-- Table -->
    <main class="main-content">
      <h2 class="welcome">Welcome, <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></h2>
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
  <div class="action-box" data-status="Review Pending">
    <i class="fa-solid fa-star-half-stroke"></i>
    <h3>Review Pending: <?php echo $counts['Review Pending']; ?></h3>
  </div>
</div>

      <h3 class="section-title">Telecom Complaints</h3>
      <div class="table-container">
  <table>
  <thead>
    <tr>
      <th>S.No.</th>
      <th>Complaint ID</th>
      <th>Employee Name</th>
      <th>Designation</th>
      <th>Group</th>
      <th>Intercom</th>
      <th>Telecom</th> 
      <th>Complaint Title</th>
      <th>Status</th>
      <th>Review</th>
      <th>Assigned Engineer</th>
    </tr>
  </thead>
  <tbody>
<?php if ($complaints && $complaints->num_rows > 0): ?>
  <?php $index = 1; ?>
  <?php while ($row = $complaints->fetch_assoc()): ?>
    <tr>
      <td><?php echo $index++; ?></td>
      <td>CMP<?php echo $row['complaint_id']; ?></td>
      <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
      <td><?php echo htmlspecialchars($row['desig_fullname'], ENT_QUOTES); ?></td>
      <td><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES); ?></td>
      <td><?php echo htmlspecialchars($row['intercom'], ENT_QUOTES); ?></td>
      <td><?php echo htmlspecialchars($row['telecom_number'], ENT_QUOTES); ?></td> 
      <td><?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?></td>
      <td><?php echo htmlspecialchars($row['status'], ENT_QUOTES); ?></td>
      <td>
      <?php if ($row['status'] === 'Review Pending'): ?>
        <button class="feedback" onclick="openFeedbackModal('<?php echo $row['complaint_id']; ?>')">Review Feedback</button>
      <?php else: ?>
       
<button class="review" onclick="openReviewModal(
  '<?php echo $row['complaint_id']; ?>',
  '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES); ?>',
  '<?php echo $row['created_at']; ?>',
  '<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>',
  '<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>',
  '<?php echo htmlspecialchars($row['location'], ENT_QUOTES); ?>',
  '<?php echo htmlspecialchars($row['intercom'], ENT_QUOTES); ?>',
  '<?php echo htmlspecialchars($row['telecom_number'], ENT_QUOTES); ?>'
)">Review</button>
      <?php endif; ?>
      </td>
      <td>
      <?php if ($row['assigned_engineer_username']): ?>
<?php
$assignedUsername = $row['assigned_engineer_username'];
$assignedFullName = $assignedUsername;

foreach ($engineer_list as $eng) {
    if ($eng['username'] === $assignedUsername) {
        $assignedFullName = $eng['full_name'];
        break;
    }
}
?>
<span><?php echo htmlspecialchars($assignedFullName); ?></span>
      <?php else: ?>
        <button class="assign" onclick="openAssignModal('<?php echo $row['complaint_id']; ?>')" data-id="<?php echo $row['complaint_id']; ?>">Assign</button>
      <?php endif; ?>
      </td>
    </tr>
  <?php endwhile; ?>
<?php else: ?>
  <tr><td colspan="10">No complaints found.</td></tr>
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
      <h2>Complaint Details</h2>
      <p><strong>Complaint ID:</strong> <span id="r_id"></span></p>
      <p><strong>Employee Name:</strong> <span id="r_name"></span></p>
      <p><strong>Registered At:</strong> <span id="r_time"></span></p>
      <p><strong>Title:</strong> <span id="r_title"></span></p>
      <p><strong>Description:</strong> <span id="r_desc"></span></p>
      <p><strong>Telecom:</strong> <span id="r_telecom"></span></p>
      <p><strong>Location:</strong> <span id="r_loc"></span></p>
      <p><strong>Intercom:</strong> <span id="r_intercom"></span></p>
     
<div id="engineerAssignSection">
  <p id="assignMessage">Assign the Engineer</p>
  <p><strong>Assigned Engineer:</strong> <span id="r_engineer"></span></p>
  <button id="changeEngineerBtn" onclick="openAssignModal(currentComplaintId)">Change Engineer</button>
</div>
    </div>
  </div>

  
<div class="modal" id="feedbackReviewModal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeFeedbackModal()">&times;</span>
    <h2>Engineer Feedback</h2>
    <p><strong>Complaint ID:</strong> <span id="f_id"></span></p>
    <p><strong>Engineer Feedback:</strong> <span id="f_solution"></span></p>
    <button class="resolved" onclick="acceptResolution()">Resolved</button>
    <button class="not-resolved" onclick="markNotResolved()">Not Resolved</button>
  </div>
</div>

  <div class="modal" id="assignModal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeAssignModal()">&times;</span>
    <h2>Assign Engineer</h2>
    <p>Complaint ID: <span id="assignComplaintIdText"></span></p>

   <input type="text" id="engineerInput" onclick="filterEngineers()" oninput="filterEngineers()" placeholder="Type engineer name..." autocomplete="off"/>

    <div id="engineerDropdown" class="dropdown-list"></div>

    <button onclick="assignEngineer()">Assign Engineer</button>
  </div>
</div>


<script>
function openReviewModal(id, name, time, title, desc, location, intercom, telecom) {
  currentComplaintId = id;
  document.getElementById('r_id').innerText = 'CMP' + id;
  document.getElementById('r_name').innerText = name;
  document.getElementById('r_time').innerText = time;
  document.getElementById('r_title').innerText = title;
  document.getElementById('r_desc').innerText = desc;
  document.getElementById('r_loc').innerText = location;
  document.getElementById('r_intercom').innerText = intercom;
  document.getElementById('r_telecom').innerText = telecom || '—';

  // Engineer assignment logic (if needed)
  const engineer = assignedEngineerMap[id];
  if (!engineer || engineer === "") {
    document.getElementById('assignMessage').style.display = 'block';
    document.getElementById('r_engineer').style.display = 'none';
    document.getElementById('changeEngineerBtn').style.display = 'none';
  } else {
    document.getElementById('assignMessage').style.display = 'none';
    document.getElementById('r_engineer').style.display = 'inline';
    document.getElementById('r_engineer').innerText = engineer;
    document.getElementById('changeEngineerBtn').style.display = 'inline';
  }

  document.getElementById('reviewModal').style.display = 'block';
}

function closeReviewModal() {
  document.getElementById('reviewModal').style.display = 'none';
}

function openAssignModal(id) {
  document.getElementById('assignComplaintId').innerText = 'CMP' + id;
  document.getElementById('assignModal').style.display = 'block';
}

function closeAssignModal() {
  document.getElementById('assignModal').style.display = 'none';
}
</script>
<script>
let allEngineers = <?php echo json_encode($engineer_list); ?>;
let currentComplaintId = null;
let assignedEngineerMap = {};

<?php

if ($complaints && $complaints->num_rows > 0) {
    $complaints->data_seek(0);
    echo "assignedEngineerMap = {";
    while ($row = $complaints->fetch_assoc()) {
      echo "'{$row['complaint_id']}': '" . (isset($row['assigned_engineer_username']) ? $row['assigned_engineer_username'] : '') . "',";

    }
    echo "};";
}
?>


function openAssignModal(id) {
  currentComplaintId = id;
  document.getElementById('assignComplaintIdText').innerText = 'CMP' + id;
  document.getElementById('engineerInput').value = '';
  filterEngineers();
  document.getElementById('assignModal').style.display = 'block';
}

function closeAssignModal() {
  document.getElementById('assignModal').style.display = 'none';
}

function filterEngineers() {
  const input = document.getElementById('engineerInput').value.toLowerCase();
  const dropdown = document.getElementById('engineerDropdown');
  dropdown.innerHTML = '';
  const matches = allEngineers.filter(e => e.full_name.toLowerCase().includes(input));
  matches.forEach(e => {
    const div = document.createElement('div');
    div.innerText = e.full_name;
    div.onclick = () => {
      document.getElementById('engineerInput').value = e.full_name;
      document.getElementById('engineerInput').setAttribute('data-username', e.username); // for backend
      dropdown.innerHTML = '';
    };
    dropdown.appendChild(div);
  });
}


function assignEngineer() {
 const name = document.getElementById('engineerInput').getAttribute('data-username');
const displayName = document.getElementById('engineerInput').value;

  if (!name) return alert("Please enter or select an engineer");

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "assign_engineer.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.onload = function() {
    if (xhr.status === 200 && xhr.responseText === "success") {
      assignedEngineerMap[currentComplaintId] = displayName;

    
      document.querySelectorAll(`button[data-id="${currentComplaintId}"]`).forEach(btn => {
  const row = btn.closest('tr');

 
  btn.outerHTML = `<span>${displayName}</span>`;


  const statusCell = row.querySelector('td:nth-child(6)');
  statusCell.innerText = 'Active';
});

closeAssignModal();
    } else {
      alert("Failed to assign engineer.");
    }
  };
  xhr.send("complaint_id=" + currentComplaintId + "&engineer=" + encodeURIComponent(name));

}

function openFeedbackModal(complaintId) {
  fetch("get_solution.php", {
    method: "POST",
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: "complaint_id=" + complaintId
  })
  .then(res => res.json())
  .then(data => {
    document.getElementById('f_id').innerText = 'CMP' + complaintId;
    document.getElementById('f_solution').innerText = data.solution || "No feedback provided";
    document.getElementById('feedbackReviewModal').style.display = 'block';
    currentComplaintId = complaintId;
  });
}

function closeFeedbackModal() {
  document.getElementById('feedbackReviewModal').style.display = 'none';
}

function acceptResolution() {
  fetch("accept_resolution.php", {
    method: "POST",
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: "complaint_id=" + currentComplaintId
  }).then(res => res.text()).then(data => {
    if (data === 'success') {
      alert("Resolution accepted.");
      location.reload();
    } else {
      alert("Failed to accept.");
    }
  });
}

function reassignComplaint() {
  closeFeedbackModal();
  openAssignModal(currentComplaintId);
}
function markNotResolved() {
  fetch("mark_not_resolved.php", {
    method: "POST",
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: "complaint_id=" + currentComplaintId
  }).then(res => res.text()).then(data => {
    if (data === 'success') {
      alert("Complaint marked as Pending again.");
     
    } else {
      alert("Failed to mark as not resolved.");
    }
  });
}

function submitAfterReview() {
  fetch("submit_review_resolution.php", {
    method: "POST",
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: "complaint_id=" + currentComplaintId
  }).then(res => res.text()).then(data => {
    if (data === 'success') {
      alert("Complaint removed after review.");
      closeFeedbackModal();
      location.reload();
    } else {
      alert("Failed to finalize complaint.");
    }
  });
}

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
</script>


</body>
</html>
