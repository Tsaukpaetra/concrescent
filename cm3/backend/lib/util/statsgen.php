<?php

namespace CM3_Lib\util;

use CM3_Lib\database\SelectColumn;
use CM3_Lib\database\Table;
use CM3_Lib\database\View;
use CM3_Lib\database\Join;
use CM3_Lib\database\SearchTerm;

final class statsgen
{

    public function __construct(private \CM3_Lib\database\DbConnection $cm_db)
    {
    }
    public function getBadgeStats(
        Table $badges,
        array $badgeTypes = [],
        string $intervalFormat = '%y-%m',
        array $groupColumns = ['payment_status', 'printed', 'checked_in'],
        array $extraColumns = [],
        array $whereTerms = [],
        \DateTime $rangeStart = null,
        \DateTime $rangeEnd = null,
        bool $fillHoles = false,
    ) {
        $dateFormat = $this->getDateFormatFromMySQLFormat($intervalFormat);
        $stats = [];
        //Build up the select columns
        $columns = [];
        $columns[] = new SelectColumn('badge_type_id', true);
        //Toss in the date format
        //TODO: Prevent SQL injection? SelectColumns don't use parameter inputs
        $columns[] = new SelectColumn('date_created', true, 'DATE_FORMAT(?, "' . $this->cm_db->connection->real_escape_string($intervalFormat) . '")', 'period');
        $columns[] = new SelectColumn('id', false, 'count(?)', 'count');
        foreach ($groupColumns as $column)
        {
            if($badges->HasColumn($column) || $badges->HasColumn('time_'.$column)){
                switch ($column)
                {
                    case 'printed':
                    case 'checked_in':
                        $columns[] = new SelectColumn('time_' . $column, true, 'case when ? is NULL THEN "N" else "Y" END', $column);
                        break;
                    default:
                        $columns[] = new SelectColumn($column, true);
                        break;
                }
            }
        }

        //Build up the Where c;ause
        $where = array_merge([
            is_null($rangeStart) ? null : new SearchTerm('date_created', date_format($rangeStart,'Y-m-d H:i:s'), '>='),
            is_null($rangeEnd) ? null : new SearchTerm('date_created', date_format($rangeEnd,'Y-m-d H:i:s'), '<='),
            new SearchTerm('badge_type_id', $badgeTypes, 'in'),
        ], $whereTerms);

        //Grab the results
        // $badges->debugThrowBeforeSelect = true;
        $rawresults = $badges->Search($columns, $where, ['period']);

        //If we didn't get anything, flag for intial insertion in cumulation
        $nothingInRange = false;
        if(count($rawresults) == 0)
        {
            $nothingInRange = true;
            // $rawresults = array_map(function($bid) use ($rangeStart,$dateFormat,$groupColumns){
            //     $dat = [
            //         "badge_type_id"=> $bid,
            //         "total" => 0,
            //         "count" => 0,
            //         "period" =>date_format($rangeStart,$dateFormat)
            //     ];
            //     array_walk($groupColumns, function($col) use ($dat){
            //         $dat[$col] = '';
            //     });
            //     return $dat;
            // }, $badgeTypes);
        }

        //Prepare the groupings
        $groupings = [];
        array_walk($rawresults, function($item) use ($groupColumns, &$groupings) {
            $gk= $this->getGroupingKey($item, $groupColumns);
            if(!array_key_exists($gk, $groupings)){
                //Calculate template for the grouping
                $groupColumnsAsKeys = array_flip(array_merge(['badge_type_id','period'], $groupColumns));
                //Get the grouped columns
                $template = array_intersect_key($item, $groupColumnsAsKeys);
                //Splat nulls for the data that may change
                $template += array_fill_keys(array_flip(array_diff_key($item,$groupColumnsAsKeys)),null);

                $groupings[$gk] = $template;
            } else {
                die(print_r($gk,true));
            }
        });

        //Prepare to retrieve the initial cumulative if we have a rangeStart
        $cumulationCurrent = array_fill_keys(array_keys($groupings),0);
        if(!is_null($rangeStart)){
            //Modify the first SearchTerm
            $where[0]->Operation = '<';
            //Modify the second column (date_created)
            $columns[1]->GroupBy = false;
            $columns[1]->EncapsulationFunction = 'MAX('. $columns[1]->EncapsulationFunction .')';
            
            //Set the cumulation for each grouping
            //$badges->debugThrowBeforeSelect = true;
            foreach($badges->Search($columns, $where) as $cumulation ) {
                $cumulationCurrent[$this->getGroupingKey($cumulation, $groupColumns)] = $cumulation['count'];
                if($nothingInRange){
                    $rawresults[] = array_merge($cumulation, ["count" => 0]);
                }
            }
        }

        //Accumulate the results
        $results = array_fill_keys(array_keys($groupings),[]);

        //Prep the range info if it's not provided
        if(is_null($rangeStart)){
            $rangeStart = $this->pDate($dateFormat, $rawresults[0]['period']);
        }
        if(is_null($rangeEnd)){
            $rangeEnd = $this->pDate($dateFormat, $rawresults[count($rawresults)-1]['period'] ?? date_format($rangeStart,$dateFormat));
        }

        //Are we filling holes?
        if($fillHoles)
        {
            //Prepare the initial holes
            foreach($groupings as $key => $value) {
                $results[$key] = $this->expandHoles($value, $intervalFormat, $rangeStart, $rangeEnd);
            }
        }

        //Splat the actual results
        foreach ($rawresults as $result) {
            $currentGroupingKey = $this->getGroupingKey($result, $groupColumns);
            $cumulationCurrent[$currentGroupingKey] += $result['count'];
            $result['total' ] = $cumulationCurrent[$currentGroupingKey];
            //die(print_r(pDate($dateFormat,$result['period']),true));
            $results[$currentGroupingKey][$result['period']] =$result;
        }

        if($fillHoles)
        {
            //Run through and finish filling holes
            foreach($results as &$group) {
                $groupCumulative = 0;
                foreach ($group as $key => &$item) {
                    if(is_null($item['count'])){
                        $item['period'] = $key;
                        $item['count'] = 0;
                        $item['total'] = $groupCumulative;
                    } else {
                        $groupCumulative = $item['total'];
                    }
                }
            }
        }

        //Finally, collapse the keys
        foreach ($results as $key => &$value) {
            $results[$key] = array_values($value);
        }

        return $results;
    }

