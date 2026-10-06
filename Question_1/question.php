<?php

$array = [];

echo "Populating array with 100000000 random numbers..\n\n";

$start_memory = memory_get_usage();
printf("Checkpoint 1: Memory Usage %f bytes\n", $start_memory);

for( $i = 0; $i < 100000000; $i++ ){
	
	$num = rand(0, 10000000);
	
	// Store each number as a value; repeated numbers reuse the same key.
	$array[$num] = $num;
}

$start_time = round(microtime(true) * 1000);
printf("Checkpoint 2: %fms.\n", $start_time);

// Number to be matched
$match = 1;

// Direct lookup takes constant time, with no library search functions.
$found = isset($array[$match]);

$end_time = round(microtime(true) * 1000);
$end_memory = memory_get_usage();

$time_diff = $end_time - $start_time;
$memory_diff = round(($end_memory - $start_memory) / 1024 / 1024, 4);

printf("Checkpoint 3: %fms. Memory Usage %f bytes\n\n", $end_time, $end_memory);
printf("Time used: %fms\n", $time_diff);
printf("Memory used: %f MB\n\n", $memory_diff);

printf("Match found: %s\n", ($found ? 'Y' : 'N'));
