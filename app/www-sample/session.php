<?php
	session_start();
	if (!isset($_SESSION['count'])) {
		$_SESSION['count'] = 0;
	} else {
		$_SESSION['count']++;
	}
	
?>
<html>
SESSION <br/>
Count = <?php echo $_SESSION['count'] ?><br/>
ID = <?php echo session_id() ?>
</html>