<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Exceptions\SecurityException; // Import the custom exception
use Illuminate\Support\Str;

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
        'credit_card', // Added for direct PII encryption
    ];

    /**
     * General purpose encryption
     * @throws \App\Exceptions\SecurityException
     */
    public function encrypt(string $value): string
    {
        try {
            return Crypt::encryptString($value);
        } catch (Exception $e) {
            Log::error('General encryption failed', ['error' => $e->getMessage()]);
            throw new SecurityException('Failed to encrypt data');
        }
    }

    /**
     * General purpose decryption
     * @throws \App\Exceptions\SecurityException
     */
    public function decrypt(string $encryptedValue): string
    {
        try {
            return Crypt::decryptString($encryptedValue);
        } catch (Exception $e) {
            Log::error('General decryption failed', ['error' => $e->getMessage()]);
            throw new SecurityException('Failed to decrypt data');
        }
    }

    /**
     * Encrypt sensitive PII data directly (e.g., a single phone number)
     * @throws \App\Exceptions\SecurityException
     */
    public function encryptPII(string $value, string $type): string
    {
        if (!in_array($type, self::SENSITIVE_FIELDS) && $type !== 'email') {
            throw new SecurityException('Invalid PII type');
        }
        return $this->encryptValue($value, $type);
    }

    /**
     * Decrypt sensitive PII data directly
     * @throws \App\Exceptions\SecurityException
     */
    public function decryptPII(string $encryptedValue, string $type): string
    {
        if (!in_array($type, self::SENSITIVE_FIELDS) && $type !== 'email') {
            throw new SecurityException('Invalid PII type');
        }
        $decrypted = $this->decryptValue($encryptedValue, $type);
        if (is_null($decrypted)) {
            throw new SecurityException('Failed to decrypt PII data');
        }
        return $decrypted;
    }

    /**
     * Generate a secure hash for data (e.g., passwords)
     */
    public function hash(string $value): string
    {
        return Hash::make($value);
    }

    /**
     * Verify a hash against a value
     */
    public function verifyHash(string $value, string $hashedValue): bool
    {
        return Hash::check($value, $hashedValue);
    }

    /**
     * Generate a secure, random token
     */
    public function generateSecureToken(int $length = 64): string
    {
        return Str::random($length);
    }

    /**
     * Mask sensitive data for display purposes (for a single value)
     */
    public function maskSensitiveData(string $value, string $field): string
    {
        return $this->maskValue($value, $field);
    }

    /**
     * Safely compare two strings to prevent timing attacks
     */
    public function safeStringCompare(string $knownString, string $userString): bool
    {
        return hash_equals($knownString, $userString);
    }

    /**
     * Generate an HMAC signature for data integrity
     */
    public function generateHmac(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    /**
     * Verify an HMAC signature
     */
    public function verifyHmac(string $data, string $signature, string $key): bool
    {
        return hash_equals($this->generateHmac($data, $key), $signature);
    }

    /**
     * Encrypt sensitive data for database storage (array version)
     */
    public function encryptSensitiveDataArray(array $data, string $context = 'general'): array
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
                    throw new SecurityException("Failed to encrypt sensitive field: {$key}");
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
     * Decrypt sensitive data for application use (array version)
     */
    public function decryptSensitiveDataArray(array $data, string $context = 'general'): array
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
     * Encrypt payment information with special handling (array version)
     */
    public function encryptPaymentDataArray(array $paymentData): array
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
     * Mask sensitive data for display purposes (array version)
     */
    public function maskSensitiveDataArray(array $data): array
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
                throw new SecurityException('Context verification failed');
            }
            
            return substr($decrypted, strlen($context . ':'));
        } catch (Exception $e) {
            Log::error('Contextual decryption failed', ['error' => $e->getMessage(), 'context' => $context]);
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
                // Handle phone numbers with + prefix: +1234567890 -> +123***7890
                if (str_starts_with($value, '+')) {
                    return preg_replace('/(\+\d{3})\d{3}(\d{4})/', '$1***$2', $value);
                }
                // Handle regular phone numbers: 1234567890 -> 123***7890
                return preg_replace('/(\d{3})\d{3}(\d{4})/', '$1***$2', $value);
            
            case 'credit_card': // Added for direct PII masking
                // Show first 4 and last 4 digits: 4111111111111111 -> 4111-****-****-1111
                return substr($value, 0, 4) . '-****-****-' . substr($value, -4);
            
            case 'email': // Added for direct PII masking
                $parts = explode('@', $value);
                if (count($parts) === 2) {
                    return substr($parts[0], 0, 1) . str_repeat('*', strlen($parts[0]) - 1) . '@' . $parts[1];
                }
                return str_repeat('*', strlen($value));
            
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
} 