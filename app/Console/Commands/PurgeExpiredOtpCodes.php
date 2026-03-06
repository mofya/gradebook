<?php

namespace App\Console\Commands;

use App\Models\OtpCode;
use Illuminate\Console\Command;

class PurgeExpiredOtpCodes extends Command
{
    protected $signature = 'otp:purge';

    protected $description = 'Delete OTP codes that expired more than 24 hours ago';

    public function handle(): int
    {
        $count = OtpCode::query()
            ->where('expires_at', '<', now()->subDay())
            ->delete();

        $this->info("Purged {$count} expired OTP codes.");

        return self::SUCCESS;
    }
}
