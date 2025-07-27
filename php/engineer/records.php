<?php
session_start();

if (!isset($_SESSION['user_name'])) {
    header("Location: ../../login/login_engineer.html");
    exit();
}

$engineer_username = $_SESSION['user_name'];
$engineer_name = $_SESSION['engineer_name'];

$conn = new mysqli("localhost", "root", "", "cfees");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['from']) ? $_GET['from'] : '';
$date_to = isset($_GET['to']) ? $_GET['to'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$emp_desig_name = '';
$emp_group_name = '';

$sql1 = "SELECT desig_id, group_id FROM id_emp WHERE user_name = '" . mysqli_real_escape_string($conn, $engineer_username) . "' LIMIT 1";

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

$sql = "SELECT 
            c.*, 
            e.first_name AS emp_first_name, 
            e.last_name AS emp_last_name, 
            d.desig_fullname, 
            g.fullname AS group_name 
        FROM complaints c
        LEFT JOIN id_emp e ON c.employee_user_name = e.user_name
        LEFT JOIN id_desig d ON e.id = d.id
        LEFT JOIN id_group g ON e.id = g.id
        WHERE c.assigned_engineer_username = ?";




$params = [$engineer_username];
$types = "s";

if ($filter_type !== 'all') {
    $sql .= " AND c.type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($status_filter !== 'all') {
    $sql .= " AND COALESCE(NULLIF(TRIM(c.engineer_status), ''), 'Pending') = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_term)) {
    $sql .= " AND (c.title LIKE ? OR c.description LIKE ? OR c.location LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $types .= "sss";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(c.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $sql .= " AND DATE(c.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$order = $sort === 'oldest' ? 'ASC' : 'DESC';
$sql .= " ORDER BY c.created_at $order";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

$refs = array();
foreach ($params as $key => $value) {
    $refs[$key] = &$params[$key]; // Pass by reference
}
array_unshift($refs, $types);

call_user_func_array(array($stmt, 'bind_param'), $refs);
  

$stmt->execute();
$result = $stmt->get_result();
$complaints = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Complaint Records - DRDO CFEES</title>
  <link rel="stylesheet" href="../../css/engineer/records.css">
    <link rel="stylesheet" href="../../css_icons/all.min.css" />

  <style>
    .status {
      padding: 4px 10px;
      border-radius: 6px;
      font-weight: bold;
      display: inline-block;
    }
    .status.pending {
      color: #d0ab19ff;
    }
    .status.active {
      color: #0277bd;
    }
    .status.resolved {
      color: #388e3c;
    }
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
    margin: 0 30px 15px 0;
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
  </style>
</head>
<body>
  <!-- Header -->
  <header class="main-header">
    <div class="header-inner">
      <div class="logo-box left">
        <img src="../../logos/logo-left.png" alt="Left Logo">
      </div>
      <div class="header-center">
        <h1 class="hindi-bold">अग्नि, पर्यावरण तथा विस्फोटक सुरक्षा केंद्र (CFEES)</h1>
        <p class="hindi-regular">रक्षा मन्त्रालय, भारत सरकार</p>
        <h2 class="eng-bold">Centre for Fire, Explosive and Environment Safety (CFEES)</h2>
        <p class="eng-regular">Ministry of Defence, Government of India</p>
      </div>
      <div class="logo-box right">
        <img src="../../logos/logo-right.png" alt="Right Logo">
      </div>
    </div>
  </header>

  <!-- Dashboard Layout -->
  <div class="dashboard">
     <main class="main-content">
      <main class="main-content">
      <a href="dashboard.php" class="back-top-btn">
  <i class="fa fa-arrow-left"></i> Back to Dashboard
</a>
<a id="exportExcelBtn" class="export-btn" onclick="openExportPopup()">
      <i class="fa fa-file-excel"></i> Export to Excel
      </a>

      <div class="modal" id="exportModal">
        <div class="modal-content">
          <span class="close-btn" onclick="closeExportPopup()">&times;</span>
          <h3>Select Attributes to Export</h3>
          <select id="attributeDropdown" onchange="addSelectedAttribute(this.value)">
            <option value="">-- Select Attribute --</option>
            <option value="complaint_id">Complaint ID</option>
            <option value="title">Title</option>
            <option value="type">Complaint Type</option>
            <option value="location">Location</option>
            <option value="emp_first_name">Employee Name</option>
            <option value="created_at">Registered Date</option>
            <option value="intercom">Intercom</option>
            <option value="desig_id">Designation ID</option>
            <option value="group_id">Group ID</option>
            <option value="status">Status</option>
            <option value="description">Description</option>
            <option value="solution">Engineer Feedback</option>
            <option value="select_all">Select All</option>
          </select>
         <div id="selectedAttributes" class="attr-boxes"></div>
    <button id="export-btn" onclick="exportFilteredToExcel()">Export</button>
  </div>
      </div>


      <div class="complaint-records">
        <h2>Complaint Records</h2>
        <form method="GET" class="filters">

  <label for="sort">Sort By:</label>
  <select name="sort" id="sort">
    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
  </select>

  <label>By Type:</label>
  <select name="type">
    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Complaints</option>
    <option value="Software" <?= $filter_type === 'Software' ? 'selected' : '' ?>>Software</option>
    <option value="Hardware" <?= $filter_type === 'Hardware' ? 'selected' : '' ?>>Hardware</option>
    <option value="Network" <?= $filter_type === 'Network' ? 'selected' : '' ?>>Network</option>
  </select>

  <label>Status:</label>
  <select name="status">
    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Complaints</option>
    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
    <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
    <option value="Resolved" <?= $status_filter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
    <option value="Not Resolved" <?= $status_filter === 'Not Resolved' ? 'selected' : '' ?>>Not Resolved</option>
  
    
  </select>

  <label for="from">From:</label>
  <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>">

  <label for="to">To:</label>
  <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>">

  <label>Search By: <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search_term) ?>"></label>
  <button type="submit">Apply Filters</button>
</form>


        <table>
         <thead>
  <tr>
    <th>S.No.</th>
    <th>Complaint ID</th>
        <th>Employee Name</th>
        <th>Designation</th>
    <th>Group</th>
    <th>Title</th>
    <th>Complaint Type</th>
    <th>Location</th>
    <th>Registered Date and Time</th>
    <th>Intercom</th>

    <th>Status</th>
    <th>Action</th>
  </tr>
</thead>
<tbody>
  <?php if (!empty($complaints)): ?>
    <?php $index = 1; foreach ($complaints as $c): ?>
      <tr>
        <td><?= $index++ ?></td>
        <td><?= $c['complaint_id'] ?></td>
        <td><?= htmlspecialchars((isset($c['emp_first_name']) ? $c['emp_first_name'] : '') . ' ' . (isset($c['emp_last_name']) ? $c['emp_last_name'] : '')) ?></td>
        <td><?= htmlspecialchars(isset($c['desig_fullname']) ? $c['desig_fullname'] : '-') ?></td>
<td><?= htmlspecialchars(isset($c['group_name']) ? $c['group_name'] : '-') ?></td>

        <td><?= htmlspecialchars($c['title']) ?></td>
        <td><?= htmlspecialchars($c['type']) ?></td>
        <td><?= htmlspecialchars($c['location']) ?></td>
        <td><?= $c['created_at'] ?></td>
        <td><?= htmlspecialchars(isset($c['intercom']) ? $c['intercom'] : '-') ?></td>

        <td>
          <span class="status <?= strtolower(str_replace(' ', '', isset($c['engineer_status']) ? $c['engineer_status'] : 'Pending')) ?>">
            <?= htmlspecialchars(isset($c['engineer_status']) ? $c['engineer_status'] : 'Pending') ?>
          </span>
        </td>
        <td><button class="view-btn" 
        data-complaint='<?= htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8") ?>'>
  View
</button>
</td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr><td colspan="12" style="text-align:center">No complaints found.</td></tr>
  <?php endif; ?>
</tbody>


          
        </table>
      </div>
    </main>
  </div>

  <div class="modal" id="detailsModal">
    <div class="modal-content">
      <span class="close-btn" onclick="document.getElementById('detailsModal').style.display='none'">&times;</span>
      <div id="modalContent"></div>
    </div>
  </div>

  <footer class="main-footer">
    <p>&copy; 2025, DRDO, Ministry of Defence, Government of India</p>
  </footer>

  <script>
  function showDetails(data) {
  let html = `
    <h3>Complaint Details</h3>
    <p><strong>Complaint ID:</strong> ${data.complaint_id}</p>
    <p><strong>Title:</strong> ${data.title}</p>
    <p><strong>Description:</strong> ${data.description}</p>
    <p><strong>Location:</strong> ${data.location}</p>
    <p><strong>Complaint Type:</strong> ${data.type}</p>
  `;

  // ✅ Conditionally add telecom number
  if (data.type === 'Telecom') {
    html += `<p><strong>Telecom Number:</strong> ${data.telecom_number ? data.telecom_number : '-'}</p>`;
  }

  html += `
    <p><strong>Intercom:</strong> ${data.intercom != null ? data.intercom : '-'}</p>
    <p><strong>Status:</strong> ${data.status}</p>
    <p><strong>Employee Name:</strong> ${(data.emp_first_name != null ? data.emp_first_name : '') + ' ' + (data.emp_last_name != null ? data.emp_last_name : '')}</p>
    <p><strong>Registered Date and Time:</strong> ${data.created_at}</p>
    <p><strong>Engineer Feedback:</strong> ${data.solution != null ? data.solution : '-'}</p>
  `;

  document.getElementById('modalContent').innerHTML = html;
  document.getElementById('detailsModal').style.display = 'flex';
}

  </script>
  <script src="../../js/xlsx.full.min.js"></script>

  <script>
  const exportModal = document.getElementById('exportModal');
  const selectedAttributes = new Set();

  function openExportPopup() {
    exportModal.style.display = 'flex';
  }

  function closeExportPopup() {
    exportModal.style.display = 'none';
  }

  function addSelectedAttribute(attr) {
  if (!attr || selectedAttributes.has(attr)) return;

  if (attr === 'select_all') {
    document.querySelectorAll('#attributeDropdown option').forEach(opt => {
      if (opt.value && opt.value !== 'select_all') {
        addSelectedAttribute(opt.value);
      }
    });
    return;
  }

  selectedAttributes.add(attr);
  
  const tag = document.createElement('div');
  tag.className = 'attr-tag';
  tag.dataset.attr = attr;

  const label = document.createTextNode(attr.replace(/_/g, ' '));
  const closeBtn = document.createElement('span');
  closeBtn.innerHTML = '&times;';
  closeBtn.style.marginLeft = '8px';
  closeBtn.style.cursor = 'pointer';

  closeBtn.addEventListener('click', () => removeAttribute(attr));

  tag.appendChild(label);
  tag.appendChild(closeBtn);

  document.getElementById('selectedAttributes').appendChild(tag);
}


  function removeAttribute(attr) {
    selectedAttributes.delete(attr);
    document.querySelectorAll(`.attr-tag[data-attr="${attr}"]`).forEach(tag => tag.remove());
  }

  function exportFilteredToExcel() {
    const rows = Array.from(document.querySelectorAll('table tbody tr'));
    if (selectedAttributes.size === 0) {
      alert('Please select at least one attribute to export!');
      return;
    }

    const columns = Array.from(selectedAttributes);
    const headers = columns.map(col => col.replace(/_/g, ' ').toUpperCase());
    const data = rows.map(row => {
      const obj = {};
      columns.forEach(col => {
        const cellIndex = getColumnIndex(col);
        const cell = row.children[cellIndex];
        obj[col] = cell ? cell.innerText.trim() : '';
      });
      return obj;
    });

    const worksheet = XLSX.utils.json_to_sheet(data, { header: columns });
    XLSX.utils.sheet_add_aoa(worksheet, [headers], { origin: "A1" });

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, worksheet, "Complaints");
    XLSX.writeFile(wb, "complaints_export.xlsx");
  }

  function getColumnIndex(attribute) {
    const map = {
      complaint_id: 1,
    emp_first_name: 2,
    desig_id: 3,
    group_id: 4,
    title: 5,
    type: 6,
    location: 7,
    created_at: 8,
    intercom: 9,
    status: 10,
    description: 5, 
    solution: 5     
  };
    return (map[attribute] !== undefined && map[attribute] !== null) ? map[attribute] : -1;

  }

  window.addEventListener('click', (e) => {
    if (e.target === exportModal) closeExportPopup();
  });
</script>
<script>
  document.querySelectorAll('.view-btn').forEach(button => {
    button.addEventListener('click', () => {
      const data = JSON.parse(button.getAttribute('data-complaint'));
      showDetails(data);
    });
  });
</script>

</body>
</html>
