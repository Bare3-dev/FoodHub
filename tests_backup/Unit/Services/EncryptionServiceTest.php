<?php

namespace Tests\Unit\Services;

use App\Services\EncryptionService;
use App\Exceptions\SecurityException;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    protected EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryptionService = new EncryptionService();
    }

    /** @test */
    public function it_encrypts_and_decrypts_data_successfully()
    {
        $originalData = 'sensitive user data';
        
        $encrypted = $this->encryptionService->encrypt($originalData);
        $decrypted = $this->encryptionService->decrypt($encrypted);
        
        $this->assertNotEquals($originalData, $encrypted);
        $this->assertEquals($originalData, $decrypted);
    }

    /** @test */
    public function it_encrypts_different_data_to_different_ciphertexts()
    {
        $data1 = 'first piece of data';
        $data2 = 'second piece of data';
        
        $encrypted1 = $this->encryptionService->encrypt($data1);
        $encrypted2 = $this->encryptionService->encrypt($data2);
        
        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    /** @test */
    public function it_generates_different_ciphertexts_for_same_data()
    {
        $data = 'same data encrypted twice';
        
        $encrypted1 = $this->encryptionService->encrypt($data);
        $encrypted2 = $this->encryptionService->encrypt($data);
        
        // Due to random IV, same data should produce different ciphertexts
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        // But both should decrypt to the same original data
        $this->assertEquals($data, $this->encryptionService->decrypt($encrypted1));
        $this->assertEquals($data, $this->encryptionService->decrypt($encrypted2));
    }

    /** @test */
    public function it_handles_empty_data()
    {
        $encrypted = $this->encryptionService->encrypt('');
        $decrypted = $this->encryptionService->decrypt($encrypted);
        
        $this->assertEquals('', $decrypted);
    }

    /** @test */
    public function it_handles_large_data()
    {
        $largeData = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 1000);
        
        $encrypted = $this->encryptionService->encrypt($largeData);
        $decrypted = $this->encryptionService->decrypt($encrypted);
        
        $this->assertEquals($largeData, $decrypted);
    }

    /** @test */
    public function it_handles_special_characters()
    {
        $specialData = "Special chars: àáâãäåæçèéêë ñò óôõöø ùúûüý þÿ 中文 🚀 ñáéíóú";
        
        $encrypted = $this->encryptionService->encrypt($specialData);
        $decrypted = $this->encryptionService->decrypt($encrypted);
        
        $this->assertEquals($specialData, $decrypted);
    }

    /** @test */
    public function it_throws_exception_for_invalid_encrypted_data()
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Failed to decrypt data');
        
        $this->encryptionService->decrypt('invalid_encrypted_data');
    }

    /** @test */
    public function it_throws_exception_for_malformed_encrypted_data()
    {
        $this->expectException(SecurityException::class);
        
        // Create malformed encrypted data (missing IV)
        $this->encryptionService->decrypt(base64_encode('malformed_data'));
    }

    /** @test */
    public function it_throws_exception_for_tampered_data()
    {
        $originalData = 'secret information';
        $encrypted = $this->encryptionService->encrypt($originalData);
        
        // Tamper with the encrypted data
        $decoded = base64_decode($encrypted);
        $tampered = substr($decoded, 0, -1) . 'X'; // Change last byte
        $tamperedEncrypted = base64_encode($tampered);
        
        $this->expectException(SecurityException::class);
        $this->encryptionService->decrypt($tamperedEncrypted);
    }

    /** @test */
    public function it_encrypts_sensitive_pii_data()
    {
        $sensitiveData = [
            'phone' => '+1234567890',
            'address' => '123 Main St, City, State',
            'credit_card' => '4111111111111111'
        ];
        
        foreach ($sensitiveData as $type => $data) {
            $encrypted = $this->encryptionService->encryptPII($data, $type);
            $decrypted = $this->encryptionService->decryptPII($encrypted, $type);
            
            $this->assertEquals($data, $decrypted, "Failed for PII type: {$type}");
            $this->assertNotEquals($data, $encrypted, "Data not encrypted for type: {$type}");
        }
    }

    /** @test */
    public function it_validates_pii_type()
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid PII type');
        
        $this->encryptionService->encryptPII('some data', 'invalid_type');
    }

    /** @test */
    public function it_generates_secure_hashes()
    {
        $data = 'data to hash';
        
        $hash1 = $this->encryptionService->hash($data);
        $hash2 = $this->encryptionService->hash($data);
        
        $this->assertNotEquals($hash1, $hash2); // Different salts should produce different hashes
        $this->assertNotEquals($data, $hash1); // Hash should be different from original
        $this->assertTrue($this->encryptionService->verifyHash($data, $hash1));
        $this->assertTrue($this->encryptionService->verifyHash($data, $hash2)); // Both hashes should verify
    }

    /** @test */
    public function it_verifies_hashes_correctly()
    {
        $data = 'password123';
        $hash = $this->encryptionService->hash($data);
        
        $this->assertTrue($this->encryptionService->verifyHash($data, $hash));
        $this->assertFalse($this->encryptionService->verifyHash('wrong_password', $hash));
    }

    /** @test */
    public function it_generates_secure_tokens()
    {
        $token1 = $this->encryptionService->generateSecureToken();
        $token2 = $this->encryptionService->generateSecureToken();
        
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // Default length
        $this->assertTrue(ctype_alnum($token1)); // Should be alphanumeric
    }

    /** @test */
    public function it_generates_tokens_with_custom_length()
    {
        $length = 32;
        $token = $this->encryptionService->generateSecureToken($length);
        
        $this->assertEquals($length, strlen($token));
    }

    /** @test */
    public function it_masks_sensitive_data()
    {
        $phoneNumber = '+1234567890';
        $email = 'user@example.com';
        $creditCard = '4111111111111111';
        
        $maskedPhone = $this->encryptionService->maskSensitiveData($phoneNumber, 'phone');
        $maskedEmail = $this->encryptionService->maskSensitiveData($email, 'email');
        $maskedCard = $this->encryptionService->maskSensitiveData($creditCard, 'credit_card');
        
        $this->assertEquals('+123***7890', $maskedPhone);
        $this->assertEquals('u***@example.com', $maskedEmail);
        $this->assertEquals('4111-****-****-1111', $maskedCard);
    }

    /** @test */
    public function it_safely_compares_strings()
    {
        $string1 = 'secret_token_123';
        $string2 = 'secret_token_123';
        $string3 = 'different_token';
        
        $this->assertTrue($this->encryptionService->safeStringCompare($string1, $string2));
        $this->assertFalse($this->encryptionService->safeStringCompare($string1, $string3));
    }

    /** @test */
    public function it_generates_hmac_signatures()
    {
        $data = 'important message';
        $key = 'secret_key';
        
        $signature1 = $this->encryptionService->generateHmac($data, $key);
        $signature2 = $this->encryptionService->generateHmac($data, $key);
        
        $this->assertEquals($signature1, $signature2); // Same data/key should produce same signature
        $this->assertTrue($this->encryptionService->verifyHmac($data, $signature1, $key));
    }

    /** @test */
    public function it_validates_data_integrity_with_hmac()
    {
        $data = 'critical data';
        $key = 'integrity_key';
        $signature = $this->encryptionService->generateHmac($data, $key);
        
        // Valid verification
        $this->assertTrue($this->encryptionService->verifyHmac($data, $signature, $key));
        
        // Invalid data
        $this->assertFalse($this->encryptionService->verifyHmac('tampered data', $signature, $key));
        
        // Invalid key
        $this->assertFalse($this->encryptionService->verifyHmac($data, $signature, 'wrong_key'));
        
        // Invalid signature
        $this->assertFalse($this->encryptionService->verifyHmac($data, 'invalid_signature', $key));
    }
} 