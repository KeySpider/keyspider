<?php

namespace Tests\Feature;


use TablesBuilder;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TablesBuilderTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testExample()
    {
        $response = $this->get('/');
//        $tablesBuilder = new TablesBuilder('/Applications/MAMP/htdocs/LDAP_ID/storage/tests/ini_configs/MasterDBConf.ini');
        $tablesBuilder = new TablesBuilder('/Applications/MAMP/htdocs/LDAP_ID/storage/ini_configs/MasterDBConf.ini');
        $tablesBuilder->buildTables();
        $response->assertStatus(200);
    }
}
