<?php
// Get the current page name (e.g., 'phone.php')
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <div class="container">
      <ul class="nav-links">
        <li><a href="../../index.html">Home</a></li>
        
        <li><a href="phone.php" class="<?php echo ($current_page == 'phone.php') ? 'active' : ''; ?>">Phones</a></li>
        
        <li><a href="Tablets.php" class="<?php echo ($current_page == 'Tablets.php') ? 'active' : ''; ?>">Tablets</a></li>
        <li><a href="accessories.php" class="<?php echo ($current_page == 'accessories.php') ? 'active' : ''; ?>">Accessories</a></li>
        <li><a href="laptop.php" class="<?php echo ($current_page == 'laptop.php') ? 'active' : ''; ?>">Laptops</a></li>
        <li><a href="gaming.php" class="<?php echo ($current_page == 'gaming.php') ? 'active' : ''; ?>">Gaming</a></li>
      </ul>
    </div>
</nav>