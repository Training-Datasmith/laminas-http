<?php

declare(strict_types=1);

header('Content-Encoding: gzip');
echo gzcompress('Success');
