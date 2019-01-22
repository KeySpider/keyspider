<?php
/*
$a = [array(

    'name' => "Angelina Jolie",
    'member' => true
),
    array
    (
        'name' => "Eric Jones",
        'member' => false
    ),
    array
    (
        'name' => "Paris Hilton",
        'member' => true
    )
];*/


ini_set('display_errors', '1');

$ceu = array( "Italy"=>"Rome", "Luxembourg"=>"Luxembourg", "Belgium"=> "Brussels", "Denmark"=>"Copenhagen", "Finland"=>"Helsinki", "France" => "Paris", "Slovakia"=>"Bratislava", "Slovenia"=>"Ljubljana", "Germany" => "Berlin", "Greece" => "Athens", "Ireland"=>"Dublin", "Netherlands"=>"Amsterdam", "Portugal"=>"Lisbon", "Spain"=>"Madrid", "Sweden"=>"Stockholm", "United Kingdom"=>"London", "Cyprus"=>"Nicosia", "Lithuania"=>"Vilnius", "Czech Republic"=>"Prague", "Estonia"=>"Tallin", "Hungary"=>"Budapest", "Latvia"=>"Riga", "Malta"=>"Valetta", "Austria" => "Vienna", "Poland"=>"Warsaw") ;

print_r(array_map(function ($k) use ($ceu) {return "The capital of $k is $ceu[$k]";}, array_keys($ceu)));

$intRange = range(200, 250);

print_r(array_filter(range(200, 250), function ($x){return $x%4==0;}));

$maxLength = ["abcd","abc","de","hjjj4","g","wer"];
print_r(array_reduce($maxLength, function ($s1, $s2){return strlen($s1)>=strlen($s2)?$s1:$s2;}));

$number = range(1,5);
$numberString = ['one', 'two', 'three', 'four', 'five'];
print_r(array_map(function ($s, $n) {return array($s=>$n);}, $numberString, $number));
print_r(array_map(null, $numberString, $number));
print_r(array_combine($numberString, $number));