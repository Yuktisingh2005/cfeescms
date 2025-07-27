<?php
session_start();
$conn = new mysqli("localhost", "root", "", "cfees");

if (!isset($_SESSION['username'])) {
    header("Location: ../../../login/login_admin.html");
    exit();
}

$user_name = $_SESSION['username'];
$user_result = $conn->query("SELECT name FROM id_admin WHERE username = '$user_name' LIMIT 1");

if ($user_result && $user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
$name = isset($user_data['name']) ? $user_data['name'] : '';
} else {
    $name = "Super Admin";
}

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';

$query = "SELECT 
  c.complaint_id,
  c.title,
  c.description,
  c.location,
  c.type,
  c.status,
  c.telecom_number,
  c.intercom,
  c.created_at AS registered_time,
  c.resolution_time AS resolved_time,
  c.solution AS engineer_feedback,
  e.first_name AS emp_first_name,
  e.last_name AS emp_last_name,
  e.desig_id,
  d.desig_fullname AS designation_name,
  e.group_id,
  g.fullname AS group_name,
  eng.first_name AS eng_first_name,
  eng.last_name AS eng_last_name,
  f.rating,
  f.reason,
  f.rating AS admin_feedback
FROM complaints c
JOIN id_emp e ON c.employee_user_name = e.user_name
LEFT JOIN id_engineer eng ON c.assigned_engineer_username = eng.user_name
LEFT JOIN id_desig d ON e.id = d.id
LEFT JOIN id_group g ON e.id = g.id
LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
WHERE 1 = 1
";
if ($status === 'default') {
  $query .= " AND c.status IN ('Active', 'Pending', 'Review Pending')";
} elseif ($status !== 'all') {
  $query .= " AND c.status = '" . $conn->real_escape_string($status) . "'";
}
if ($type !== 'all') {
  $query .= " AND c.type = '" . $conn->real_escape_string($type) . "'";
}
if ($rating_filter !== 'all') {
  $query .= " AND f.rating = '" . $conn->real_escape_string($rating_filter) . "'";
}
if (!empty($from)) {
  $query .= " AND DATE(c.created_at) >= '" . $conn->real_escape_string($from) . "'";
}
if (!empty($to)) {
  $query .= " AND DATE(c.created_at) <= '" . $conn->real_escape_string($to) . "'";
}
if (!empty($search)) {
  $s = $conn->real_escape_string($search);
  $query .= " AND (c.title LIKE '%$s%' OR c.description LIKE '%$s%' OR e.first_name LIKE '%$s%' OR e.last_name LIKE '%$s%')";
}
$query .= ($sort === 'oldest') ? " ORDER BY c.created_at ASC" : " ORDER BY c.created_at DESC";

$result = $conn->query($query);
$complaints = [];
while ($row = $result->fetch_assoc()) {
  $complaints[] = $row;
}

$totalQuery = "SELECT COUNT(*) AS count FROM complaints";
$activeQuery = "SELECT COUNT(*) AS count FROM complaints WHERE status = 'Active'";
$pendingQuery = "SELECT COUNT(*) AS count FROM complaints WHERE status = 'Pending'";
$resolvedQuery = "SELECT COUNT(*) AS count FROM complaints WHERE status = 'Resolved'";
$reviewPendingQuery = "SELECT COUNT(*) AS count FROM complaints WHERE status = 'Review Pending'";

function getCount($conn, $query) {
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) return $row['count'];
    return 0;
}

$totalCount = getCount($conn, "SELECT COUNT(*) AS count FROM complaints");
$activeCount = getCount($conn, "SELECT COUNT(*) AS count FROM complaints WHERE status = 'Active'");
$pendingCount = getCount($conn, "SELECT COUNT(*) AS count FROM complaints WHERE status = 'Pending'");
$resolvedCount = getCount($conn, "SELECT COUNT(*) AS count FROM complaints WHERE status = 'Resolved'");
$reviewPendingCount = getCount($conn, "SELECT COUNT(*) AS count FROM complaints WHERE status = 'Review Pending'");
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Super-Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../../../css/index/index.css" />
  <link rel="stylesheet" href="../../../css/admin/superadmin_dashboard.css" />
        <link rel="stylesheet" href="../../../css_icons/all.min.css" />


