<?php
    session_start();
    if (!isset($_SESSION['username']) || $_SESSION['admin_role'] !== 'Telecom') {
      header("Location: ../../../login/login_admin.html");
      exit();
    }
    
    $conn = new mysqli("localhost", "root", "", "cfees");
    if ($conn->connect_error) die("Connection failed");
    $filters = array(
      'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'desc',
      'from' => isset($_GET['from']) ? $_GET['from'] : '',
      'to' => isset($_GET['to']) ? $_GET['to'] : '',
      'search' => isset($_GET['search']) ? $_GET['search'] : '',
      'status' => isset($_GET['status']) ? $_GET['status'] : 'all'
    );
    

  $query = "
    SELECT 
      c.*, 
      c.telecom_number,
      CONCAT(e.first_name, ' ', e.last_name) AS employee_name, 
      d.desig_fullname AS designation,
      g.fullname AS group_name,
      c.solution, f.rating, f.reason,
      CONCAT(eng.first_name, ' ', eng.last_name) AS engineer_name
    FROM complaints c
    JOIN id_emp e ON c.employee_user_name = e.user_name
    LEFT JOIN id_desig d ON e.id = d.id
  LEFT JOIN id_group g ON e.id = g.id
    LEFT JOIN id_engineer eng ON eng.user_name = c.assigned_engineer_username
    LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
    WHERE c.type = 'Telecom'
  ";

  if (!empty($filters['from']) && !empty($filters['to'])) {
    $query .= " AND DATE(c.created_at) BETWEEN '" . $conn->real_escape_string($filters['from']) . "' AND '" . $conn->real_escape_string($filters['to']) . "'";
  }

  if (!empty($filters['search'])) {
    $search = $conn->real_escape_string($filters['search']);
    $query .= " AND (
        c.title LIKE '%$search%' OR
        c.description LIKE '%$search%' OR
        CONCAT(e.first_name, ' ', e.last_name) LIKE '%$search%' OR
        c.location LIKE '%$search%'
    )";
  }

  if ($filters['status'] !== 'all') {
  $status = $conn->real_escape_string($filters['status']);
  $query .= " AND c.status = '$status'";
}
  $query .= " ORDER BY c.created_at " . ($filters['sort'] === 'asc' ? 'ASC' : 'DESC');

  $result = $conn->query($query);


  ?>

  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Records - DRDO CFEES</title>
    <link rel="stylesheet" href="../../../css/admin/records.css">
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

    <header class="main-header">
      <div class="header-inner">
        <div class="logo-box left">
          <img src="../../../logos/logo-left.png" alt="Left Logo">
        </div>
        <div class="header-center">
          <h1 class="hindi-bold">अग्नि, पर्यावरण तथा विस्फोटक सुरक्षा केंद्र (CFEES)</h1>
          <p class="hindi-regular">रक्षा मन्त्रालय, भारत सरकार</p>
          <h2 class="eng-bold">Centre for Fire, Explosive and Environment Safety (CFEES)</h2>
          <p class="eng-regular">Ministry of Defence, Government of India</p>
        </div>
        <div class="logo-box right">
          <img src="../../../logos/logo-right.png" alt="Right Logo">
        </div>
      </div>
    </header>


    <div class="dashboard">
      <main class="main-content">
        <a href="telecom_dashboard.php" class="back-top-btn">
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
    </select>
    <div id="selectedAttributes" class="attr-boxes"></div>
    <button id="export-btn" onclick="exportFilteredToExcel()">Export</button>
  </div>
</div>

        <section class="complaint-records">
          <section class="filters">
          <form method="get">
            <label>Sort by:
              <select name="sort">
                <option value="desc" <?= $filters['sort'] === 'desc' ? 'selected' : '' ?>>Newest to Oldest</option>
