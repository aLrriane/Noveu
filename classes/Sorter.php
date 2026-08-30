<?php

class Sorter {

    //Main function to call from your pages
    //$data is an array of associative arrays(rows from database)
    //$key is the column to sort by like average_rating
    public static function quickSort(array $data, string $key) {

        //if array has 0 or 1 element, it's already sorted
        if (count($data) < 2) {
            return $data;
        }

        //Pick a pivot, take the first element then
        $pivot_key = key($data);
        $pivot = array_shift($data);
        
        $left = [];  //Items larger than pivot (for descending order)
        $right = []; //Items smaller than pivot

        foreach ($data as $val) {
            //Compare the specific rating
            if ($val[$key] > $pivot[$key]) {
                $left[] = $val; //Put higher ratings on the left
            } else {
                $right[] = $val; //Put lower ratings on the right
            }
        }

        //sorts the left and right arrays repeatedly, then merge
        return array_merge(
            self::quickSort($left, $key), 
            array($pivot), 
            self::quickSort($right, $key)
        );
    }
}
?>