<style>
    .view-btn {
      background: #0059b3;
      color: #fff;
      padding: 6px 12px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      transition: background 0.3s;
    }
    .view-btn:hover {
      background: #004080;
    }
  .export-btn {
    float: right;
    margin: 27px 30px 15px 0;
    background-color: #2e7d32;
    color: white;
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    text-decoration: none;
    font-weight:bold;
  }
  .export-btn:hover {
    background-color: #256429;
  }
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.4);
    justify-content: center;
    align-items: center;
  }
  .modal-content {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    width: 450px;
    position: relative;
  }
  .close-btn {
    position: absolute;
    top: 8px;
    right: 12px;
    font-size: 22px;
    cursor: pointer;
  }
  .attr-boxes {
    display: flex;
    flex-wrap: wrap;
    margin-top: 10px;
  }
  .attr-tag {
    background: #f1f1f1;
    border: 1px solid #ccc;
    padding: 5px 10px;
    margin: 5px;
    border-radius: 20px;
    font-size: 13px;
    display: flex;
    align-items: center;
  }
  .attr-tag span {
    margin-left: 8px;
    color: black;
    cursor: pointer;
  }
  .fa-solid, .fas {
    font-weight: 900;
    font-size: 25px;
    margin-bottom: 10px;
}
.fa-user{
  font-size:16px;
}
.fa-right-from-bracket{
  font-size:16px; 
}
  </style>
</head>
<body>
  <header class="main-header">
    <div class="header-inner">
      <div class="logo-box left"><img src="../../../logos/logo-left.png" alt="Left Logo">
      </div>
      <div class="header-center">
      <h1 class="hindi-bold">अग्नि, पर्यावरण तथा विस्फोटक सुरक्षा केंद्र (CFEES)</h1>
      <p class="hindi-regular">रक्षा मन्त्रालय, भारत सरकार</p>
      <h2 class="eng-bold">Centre for Fire, Explosive and Environment Safety (CFEES)</h2>
      <p class="eng-regular">Ministry of Defence, Government of India</p>
    </div>
      <div class="logo-box right"><img src="../../../logos/logo-right.png" alt="Right Logo">
    </div>
  </header>

  <div class="page-layout">
    <aside class="sidebar">
      <div class="profile-box">
        <div class="avatar-box">
        <img src="../../../logos/default_user.jpg" alt="Profile Picture" />
        </div>
        <h3><?= htmlspecialchars($name); ?></h3>
      </div>
      <nav class="sidebar-nav">
        <ul>
          <li><a href="MyProfile_superadmin.php"><i class="fa-solid fa-user"></i> User Profile</a></li>
          <li><a href="./logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </ul>
      </nav>
    </aside>

    <main class="main-content">
      <h2 class="welcome">Welcome, <?= htmlspecialchars($name); ?></h2>
      <div class="action-boxes">
  <!-- Add data-status to each box -->
<div class="action-box" data-status="all">
  <i class="fas fa-layer-group"></i>
  <h3>Total Complaints: <?= $totalCount; ?></h3>
</div>
<div class="action-box" data-status="Active">
  <i class="fa-solid fa-bolt"></i>
  <h3>Active Complaints: <?= $activeCount; ?></h3>
</div>
<div class="action-box" data-status="Pending">
  <i class="fa-solid fa-hourglass-half"></i>
  <h3>Pending Complaints: <?= $pendingCount; ?></h3>
</div>
<div class="action-box" data-status="Resolved">
  <i class="fa-solid fa-circle-check"></i>
  <h3>Resolved Complaints: <?= $resolvedCount; ?></h3>
