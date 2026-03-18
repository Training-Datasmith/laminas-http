<?php

declare(strict_types=1);

// intentional use of case-insensitive header name
header('Transfer-encoding: chunked');
header('content-encoding: gzip');