<option value="asc" <?= $filters['sort'] === 'asc' ? 'selected' : '' ?>>Oldest to Newest</option>

              </select>
            </label>
            <label>Status:
  <select name="status">
    <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All Complaints</option>
    <option value="Active" <?= $filters['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
    <option value="Pending" <?= $filters['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
    <option value="Resolved" <?= $filters['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
    <option value="Review Pending" <?= $filters['status'] === 'Review Pending' ? 'selected' : '' ?>>Review Pending</option>
  </select>
</label>

            <label>From: <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>"></label>
            <label>To: <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>"></label>
            <label>Search By: <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($filters['search']) ?>"></label>
            <button type="submit">Apply Filters</button>
          </form>
        </section>
          <h2>Telecom Complaints</h2>
          

          <table>
            <thead>
  <tr>
    <th>S.No.</th>
    <th>Complaint ID</th>
    <th>Employee Name</th>
    <th>Designation</th>
    <th>Group</th>
    <th>Title</th>
    <th>Telecom</th>
    <th>Registered Date and Time</th>
    <th>Status</th>
    <th>Complaint Type</th>
    <th>Resolved Date and Time</th>
    <th>Details</th>
  </tr>
</thead>

            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                <?php $index = 1; ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                  <tr>
  <td><?php echo $index++; ?></td>
  <td>CMP<?= $row['complaint_id'] ?></td>
  <td><?= htmlspecialchars($row['employee_name']) ?></td>

<td><?= htmlspecialchars($row['designation'] ?: '—') ?></td>
  <td><?= htmlspecialchars($row['group_name'] ?: '—') ?></td>
  <td><?= htmlspecialchars($row['title']) ?></td>
  <td><?= htmlspecialchars($row['telecom_number'] ?: '—') ?></td>
  <td><?= date('Y-m-d H:i A', strtotime($row['created_at'])) ?></td>


  <td><span class="status <?= strtolower(str_replace(' ', '', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
  <td><?= htmlspecialchars($row['type']) ?></td>
  <td><?= $row['resolution_time'] ? date('Y-m-d H:i A', strtotime($row['resolution_time'])) : '—' ?></td>
  <td>
    <button class="view-btn" onclick='viewDetails(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'>View</button>
  </td>
</tr>

                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="12" style="text-align:center">No complaints found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </section>
      </main>
    </div>

    <div class="modal" id="viewModal">
      <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('viewModal').style.display='none'">&times;</span>
        <h3>Complaint Details</h3>
        <div id="modalContent"></div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
      <p>Copyright © 2025, DRDO, Ministry of Defence, Government of India</p>
    </footer>

    <script>
function viewDetails(data) {
  let html = `<p><strong>Complaint ID:</strong> CMP${data.complaint_id}</p>` +
             `<p><strong>Title:</strong> ${data.title}</p>` +
             `<p><strong>Description:</strong> ${data.description}</p>` +
             `<p><strong>Status:</strong> ${data.status}</p>` +
             `<p><strong>Telecom Number:</strong> ${data.telecom_number || '—'}</p>` + // <-- Add this line
             (data.engineer_name ? `<p><strong>Assigned Engineer:</strong> ${data.engineer_name}</p>` : '') ;

  if (data.status === 'Resolved') {
    html += `<p><strong>Engineer Feedback:</strong> ${data.solution || 'No feedback provided'}</p>` +
            `<p><strong>Employee Rating:</strong> ${data.rating || 'N/A'}</p>`;

    if (data.reason) {
      html += `<p><strong>Remarks:</strong> ${data.reason}</p>`;
    }
  }

  document.getElementById('modalContent').innerHTML = html;
  document.getElementById('viewModal').style.display = 'flex';
}


    </script>
      <script src="../../../js/xlsx.full.min.js"></script>
<script>
const exportAttributes = [
  "Complaint ID", "Title", "Description", "Status", "Registered Date", "Resolved Date",
  "Employee Name", "Designation", "Group", "Assigned Engineer", "Engineer Feedback", "Employee Rating", "Remarks"
];

const attrDropdown = document.getElementById("attributeDropdown");
const selectedAttributesBox = document.getElementById("selectedAttributes");
let selectedAttrs = [];

function openExportPopup() {
  document.getElementById("exportModal").style.display = "flex";
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
        tag.querySelector('.close-attr').addEventListener('click', function(e) {
          removeAttribute(attr);
        });
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
  tag.querySelector('.close-attr').addEventListener('click', function(e) {
    removeAttribute(value);
  });
  selectedAttributesBox.appendChild(tag);
  attrDropdown.value = "";
}

function removeAttribute(value) {
  selectedAttrs = selectedAttrs.filter(attr => attr !== value);
  [...selectedAttributesBox.children].forEach(tag => {
    if (tag.getAttribute("data-attr") === value) tag.remove();
  });
}

function exportFilteredToExcel() {
  const table = document.querySelector("table");
  const wb = XLSX.utils.book_new();
  const ws_data = [];

  if (!selectedAttrs.length) {
    alert("Please select at least one attribute to export.");
    return;
  }

  // Table headers
  ws_data.push(selectedAttrs);

  // Table rows
  for (const row of table.querySelectorAll("tbody tr")) {
    const data = {};
    const rowData = [];
    const viewData = JSON.parse(row.querySelector(".view-btn").getAttribute("onclick").match(/viewDetails\((.*)\)/)[1]);

    selectedAttrs.forEach(attr => {
      switch (attr) {
        case "Complaint ID": rowData.push(`CMP${viewData.complaint_id}`); break;
        case "Title": rowData.push(viewData.title); break;
        case "Description": rowData.push(viewData.description); break;
        case "Status": rowData.push(viewData.status); break;
        case "Registered Date": rowData.push(viewData.created_at); break;
        case "Resolved Date": rowData.push(viewData.resolution_time || '—'); break;
        case "Employee Name": rowData.push(viewData.employee_name); break;
        case "Designation": rowData.push(viewData.designation || '—'); break;
        case "Group": rowData.push(viewData.group_name || '—'); break;
        case "Assigned Engineer": rowData.push(viewData.engineer_name || '—'); break;
        case "Engineer Feedback": rowData.push(viewData.solution || '—'); break;
        case "Employee Rating": rowData.push(viewData.rating || '—'); break;
        case "Remarks": rowData.push(viewData.reason || '—'); break;
        default: rowData.push('');
      }
    });

    ws_data.push(rowData);
  }

  const ws = XLSX.utils.aoa_to_sheet(ws_data);
  XLSX.utils.book_append_sheet(wb, ws, "Telecom Complaints");
  XLSX.writeFile(wb, "Telecom_Complaints_Export.xlsx");
  closeExportPopup();
}
</script>

  </body>
  </html>
