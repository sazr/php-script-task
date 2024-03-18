<?php
/**
 * Task 2
 * 
 * Approach 1: Traditional loop with conditional checks.
 * Approach 2: Using array_map function to achieve the same results.
 * 
 * To run this script in the command line, navigate to the directory where
 * this script is located and execute the following command:
 * 
 *     php foobar.php
 */

// Approach 1: Traditional loop with conditional checks.
// for ( $i = 1; $i <= 100; $i++ ) {
//     if ( $i % 3 == 0 && $i % 5 == 0 ) {
//         echo "foobar";
//     } elseif ( $i % 3 == 0 ) {
//         echo "foo";
//     }
//     elseif ( $i % 5 == 0 ) {
//         echo "bar";
//     } else {
//         echo $i;
//     }

//     if ( $i % 100 != 0 ) {
//         echo ", ";
//     }
// }

// Approach 2: Using array_map to achieve the same results above.
$numbers = range( 1, 100 );
$output_arr = array_map( function ( $i ) {
    if ( $i % 3 == 0 && $i % 5 == 0 ) {
        return "foobar";
    } elseif ( $i % 3 == 0 ) {
        return "foo";
    } elseif ( $i % 5 == 0 ) {
        return "bar";
    } else {
        return $i;
    }
}, $numbers );
echo implode( ", ", $output_arr );

?>
