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

    /**
     * Get badge statistics grouped by badge type, period, and group columns.
     *
     * @param Table  $badges          The badge table/view to query
     * @param array  $badgeTypes      Badge type IDs to include (empty = all)
     * @param string $intervalFormat  MySQL DATE_FORMAT string for the period bucket
     * @param array  $groupColumns    Columns to group by (payment_status, printed, checked_in)
     * @param array  $extraColumns    Additional columns to include
     * @param array  $whereTerms      Extra WHERE conditions
     * @param ?\DateTime $rangeStart  Only include badges created after this (for cumulative)
     * @param ?\DateTime $rangeEnd    Only include badges created before this
     * @param bool   $fillHoles       Fill periods with no data with zero-count rows
     * @param string $periodColumn    Column to use for period grouping (default: date_created)
     * @return array Nested array: [badge_type_id => [period_key => row, ...], ...]
     */
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
        string $periodColumn = 'date_created',
        ?Join $join = null,
        ?string $joinAlias = null,
    ) {
        $dateFormat = $this->getDateFormatFromMySQLFormat($intervalFormat);
        $stats = [];

        // --- Build SELECT columns ---
        $columns = [];
        $columns[] = new SelectColumn('badge_type_id', true, JoinedTableAlias: $joinAlias);
        // DATE_FORMAT on the period column, grouped by the formatted period
        // TODO: Prevent SQL injection? SelectColumns don't use parameter inputs
        $columns[] = new SelectColumn($periodColumn, true, 'DATE_FORMAT(?, "' . $this->cm_db->connection->real_escape_string($intervalFormat) . '")', 'period');
        // Count rows per group
        $columns[] = new SelectColumn('id', false, 'count(*)', 'count');
        foreach ($groupColumns as $column) {
            if ($badges->HasColumn($column) || $badges->HasColumn('time_' . $column)) {
                switch ($column) {
                    case 'printed':
                    case 'checked_in':
                        $columns[] = new SelectColumn('time_' . $column, true, 'case when ? is NULL THEN "N" else "Y" END', $column);
                        break;
                    default:
                        $columns[] = new SelectColumn($column, true, JoinedTableAlias: $joinAlias);
                        break;
                }
            }
        }

        // --- Build WHERE clause ---
        $where = array_merge([
            is_null($rangeStart) ? null : new SearchTerm('date_created', date_format($rangeStart, 'Y-m-d H:i:s'), '>='),
            is_null($rangeEnd) ? null : new SearchTerm('date_created', date_format($rangeEnd, 'Y-m-d H:i:s'), '<='),
            new SearchTerm('badge_type_id', $badgeTypes, 'in', JoinedTableAlias: $joinAlias),
        ], $whereTerms);

        // --- Execute the search ---
        // Always wrap columns in a View. When there's no join, the View
        // has an empty joins array but still carries the SELECT columns.
        $view = new View($columns, $join !== null ? [$join] : null);

        // $badges->debugThrowBeforeSelect = true;
        $rawresults = $badges->Search($view, $where, ['period']);

        // If we got nothing back, return empty results (no crash).
        // With no data and no range filter, there's nothing to aggregate.
        // With a range filter, $nothingInRange below will flag cumulative
        // seeding, but if the DB returned zero rows the cumulative search
        // also yields nothing, so we just return the empty structure.
        if (count($rawresults) === 0) {
            return [];
        }

        // --- Prepare groupings ---
        $groupings = [];
        array_walk($rawresults, function ($item) use ($groupColumns, &$groupings) {
            $gk = $this->getGroupingKey($item, $groupColumns);
            if (!array_key_exists($gk, $groupings)) {
                // Calculate template for the grouping
                $groupColumnsAsKeys = array_flip(array_merge(['badge_type_id', 'period'], $groupColumns));
                // Get the grouped columns
                $template = array_intersect_key($item, $groupColumnsAsKeys);
                // Splat nulls for the data that may change
                $template += array_fill_keys(array_flip(array_diff_key($item, $groupColumnsAsKeys)), null);

                $groupings[$gk] = $template;
            }
        });

        // --- Handle cumulative seeding when rangeStart is specified ---
        $cumulationCurrent = array_fill_keys(array_keys($groupings), 0);
        if (!is_null($rangeStart)) {
            // Modify the first SearchTerm to "<" so we get only records before rangeStart
            $where[0]->Operation = '<';
            // Modify the date column: group by false, wrap in MAX for cumulative count
            $columns[1]->GroupBy = false;
            $columns[1]->EncapsulationFunction = 'MAX(' . $columns[1]->EncapsulationFunction . ')';

            // Seed the cumulative counters from records before rangeStart
            // $badges->debugThrowBeforeSelect = true;
            $cumView = new View($columns, $join !== null ? [$join] : null);
            foreach ($badges->Search($cumView, $where) as $cumulation) {
                $cumulationCurrent[$this->getGroupingKey($cumulation, $groupColumns)] = $cumulation['count'];
            }
        }

        // --- Accumulate the results ---
        $results = array_fill_keys(array_keys($groupings), []);

        // Determine range start/end for hole-filling
        if (is_null($rangeStart)) {
            // No range filter: use the earliest and latest period from actual data
            $earliest = null;
            $latest = null;
            foreach ($rawresults as $row) {
                $d = $this->pDate($dateFormat, $row['period']);
                if ($d !== false) {
                    if ($earliest === null || $d < $earliest) {
                        $earliest = $d;
                    }
                    if ($latest === null || $d > $latest) {
                        $latest = $d;
                    }
                }
            }
            if ($earliest !== null) {
                $rangeStart = $earliest;
            }
            if ($latest !== null) {
                $rangeEnd = $latest;
            }
        }
        if (is_null($rangeEnd)) {
            // Default end to start if nothing was found
            $rangeEnd = $rangeStart instanceof \DateTime ? clone $rangeStart : new \DateTime();
        }

        // Ensure range bounds are valid before hole-filling
        if (!($rangeStart instanceof \DateTime)) {
            $rangeStart = new \DateTime();
        }
        if (!($rangeEnd instanceof \DateTime)) {
            $rangeEnd = clone $rangeStart;
        }

        // Are we filling holes?
        if ($fillHoles) {
            // Prepare the initial holes
            foreach ($groupings as $key => $value) {
                $results[$key] = $this->expandHoles($value, $intervalFormat, $rangeStart, $rangeEnd);
            }
        }

        // Splat the actual results
        foreach ($rawresults as $result) {
            $currentGroupingKey = $this->getGroupingKey($result, $groupColumns);
            $cumulationCurrent[$currentGroupingKey] += $result['count'];
            $result['total'] = $cumulationCurrent[$currentGroupingKey];
            $results[$currentGroupingKey][$result['period']] = $result;
        }

        if ($fillHoles) {
            // Run through and finish filling holes
            foreach ($results as &$group) {
                $groupCumulative = 0;
                foreach ($group as $key => &$item) {
                    if (is_null($item['count'])) {
                        $item['period'] = $key;
                        $item['count'] = 0;
                        $item['total'] = $groupCumulative;
                    } else {
                        $groupCumulative = $item['total'];
                    }
                }
            }
        }

        // Finally, collapse the keys
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
        return array_fill_keys($this->enumerateDates($intervalFormat, $startDate, $endDate), $template);
    }

    function getGroupingKey(array $item, array $groupColumns)
    {
        $groupColumnsAsKeys = array_flip(array_merge(['badge_type_id'], $groupColumns));
        return join('|', array_intersect_key($item, $groupColumnsAsKeys));
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

        foreach ($intervalMap as $formatCode => $intervalSpecifier) {
            if (strpos($dateFormat, $formatCode) !== false) {
                $interval = $intervalSpecifier;
                break;
            }
        }

        return $interval;
    }

    function getDateFormatFromMySQLFormat($dateFormat)
    {
        $formatTranslation = [
            'a' => 'D',
            'b' => 'M',
            'c' => 'n',
            'D' => 'jS',
            'd' => 'd',
            'e' => 'j',
            'f' => 'u',
            'H' => 'H',
            'h' => 'h',
            'I' => 'g',
            'i' => 'i',
            'j' => 'z',
            'k' => 'G',
            'l' => 'g',
            'm' => 'm',
            'p' => 'A',
            'r' => 'h:i:s A',
            'S' => 's',
            's' => 's',
            'T' => 'H:i:s',
            'U' => '', //Is there a PHP equivalent?
            'u' => 'W',
            'V' => '', //Is there a PHP equivalent?
            'v' => 'W', //Is this the PHP equivalent?
            'W' => 'l',
            'w' => 'w',
            'X' => '', //Is there a PHP equivalent?
            'x' => 'o',
            'Y' => 'Y',
            'y' => 'y',
            '%' => '%',
        ];
        return array_reduce(
            array_keys($formatTranslation),
            function ($current, $search) use ($formatTranslation) {
                return str_replace('%' . $search, $formatTranslation[$search], $current);
            },
            $dateFormat
        );
    }

    function enumerateDates($dateFormat, \DateTime $startDate, \DateTime $endDate)
    {
        $dates = array();

        $currentDate = clone $startDate;

        $interval = $this->getIntervalFromFormat($dateFormat);

        while ($currentDate <= $endDate) {
            $dates[] = $currentDate->format($this->getDateFormatFromMySQLFormat($dateFormat));
            $currentDate->add(new \DateInterval($interval));
        }

        return $dates;
    }

    function pDate($dateFormat, $date) {
        // Guard against null/empty input
        if (empty($date)) {
            return false;
        }
        return \DateTime::createFromFormat('!' . $dateFormat, $date);
    }

}