</div>
<div class="action-box" data-status="Review Pending">
  <i class="fa-solid fa-star-half-stroke"></i>
  <h3>Review Pending Complaints: <?= $reviewPendingCount; ?></h3>
</div>
</div>

      <h3 class="section-title">All Complaints</h3>

      <form class="filter-form" method="GET">
        <div>
          <label for="sort">Sort By:
          <select name="sort" id="sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
          </select></label>
        </div>
        <div>
  <label for="rating">Rating:
    <select name="rating" id="rating">
      <option value="all" <?= $rating_filter === 'all' ? 'selected' : '' ?>>All Ratings</option>
      <option value="5" <?= $rating_filter === '5' ? 'selected' : '' ?>>5 Star</option>
      <option value="4" <?= $rating_filter === '4' ? 'selected' : '' ?>>4 Star</option>
      <option value="3" <?= $rating_filter === '3' ? 'selected' : '' ?>>3 Star</option>
      <option value="2" <?= $rating_filter === '2' ? 'selected' : '' ?>>2 Star</option>
      <option value="1" <?= $rating_filter === '1' ? 'selected' : '' ?>>1 Star</option>
    </select>
  </label>
</div>
        <div>
          <label for="type">Type:
          <select name="type" id="type">
  <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Complaints</option>
  <option value="Software" <?= $type === 'Software' ? 'selected' : '' ?>>Software</option>
  <option value="Network" <?= $type === 'Network' ? 'selected' : '' ?>>Network</option>
  <option value="Hardware" <?= $type === 'Hardware' ? 'selected' : '' ?>>Hardware</option>
</select></label>
        </div>
        <?php $status = isset($_GET['status']) ? $_GET['status'] : 'default'; ?>

<div>
  <label for="status">Status:
 <select name="status" id="status">
  <option value="default" <?= $status === 'default' ? 'selected' : '' ?>>Active, Pending & Review Pending</option>
  <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Complaints</option>
  <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
  <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
  <option value="Resolved" <?= $status === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
  <option value="Review Pending" <?= $status === 'Review Pending' ? 'selected' : '' ?>>Review Pending</option>
</select>
</div>

        <div>
          <label for="from">From:
          <input type="date" name="from" id="from" value="<?= htmlspecialchars($from); ?>">
        </div></label>
        <div>
          <label for="to">To:
          <input type="date" name="to" id="to" value="<?= htmlspecialchars($to); ?>">
        </div></label>
        <div>
          <label for="search">Search By:
          <input type="text" name="search" id="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search...">
        </div></label>
        <div>
          <button type="submit">Apply Filters</button>
        </div>
      </form>
<div class="export-btn-container">
      <a id="exportExcelBtn" class="export-btn" onclick="openExportPopup()">
 <i class="fa fa-file-excel"></i> Export to Excel
</a>
</div>

<div class="modal" id="exportModal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeExportPopup()">&times;</span>
    <h3>Select Attributes to Export</h3>
    <select id="attributeDropdown" onchange="addSelectedAttribute(this.value)">
      <option value="">-- Select Attribute --</option>
      
    </select>
    <div id="selectedAttributes" class="attr-boxes"></div>
    <button type="button" onclick="exportFilteredToExcel()">Export</button>

  </div>
</div>

 
      <div class="table-container">
        <table>
          <thead>
  <tr>
    <th>S.No.</th>
    <th>Complaint ID</th>
    <th>Employee Name</th>
    <th>Designation</th>
    <th>Group</th>
    <th>Title</th>
    <th>Type</th>
    <th>Status</th>
    <th>Registered Date and Time</th>
    <th>Resolved Date and Time</th>
    <th>Rating</th>
    <th>Review</th>
  </tr>
