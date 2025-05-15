<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
  <div class="position-sticky pt-3">
    <div class="text-center mb-3">
      <!-- Add logo to sidebar -->
      <img src="../assets/images/bohemian logo.jpg" alt="Bohemian Burrows" class="img-fluid mb-2" style="max-width: 80px;">
      <h5 class="text-white">Bohemian Burrows</h5>
      <p class="text-muted small">Admin Panel</p>
    </div>
    <hr class="text-secondary">
    <ul class="nav flex-column">
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active bg-primary' : ''; ?>" href="dashboard.php">
          <i class="fas fa-tachometer-alt me-2"></i>
          Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active bg-primary' : ''; ?>" href="products.php">
          <i class="fas fa-tshirt me-2"></i>
          Products
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active bg-primary' : ''; ?>" href="inventory.php">
          <i class="fas fa-boxes me-2"></i>
          Inventory
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active bg-primary' : ''; ?>" href="pos.php">
          <i class="fas fa-cash-register me-2"></i>
          Point of Sale
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'sales_history.php' ? 'active bg-primary' : ''; ?>" href="sales_history.php">
          <i class="fas fa-receipt me-2"></i>
          Sales History
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php' || basename($_SERVER['PHP_SELF']) == 'order_details.php') ? 'active bg-primary' : ''; ?>" href="orders.php">
          <i class="fas fa-shopping-bag me-2"></i>
          Orders
          <?php 
          // Count pending orders
          require_once "../includes/db_connect.php";
          $pending_query = $conn->query("SELECT COUNT(*) as count FROM sales WHERE payment_status = 'pending'");
          $pending_count = $pending_query ? $pending_query->fetch_assoc()['count'] : 0;
          if($pending_count > 0): 
          ?>
            <span class="badge bg-danger rounded-pill ms-1"><?php echo $pending_count; ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'deliveries.php') ? 'active bg-primary' : ''; ?>" href="deliveries.php">
          <i class="fas fa-truck me-2"></i>
          Deliveries
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'customers.php') ? 'active bg-primary' : ''; ?>" href="customers.php">
          <i class="fas fa-users me-2"></i>
          Customers
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active bg-primary' : ''; ?>" href="user_management.php">
          <i class="fas fa-user-shield me-2"></i>
          Staff
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active bg-primary' : ''; ?>" href="reports.php">
          <i class="fas fa-chart-bar me-2"></i>
          Reports
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active bg-primary' : ''; ?>" href="settings.php">
          <i class="fas fa-cog me-2"></i>
          Settings
        </a>
      </li>
    </ul>
    
    <hr class="text-secondary">
    <div class="px-3 mt-4">
      <a href="../logout.php" class="btn btn-danger btn-sm d-flex align-items-center justify-content-center">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
      </a>
    </div>
    
    <div class="px-3 mt-3 mb-3 text-center">
      <div class="text-white-50 small">
        <div>Logged in as:</div>
        <div class="fw-bold text-white"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?></div>
      </div>
    </div>
  </div>
</nav>
