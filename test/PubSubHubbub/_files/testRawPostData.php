<?php

readfile('php://input');
header($_SERVER["SERVER_PROTOCOL"] . " 202 Verified");
