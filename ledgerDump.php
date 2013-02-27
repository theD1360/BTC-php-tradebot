<?php

	print_r(json_decode(str_replace("\u0000","",file_get_contents(".ledgerDump"))));

?>