    public function expandHoles(
        array $template,
        string $intervalFormat,
        \DateTime $startDate,
        \DateTime $endDate
    ) {
        return array_fill_keys($this->enumerateDates($intervalFormat, $startDate, $endDate),$template);
    }

    function getGroupingKey(array $item, array $groupColumns)
    {
        $groupColumnsAsKeys = array_flip(array_merge(['badge_type_id'], $groupColumns));
        return join('|',array_intersect_key($item, $groupColumnsAsKeys));
    }

    function getIntervalFromFormat($dateFormat)
    {
        $intervalMap = array(
            '%s' => 'PT1S',  // second
            '%i' => 'PT1M', // minute
            '%H' => 'PT1H', // hour
            '%d' => 'P1D',  // day
            '%U' => 'P1W',  // week
            '%m' => 'P1M',  // month
            '%y' => 'P1Y',  // year
            '%Y' => 'P1Y',  // year
        );

        $interval = 'P1D'; // default interval (day)

        foreach ($intervalMap as $formatCode => $intervalSpecifier)
        {
            if (strpos($dateFormat, $formatCode) !== false)
            {
                $interval = $intervalSpecifier;
                break;
            }
        }

        return $interval;
    }
    function getDateFormatFromMySQLFormat($dateFormat){
        $formatTranslation = [
            'a'=> 'D',
            'b'=> 'M',
            'c'=> 'n',
            'D'=> 'jS',
            'd'=> 'd',
            'e'=> 'j',
            'f'=> 'u',
            'H'=> 'H',
            'h'=> 'h',
            'I'=> 'g',
            'i'=> 'i',
            'j'=> 'z',
            'k'=> 'G',
            'l'=> 'g',
            'm'=> 'm',
            'p'=> 'A',
            'r'=> 'h:i:s A',
            'S'=> 's',
            's'=> 's',
            'T'=> 'H:i:s',
            'U'=> '', //Is there a PHP equivalent?
            'u'=> 'W',
            'V'=> '', //Is there a PHP equivalent?
            'v'=> 'W', //Is this the PHP equivalent?
            'W'=> 'l',
            'w'=> 'w',
            'X'=> '', //Is there a PHP equivalent?
            'x'=> 'o',
            'Y'=> 'Y',
            'y'=> 'y',
            '%'=> '%',
        ];
        return array_reduce(
            array_keys($formatTranslation),
            function ($current, $search) use ($formatTranslation) {
                return str_replace('%'.$search, $formatTranslation[$search], $current);
            },
            $dateFormat
        );    
    }

    function enumerateDates($dateFormat, \DateTime $startDate, \DateTime $endDate)
    {
        $dates = array();

        $currentDate = clone $startDate;

        $interval = $this->getIntervalFromFormat($dateFormat);

        while ($currentDate <= $endDate)
        {
            $dates[] = $currentDate->format($this->getDateFormatFromMySQLFormat($dateFormat));
            $currentDate->add(new \DateInterval($interval));
        }
        
        return $dates;
    }

    function pDate($dateFormat, $date) {
        return \DateTime::createFromFormat('!'.$dateFormat, $date);
    }

}