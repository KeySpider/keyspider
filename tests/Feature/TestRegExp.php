<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestRegExp extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testExample()
    {
        $pattern = '/[^\w]fox\s/';
        if (preg_match($pattern, 'fox The quick brown fo1x jumps over the lazy dog'))
        {
            echo "'fox' is present..."."\n";
            $this->assertTrue(true);
        }
        else
            echo "'fox' is not present..."."\n";    }

}
