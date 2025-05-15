<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
  <div class="position-sticky pt-3">
    <div class="text-center mb-3">
      <!-- Add logo to sidebar -->
      <img src="../assets/images/bohemian logo.jpg" alt="Bohemian Burrows" class="img-fluid mb-2" style="max-width: 80px;">
      <h5 class="text-white">Bohemian Burrows</h5>
      <p class="text-muted small">Cashier Panel</p>
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
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active bg-primary' : ''; ?>" href="pos.php">
          <i class="fas fa-cash-register me-2"></i>
          Point of Sale
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'sales_history.php' ? 'active bg-primary' : ''; ?>" href="sales_history.php">
          <i class="fas fa-receipt me-2"></i>
          My Transactions
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active bg-primary' : ''; ?>" href="profile.php">
          <i class="fas fa-user-circle me-2"></i>
          My Profile
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
        <div class="fw-bold text-white"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Cashier'; ?></div>
      </div>
    </div>
  </div>
</nav>
