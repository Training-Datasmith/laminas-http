<?php

declare(strict_types=1);

$clength = filesize(__FILE__);

header(sprintf('Content-length: %s', $clength));
