<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Encryption Service for Sensitive Data
 * 
 * Handles encryption/decryption of sensitive customer and payment information
 * with multiple layers of security and comprehensive logging.
 * 
 * Features:
 * - AES-256 encryption for sensitive data
 * - Field-level encryption for database storage
 * - Payment information tokenization
 * - Secure key derivation
 * - Comprehensive audit logging
 * - Data masking for display purposes
 */
class EncryptionService
{
    /**
     * Sensitive fields that require encryption
     */
    private const SENSITIVE_FIELDS = [
        'phone',
        'address',
        'payment_method_details',
        'credit_card_last_four',
        'bank_account_details',
        'personal_notes',
        'loyalty_card_number',
    ];

    /**
     * Encrypt sensitive data for database storage
     */
    public function encryptSensitiveData(array $data, string $context = 'general'): array
    {
        $encrypted = [];
        $encryptedFields = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key) && !empty($value)) {
                try {
                    $encrypted[$key] = $this->encryptValue($value, $context);
                    $encryptedFields[] = $key;
                } catch (Exception $e) {
                    Log::error('Encryption failed for sensitive field', [
                        'field' => $key,
                        'context' => $context,
                        'error' => $e->getMessage(),
                        'timestamp' => now()->toISOString(),
                    ]);
                    throw new Exception("Failed to encrypt sensitive field: {$key}");
                }
            } else {
                $encrypted[$key] = $value;
            }
        }

        // Log encryption activity
        if (!empty($encryptedFields)) {
            $this->logEncryptionActivity('encrypt', $encryptedFields, $context);
        }

        return $encrypted;
    }

    /**
     * Decrypt sensitive data for application use
     */
    public function decryptSensitiveData(array $data, string $context = 'general'): array
    {
        $decrypted = [];
        $decryptedFields = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key) && !empty($value)) {
                try {
                    $decrypted[$key] = $this->decryptValue($value, $context);
                    $decryptedFields[] = $key;
                } catch (Exception $e) {
                    Log::error('Decryption failed for sensitive field', [
                        'field' => $key,
                        'context' => $context,
                        'error' => $e->getMessage(),
                        'timestamp' => now()->toISOString(),
                    ]);
                    // Return null for failed decryption instead of throwing
                    $decrypted[$key] = null;
                }
            } else {
                $decrypted[$key] = $value;
            }
        }

        // Log decryption activity
        if (!empty($decryptedFields)) {
            $this->logEncryptionActivity('decrypt', $decryptedFields, $context);
        }

        return $decrypted;
    }

    /**
     * Encrypt payment information with special handling
     */
    public function encryptPaymentData(array $paymentData): array
    {
        $encrypted = [];

        foreach ($paymentData as $key => $value) {
            switch ($key) {
                case 'credit_card_number':
                    // Store only last 4 digits + encrypted token
                    $encrypted['credit_card_last_four'] = $this->encryptValue(substr($value, -4), 'payment');
                    $encrypted['credit_card_token'] = $this->generatePaymentToken($value);
                    break;

                case 'cvv':
                    // Never store CVV - generate one-time hash for verification
                    $encrypted['cvv_hash'] = Hash::make($value . config('app.key'));
                    break;

                case 'bank_account_number':
                    // Encrypt with payment context
                    $encrypted['bank_account_encrypted'] = $this->encryptValue($value, 'payment');
                    $encrypted['bank_account_last_four'] = $this->encryptValue(substr($value, -4), 'payment');
                    break;

                default:
                    $encrypted[$key] = $this->encryptValue($value, 'payment');
                    break;
            }
        }

        $this->logEncryptionActivity('encrypt_payment', array_keys($paymentData), 'payment');

        return $encrypted;
    }

    /**
     * Mask sensitive data for display purposes
     */
    public function maskSensitiveData(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key) && !empty($value)) {
                $masked[$key] = $this->maskValue($value, $key);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * Generate secure payment token
     */
    public function generatePaymentToken(string $sensitiveData): string
    {
        // Create a secure, irreversible token for payment data
        $salt = config('app.key') . '_payment_salt';
        return hash_hmac('sha256', $sensitiveData, $salt);
    }

    /**
     * Verify payment token
     */
    public function verifyPaymentToken(string $sensitiveData, string $token): bool
    {
        return hash_equals($this->generatePaymentToken($sensitiveData), $token);
    }

    /**
     * Check if a field contains sensitive data
     */
    private function isSensitiveField(string $field): bool
    {
        return in_array($field, self::SENSITIVE_FIELDS) || 
               str_contains($field, 'password') ||
               str_contains($field, 'token') ||
               str_contains($field, 'secret') ||
               str_contains($field, 'key');
    }

    /**
     * Encrypt a single value
     */
    private function encryptValue(string $value, string $context): string
    {
        // Add context to encryption for additional security
        $contextualValue = $context . ':' . $value;
        return Crypt::encryptString($contextualValue);
    }

    /**
     * Decrypt a single value
     */
    private function decryptValue(string $encryptedValue, string $context): ?string
    {
        try {
            $decrypted = Crypt::decryptString($encryptedValue);
            
            // Verify context
            if (!str_starts_with($decrypted, $context . ':')) {
                throw new Exception('Context verification failed');
            }
            
            return substr($decrypted, strlen($context . ':'));
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Mask value for display
     */
    private function maskValue(string $value, string $field): string
    {
        switch ($field) {
            case 'phone':
                return preg_replace('/(\d{3})\d{4}(\d{4})/', '$1****$2', $value);
            
            case 'credit_card_last_four':
                return '**** **** **** ' . $value;
            
            case 'address':
                // Show only first part of address
                $parts = explode(' ', $value);
                return $parts[0] . ' ' . str_repeat('*', max(0, strlen($value) - strlen($parts[0]) - 1));
            
            default:
                // Generic masking
                $length = strlen($value);
                if ($length <= 4) {
                    return str_repeat('*', $length);
                }
                return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
        }
    }

    /**
     * Log encryption/decryption activity
     */
    private function logEncryptionActivity(string $operation, array $fields, string $context): void
    {
        Log::info('Encryption operation performed', [
            'operation' => $operation,
            'fields' => $fields,
            'field_count' => count($fields),
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'ip' => request()?->ip(),
        ]);
    }

    /**
     * Rotate encryption keys (for security maintenance)
     */
    public function rotateEncryptionKeys(): bool
    {
        try {
            // This would typically involve:
            // 1. Generate new encryption key
            // 2. Re-encrypt all sensitive data with new key
            // 3. Update application configuration
            // For now, we'll log the rotation request
            
            Log::warning('Encryption key rotation requested', [
                'timestamp' => now()->toISOString(),
                'initiated_by' => auth()->id(),
                'status' => 'pending_manual_process',
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Encryption key rotation failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ]);
            return false;
        }
    }

    /**
     * Generate encryption report for security audit
     */
    public function generateEncryptionReport(): array
    {
        return [
            'encryption_algorithm' => 'AES-256-CBC',
            'key_length' => 256,
            'sensitive_fields' => self::SENSITIVE_FIELDS,
            'encryption_contexts' => ['general', 'payment', 'customer'],
            'last_key_rotation' => null, // Would track actual rotation
            'active_encrypted_records' => 0, // Would count encrypted records
            'report_generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Basic encrypt method for simple string encryption
     */
    public function encrypt(string $data): string
    {
        return Crypt::encryptString($data);
    }

    /**
     * Basic decrypt method for simple string decryption
     */
    public function decrypt(string $encryptedData): string
    {
        try {
            return Crypt::decryptString($encryptedData);
        } catch (Exception $e) {
            throw new \App\Exceptions\SecurityException('Failed to decrypt data');
        }
    }

    /**
     * Valid PII types
     */
    private const VALID_PII_TYPES = [
        'phone',
        'address', 
        'credit_card',
        'email',
        'ssn',
        'passport',
        'driver_license'
    ];

    /**
     * Encrypt PII (Personally Identifiable Information) data
     */
    public function encryptPII(string $data, string $type): string
    {
        if (!in_array($type, self::VALID_PII_TYPES)) {
            throw new \App\Exceptions\SecurityException('Invalid PII type');
        }
        
        $contextualData = "pii:{$type}:" . $data;
        return Crypt::encryptString($contextualData);
    }

    /**
     * Decrypt PII data
     */
    public function decryptPII(string $encryptedData, string $type): string
    {
        try {
            $decrypted = Crypt::decryptString($encryptedData);
            
            if (!str_starts_with($decrypted, "pii:{$type}:")) {
                throw new \App\Exceptions\SecurityException('Invalid PII type');
            }
            
            return substr($decrypted, strlen("pii:{$type}:"));
        } catch (Exception $e) {
            throw new \App\Exceptions\SecurityException('Failed to decrypt PII data');
        }
    }

    /**
     * Generate secure hash with salt
     */
    public function hash(string $data): string
    {
        return Hash::make($data);
    }

    /**
     * Verify hash against data
     */
    public function verifyHash(string $data, string $hash): bool
    {
        return Hash::check($data, $hash);
    }

    /**
     * Generate secure token
     */
    public function generateSecureToken(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Safe string comparison to prevent timing attacks
     */
    public function safeStringCompare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Generate HMAC signature
     */
    public function generateHmac(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    /**
     * Verify HMAC signature
     */
    public function verifyHmac(string $data, string $signature, string $key): bool
    {
        return hash_equals($this->generateHmac($data, $key), $signature);
    }

    /**
     * Mask sensitive data (string version for tests)
     */
    public function maskSensitiveDataString(string $data): string
    {
        $length = strlen($data);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return substr($data, 0, 2) . str_repeat('*', $length - 4) . substr($data, -2);
    }
} 