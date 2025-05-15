<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
  <div class="position-sticky pt-3">
    <div class="text-center mb-3">
      <!-- Add logo to sidebar -->
      <img src="../assets/images/bohemian logo.jpg" alt="Bohemian Burrows" class="img-fluid mb-2" style="max-width: 80px;">
      <h5 class="text-white">Bohemian Burrows</h5>
      <p class="text-muted small">Customer Portal</p>
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
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'shop.php' ? 'active bg-primary' : ''; ?>" href="shop.php">
          <i class="fas fa-store me-2"></i>
          Shop
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active bg-primary' : ''; ?>" href="cart.php">
          <i class="fas fa-shopping-cart me-2"></i>
          Cart
          <?php if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
            <span class="badge bg-danger rounded-pill ms-1">
              <?php 
                $count = 0;
                foreach($_SESSION['cart'] as $item) {
                  $count += $item['quantity'];
                }
                echo $count;
              ?>
            </span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active bg-primary' : ''; ?>" href="orders.php">
          <i class="fas fa-shopping-bag me-2"></i>
          My Orders
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'wishlist.php' ? 'active bg-primary' : ''; ?>" href="wishlist.php">
          <i class="fas fa-heart me-2"></i>
          Wishlist
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active bg-primary' : ''; ?>" href="profile.php">
          <i class="fas fa-user me-2"></i>
          My Profile
        </a>
      </li>
    </ul>
    
    <hr class="text-secondary">
    <div class="px-3 mt-4">
      <a href="../logout.php" class="btn btn-outline-danger btn-sm d-flex align-items-center justify-content-center">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
      </a>
      
      <div class="mt-3 text-center">
        <div class="text-muted small">
          <div>Logged in as:</div>
          <div class="fw-bold"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Customer'; ?></div>
        </div>
      </div>
    </div>
  </div>
</nav>
