<?php
if ( is_array($_GET) )
    reset($_GET);

header("Location: index.php".(is_array($_GET) ? "?".key($_GET) : NULL));
exit;
?>