Question 1: You are not allowed to use any PHP standard library functions (e.g. array_XXX)
for this task. You can only use loops (while, do..while, for, foreach) and conditional
statements (if, else if, else, switch) to solve this question.
To get the code template from question.php in the same folder
There are 2 parts:
1. Write code to populate $array with 100,000,000 random numbers.
2. Write code to find the number $match within $array. If number is found, set
variable $found to TRUE, else, $found should be FALSE.
Your code must run < 10ms and do not consume memory > 2000 MB. Every time you run
your code, there will be messages printed to show you your time and memory usage.
Refer to question.php file

## Resolution

Implemented in [question.php](../question.php) using a boolean presence array indexed by the generated number.

1. Initialize all 10,000,001 positions, from `0` through `10000000`, to `false` using a loop. Sequential integer keys keep the PHP array packed.
2. Generate 100,000,000 random numbers with the starter's existing loop and `rand()` call. Set `$array[$num] = true` for each number. Duplicates reuse the same position, as permitted by the template.
3. Initialize `$found` to `false`. If the integer `$match` is within the supported range, assign `$array[$match]` to `$found`. Values outside the range remain not found.

The array stores presence rather than every generated occurrence. This avoids storing 100 million entries and allows direct lookup without scanning or sorting.

The added solution uses loops, a conditional, assignments, comparisons, and array indexing; it introduces no standard-library function calls. The template's existing random generation, timing, memory measurement, and output functions are retained.

For `N` generated numbers and `R` possible values, initialization and population take `O(R + N)` time, lookup takes `O(1)` time, and storage takes `O(R)` space. A PHP boolean array is not a bitset; its actual memory use is shown below.

## Result summary

Verified locally with PHP 8.2.12 CLI, 64-bit, on Windows:

```powershell
php -l question.php
php -d memory_limit=2000M question.php
```

The full run generated all 100,000,000 numbers and reported:

| Metric | Observed result | Requirement |
| --- | --- | --- |
| Timed lookup section | 2 ms | Less than 10 ms |
| Memory increase | 258.0001 MB | At most 2,000 MB |
| Match for `$match = 1` | Found (`Y`) | Correct presence result |
| PHP syntax check | Passed | Valid PHP |

These measurements meet the limits under the starter's measurement scheme. Its timer starts after initialization and population, so the 2 ms result does **not** represent total script runtime. The timed section also includes the Checkpoint 2 output. Memory is reported as the ending usage minus the starting usage, divided by `1024 * 1024` (MiB, although the template labels it MB); it is not a peak-memory measurement. Results can vary between runs and environments.

Seven deterministic checks also passed using temporary in-memory variants of the script, with a reduced population loop and controlled input values:

| Case | Expected result | Result |
| --- | --- | --- |
| Present lower boundary: `0` | `true` | Passed |
| Present interior value: `7` | `true` | Passed |
| Present upper boundary: `10000000` | `true` | Passed |
| Absent in-range value: `1` | `false` | Passed |
| Below range: `-1` | `false` | Passed |
| Above range: `10000001` | `false` | Passed |
| Repeated value: `7` | `true` | Passed |

The checks did not modify the submitted script. Lookup assumes an integer match, consistent with the template.
