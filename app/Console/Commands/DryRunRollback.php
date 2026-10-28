<?php

declare(strict_types=1);

namespace App\Console\Commands;

/**
 * Thrown to abort the conversion transaction during a dry run.
 *
 * Using the database own rollback (rather than simply skipping the writes)
 * means a dry run exercises the real write path, so it surfaces problems such
 * as a value overflowing the decimal column instead of hiding them.
 */
final class DryRunRollback extends \RuntimeException {}
