<?php 

require_once "includes/inc_all.php";
// French translation
require_once __DIR__ . '/../../lang/fr/itflow_integration.php';

?>
	
<!-- Breadcrumbs-->
<ol class="breadcrumb">
  <li class="breadcrumb-item">
    <a href="index.php">Tableau de bord</a>
  </li>
  <li class="breadcrumb-item active">Blank Page</li>
</ol>

<!-- Page Content -->
<h1>Blank Page</h1>
<hr>

<meta http-equiv="refresh" content="0;url=<?= $config_start_page ?>">

<?php
require_once "../includes/footer.php";