</thead>
<tbody>
<?php
if (count($complaints) === 0) {
  echo "<tr><td colspan='12'>No complaints found.</td></tr>";
} else {
  $index = 1;
  foreach ($complaints as $c) {
    $statusClass = strtolower(str_replace(' ', '-', $c['status']));
    $empName = (isset($c['emp_first_name']) ? $c['emp_first_name'] : '') . ' ' . (isset($c['emp_last_name']) ? $c['emp_last_name'] : '');
$resolvedTime = isset($c['resolved_time']) ? $c['resolved_time'] : '-';
$rating = isset($c['rating']) ? $c['rating'] : '-';

    $viewData = htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8");
    echo "<tr>
      <td>" . $index++ . "</td>
      <td>CMP" . htmlspecialchars($c['complaint_id']) . "</td>
      <td>" . htmlspecialchars($empName) . "</td>
      <td>" . htmlspecialchars($c['designation_name']) . "</td>
      <td>" . htmlspecialchars($c['group_name']) . "</td>
      <td>" . htmlspecialchars($c['title']) . "</td>
      <td>" . htmlspecialchars($c['type']) . "</td>
      <td><span class='status $statusClass'>" . htmlspecialchars($c['status']) . "</span></td>
      <td>" . htmlspecialchars($c['registered_time']) . "</td>
      <td>" . htmlspecialchars($resolvedTime) . "</td>
      <td>" . htmlspecialchars($rating) . "</td>
      <td><button class='view-btn' onclick='showDetails($viewData)'>View</button></td>
    </tr>";
  }
}
?>
</tbody>
        </table>
      </div>
    </main>
  </div>

  <div id="viewModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">&times;</span>
    <h2>Complaint Details</h2>
    <div id="complaintDetails"></div>
  </div>
</div>

<footer class="main-footer">
  <p>Copyright © 2025, DRDO, Ministry of Defence, Government of India</p>
</footer>
<script>
const tableData = <?= json_encode($complaints); ?>;
</script>
<script>
  function showDetails(data) {
    // If data is a string, parse it
    if (typeof data === "string") data = JSON.parse(data);

    let html = `
      <p><strong>Complaint ID:</strong> CMP${data.complaint_id}</p>
      <p><strong>Title:</strong> ${data.title}</p>
      <p><strong>Description:</strong> ${data.description}</p>
      <p><strong>Location:</strong> ${data.location}</p>
      <p><strong>Type:</strong> ${data.type}</p>
      <p><strong>Status:</strong> ${data.status}</p>
      ${data.type === "Telecom" ? `<p><strong>Telecom Number:</strong> ${data.telecom_number || '-'}</p>` : ""}
      <p><strong>Intercom:</strong> ${data.intercom}</p>
      <p><strong>Registered Time:</strong> ${data.registered_time}</p>
      <p><strong>Resolved Time:</strong> ${data.resolved_time ? data.resolved_time : '-'}</p>
      <p><strong>Engineer Feedback:</strong> ${data.engineer_feedback ? data.engineer_feedback : '-'}</p>
      <p><strong>Admin Feedback:</strong> ${data.admin_feedback ? data.admin_feedback : '-'}</p>
      <p><strong>Rating:</strong> ${data.rating ? data.rating : '-'}</p>
    `;

    document.getElementById("complaintDetails").innerHTML = html;
    document.getElementById("viewModal").style.display = "flex";
  }

  function closeModal() {
    document.getElementById("viewModal").style.display = "none";
  }

  document.querySelectorAll('.action-box').forEach(box => {
  box.style.cursor = 'pointer';
  box.addEventListener('click', function() {
    const status = this.getAttribute('data-status');
    let url = window.location.pathname + '?status=' + encodeURIComponent(status);
    window.location.href = url;
  });
});

const exportAttributes = [
  "Complaint ID", "Title", "Description", "Location", "Intercom", "Telecom Number",
  "Type", "Status", "Registered Time", "Resolved Time", "Engineer Feedback",
  "Employee Name", "Designation", "Group", "Engineer Name", "Rating", "Admin Feedback"
];
let selectedAttrs = [];
const attrDropdown = document.getElementById("attributeDropdown");
const selectedAttributesBox = document.getElementById("selectedAttributes");

