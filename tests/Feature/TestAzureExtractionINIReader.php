<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestAzureExtractionINIReader extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testExample()
    {

        $response = $this->get('/');
        $userTableConfig = parse_ini_file('/Users/tuanleanh/PhpstormProjects/keyspider/storage/ini_configs/extract/UserToAzureExtraction.ini');
        $roleTableConfig = parse_ini_file('/Users/tuanleanh/PhpstormProjects/keyspider/storage/ini_configs/extract/RoleToAzureExtraction.ini');

        $response->assertStatus(200);
    }
}
