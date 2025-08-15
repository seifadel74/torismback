<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class EncryptExistingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encrypt:existing-data {--dry-run : Show what would be encrypted without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing sensitive data in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be modified');
        } else {
            $this->warn('⚠️  This will encrypt existing sensitive data in the database');
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('🔐 Starting data encryption process...');
        
        // Encrypt user data
        $this->encryptUserData($dryRun);
        
        // Encrypt booking data
        $this->encryptBookingData($dryRun);
        
        $this->info('✅ Data encryption process completed!');
        
        if ($dryRun) {
            $this->info('💡 Run without --dry-run to actually encrypt the data');
        }
    }

    /**
     * Encrypt user sensitive data
     */
    private function encryptUserData($dryRun = false)
    {
        $this->info('📱 Processing user data...');
        
        $users = User::whereNotNull('phone')
                    ->orWhereNotNull('address')
                    ->get();
        
        $processed = 0;
        $skipped = 0;
        
        foreach ($users as $user) {
            $needsUpdate = false;
            $changes = [];
            
            // Check phone
            if ($user->phone && !$this->isEncrypted($user->phone)) {
                $changes['phone'] = 'Will encrypt phone number';
                $needsUpdate = true;
                
                if (!$dryRun) {
                    $user->phone = Crypt::encryptString($user->phone);
                }
            }
            
            // Check address
            if ($user->address && !$this->isEncrypted($user->address)) {
                $changes['address'] = 'Will encrypt address';
                $needsUpdate = true;
                
                if (!$dryRun) {
                    $user->address = Crypt::encryptString($user->address);
                }
            }
            
            if ($needsUpdate) {
                if ($dryRun) {
                    $this->line("  User ID {$user->id}: " . implode(', ', $changes));
                } else {
                    $user->save();
                    Log::info('User data encrypted', [
                        'user_id' => $user->id,
                        'fields' => array_keys($changes)
                    ]);
                }
                $processed++;
            } else {
                $skipped++;
            }
        }
        
        $this->info("  📊 Users: {$processed} processed, {$skipped} skipped");
    }

    /**
     * Encrypt booking sensitive data
     */
    private function encryptBookingData($dryRun = false)
    {
        $this->info('📝 Processing booking data...');
        
        $bookings = Booking::whereNotNull('special_requests')
                          ->orWhereNotNull('payment_method')
                          ->get();
        
        $processed = 0;
        $skipped = 0;
        
        foreach ($bookings as $booking) {
            $needsUpdate = false;
            $changes = [];
            
            // Check special_requests
            if ($booking->special_requests && !$this->isEncrypted($booking->special_requests)) {
                $changes['special_requests'] = 'Will encrypt special requests';
                $needsUpdate = true;
                
                if (!$dryRun) {
                    $booking->special_requests = Crypt::encryptString($booking->special_requests);
                }
            }
            
            // Check payment_method
            if ($booking->payment_method && !$this->isEncrypted($booking->payment_method)) {
                $changes['payment_method'] = 'Will encrypt payment method';
                $needsUpdate = true;
                
                if (!$dryRun) {
                    $booking->payment_method = Crypt::encryptString($booking->payment_method);
                }
            }
            
            if ($needsUpdate) {
                if ($dryRun) {
                    $this->line("  Booking ID {$booking->id}: " . implode(', ', $changes));
                } else {
                    $booking->save();
                    Log::info('Booking data encrypted', [
                        'booking_id' => $booking->id,
                        'fields' => array_keys($changes)
                    ]);
                }
                $processed++;
            } else {
                $skipped++;
            }
        }
        
        $this->info("  📊 Bookings: {$processed} processed, {$skipped} skipped");
    }

    /**
     * Check if a value is already encrypted
     */
    private function isEncrypted($value)
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
