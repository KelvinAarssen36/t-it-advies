<?php

namespace App\Support\Backup;

use RuntimeException;

/**
 * Het signaal dat een proefterugzetting klaar is en teruggedraaid mag.
 *
 * Een eigen uitzondering en geen generieke: zo kan `BackupTerugzetter`
 * hem opvangen zonder per ongeluk een echte fout op te slokken. Dit is
 * geen storing -- hij hoort erbij.
 *
 * Zie docs/operations/back-ups.md.
 */
class ProefAfgerond extends RuntimeException {}
