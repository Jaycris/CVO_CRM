<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Support\AutoReturnUntouchedLeads;
use Illuminate\Console\Command;

class AutoReturnUntouchedLeadsCommand extends Command
{
    protected $signature = 'leads:auto-return-untouched {--hours= : Override the saved auto-return timer in hours}';

    protected $description = 'Return untouched assigned leads to Ready to Assign after the configured timer.';

    public function handle(AutoReturnUntouchedLeads $autoReturnUntouchedLeads): int
    {
        $hours = $this->option('hours');
        $hours = $hours === null ? null : (int) $hours;

        if ($hours !== null && ! AppSetting::validAutoReturnUntouchedLeadsHours($hours)) {
            $this->error('Invalid hours. Use 0, 12, 24, 48, 72, or 168.');

            return self::FAILURE;
        }

        $count = $autoReturnUntouchedLeads->handle($hours);

        $this->info("Auto-returned {$count} untouched lead(s).");

        return self::SUCCESS;
    }
}
