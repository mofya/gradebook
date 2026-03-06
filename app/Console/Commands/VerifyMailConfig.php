<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class VerifyMailConfig extends Command
{
    protected $signature = 'mail:verify';

    protected $description = 'Verify that the mail configuration is production-ready and can send emails';

    public function handle(): int
    {
        $this->info('Checking mail configuration...');

        $mailer = config('mail.default');
        $fromAddress = config('mail.from.address');
        $hasWarnings = false;

        if (in_array($mailer, ['log', 'array'])) {
            $this->warn("Mail driver is '{$mailer}' — emails will not be delivered in production.");
            $hasWarnings = true;
        } else {
            $this->info("Mail driver: {$mailer}");
        }

        if ($fromAddress === 'hello@example.com') {
            $this->warn("From address is the default 'hello@example.com' — update MAIL_FROM_ADDRESS.");
            $hasWarnings = true;
        } else {
            $this->info("From address: {$fromAddress}");
        }

        if ($hasWarnings) {
            $this->newLine();
            $this->error('Mail configuration has warnings. Fix the issues above before going to production.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Sending test email to '.$fromAddress.'...');

        try {
            Mail::raw('This is a test email from the UNZA Gradebook mail:verify command.', function ($message) use ($fromAddress): void {
                $message->to($fromAddress)
                    ->subject('Gradebook Mail Verification');
            });
            $this->info('Test email sent successfully!');
        } catch (\Throwable $e) {
            $this->error('Failed to send test email: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
