<?php
// Logout handler
session_start();
session_destroy();
header("Location: index.php");
exit();
?>
