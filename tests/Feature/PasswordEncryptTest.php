<?php

namespace Tests\Feature;

use App\Ldaplibs\SettingsManager;
use Tests\TestCase;

class PasswordEncryptTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testEncryption()
    {
        $settings = new SettingsManager();
        $testedString = 'hello';
        $encryptedString = $settings->passwordEncrypt($testedString);
        $decryptedString = $settings->passwordDecrypt($encryptedString);

        print "Input string     : $testedString\n";
        print "Encrypted string : $encryptedString\n";
        print "Decrypted string : $decryptedString\n";

        $this->assertEquals($testedString, $decryptedString);
    }

    public function testGetEncryptedFields()
    {
        $settings = new SettingsManager();
        print "getEncryptedFields: \n";
        var_dump($settings->getEncryptedFields());
        $this->assertEquals(['AAA.002'], $settings->getEncryptedFields());
    }
}