function openExportPopup() {
  attrDropdown.innerHTML = `
    <option value="">-- Select Attribute --</option>
    <option value="__select_all__">Select All</option>
  `;
  exportAttributes.forEach(attr => {
    const option = document.createElement("option");
    option.value = attr;
    option.textContent = attr;
    attrDropdown.appendChild(option);
  });
  document.getElementById("exportModal").style.display = "flex";
}

function closeExportPopup() {
  document.getElementById("exportModal").style.display = "none";
  selectedAttrs = [];
  selectedAttributesBox.innerHTML = '';
}

function addSelectedAttribute(value) {
  if (!value) return;

  if (value === "__select_all__") {
    exportAttributes.forEach(attr => {
      if (!selectedAttrs.includes(attr)) {
        selectedAttrs.push(attr);
        const tag = document.createElement("div");
        tag.className = "attr-tag";
        tag.setAttribute("data-attr", attr);
        tag.innerHTML = `${attr}<span class="close-attr" style="margin-left:8px;cursor:pointer;">&times;</span>`;
        tag.querySelector('.close-attr').onclick = function() { removeAttribute(attr); };
        selectedAttributesBox.appendChild(tag);
      }
    });
    attrDropdown.value = "";
    return;
  }

  if (selectedAttrs.includes(value)) return;

  selectedAttrs.push(value);
  const tag = document.createElement("div");
  tag.className = "attr-tag";
  tag.setAttribute("data-attr", value);
  tag.innerHTML = `${value}<span class="close-attr" style="margin-left:8px;cursor:pointer;">&times;</span>`;
  tag.querySelector('.close-attr').onclick = function() { removeAttribute(value); };
  selectedAttributesBox.appendChild(tag);
  attrDropdown.value = "";
}

function removeAttribute(attr) {
  const index = selectedAttrs.indexOf(attr);
  if (index !== -1) {
    selectedAttrs.splice(index, 1);
    [...selectedAttributesBox.children].forEach(tag => {
      if (tag.getAttribute("data-attr") === attr) tag.remove();
    });
  }
}

function exportFilteredToExcel() {
  if (selectedAttrs.length === 0) {
    alert("Please select at least one attribute to export.");
    return;
  }
  let csv = selectedAttrs.join(",") + "\n";
  tableData.forEach(row => {
    let rowData = [];
    selectedAttrs.forEach(attr => {
      switch (attr) {
        case "Complaint ID": rowData.push(`CMP${row.complaint_id}`); break;
        case "Title": rowData.push(row.title); break;
        case "Description": rowData.push(row.description); break;
        case "Location": rowData.push(row.location); break;
        case "Intercom": rowData.push(row.intercom); break;
        case "Telecom Number": rowData.push(row.telecom_number || ''); break;
        case "Type": rowData.push(row.type); break;
        case "Status": rowData.push(row.status); break;
        case "Registered Time": rowData.push(row.registered_time); break;
        case "Resolved Time": rowData.push(row.resolved_time || ''); break;
        case "Engineer Feedback": rowData.push(row.engineer_feedback || ''); break;
        case "Employee Name": rowData.push((row.emp_first_name || '') + ' ' + (row.emp_last_name || '')); break;
        case "Designation": rowData.push(row.designation_name || ''); break;
        case "Group": rowData.push(row.group_name || ''); break;
        case "Engineer Name": rowData.push((row.eng_first_name || '') + ' ' + (row.eng_last_name || '')); break;
        case "Rating": rowData.push(row.rating || ''); break;
        case "Admin Feedback": rowData.push(row.admin_feedback || ''); break;
        default: rowData.push('');
      }
    });
    csv += rowData.map(v => `"${(v || '').toString().replace(/"/g, '""')}"`).join(",") + "\n";
  });

  const blob = new Blob([csv], { type: "text/csv" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "complaints_export.csv";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  closeExportPopup();
}
</script>
</body>
</html>
