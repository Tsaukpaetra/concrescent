<?php

namespace CM3_Lib\database;

use CM3_Lib\database\SearchTerm;

trait orderableTrait
{
    public string $orderColumn = 'display_order';

    //When dynamically adjusting an entry, these columns will be treated as the group-by
    public array $orderGroupColumns = [];

    //Override the table's Create method
    public function _createOrUpdate_entry( array $entrydata, bool $isNew): array|bool
    {
        return $this->cm_db->transactional(function() use ($entrydata,$isNew) {
        if($isNew 
        || (isset($entrydata[$this->orderColumn]) && ($entrydata[$this->orderColumn] ?? 0) == 0)
        )
        {
            $entrydata[$this->orderColumn] = $this->GetNextOrder($entrydata);
        }
        return parent::_createOrUpdate_entry($entrydata, $isNew);
        });
    }

    //Implementation note: Should be in a transaction!
    public function GetNextOrder($entrydata) :int
    {

        $next = $this->Search(
                [$this->orderColumn] ,
                array_intersect_key($entrydata, array_flip($this->orderGroupColumns)),
                [$this->orderColumn => true],
                1
            );
        return (count($next) > 0) ? $next[0][$this->orderColumn] + 1 : 1;
    }

    private function orderBatchUpdate(array $items)
    {
        $result = [];
        foreach ($items as $item)
        {
            $resultItem = $this->Update($item);
            if ($resultItem === false)
            {
                throw new \Exception("Batch update failed for item: " . json_encode($item));
            }
            $result[] = array_merge($item, $resultItem);
        }
        return $result;
    }

    public function orderSwitchWith($id1, $id2)
    {
        return $this->cm_db->transactional(function() use ($id1, $id2) {
        //Inefficient because we're using the Table's functions that check things for us
        //No need to re-implement checks!
        $retrieveColumns = array_merge($this->PrimaryKeys, [$this->orderColumn]);
        $item1 = $this->GetById($id1, $retrieveColumns,true);
        $item2 = $this->GetById($id2, $retrieveColumns,true);
        //Check that we have both items
        if ($item1 === false || $item2 === false)
        {
            throw new \Exception('Missing item');
        }
        //Cache the first orderd item's orderColumn data, and do the swap
        $swapOrderValue = $item1[$this->orderColumn];
        $item1[$this->orderColumn] = $item2[$this->orderColumn];
        $item2[$this->orderColumn] = $swapOrderValue;
        //Save the results
        $this->orderBatchUpdate([$item1, $item2]);
        });
    }

    public function orderMove($id, bool $upwards = true, int $positions = 1)
    {
        return $this->cm_db->transactional(function() use ($id, $upwards, $positions) {
        //Note, upwards in this case means reduce the order number
        $retrieveColumns = array_merge(array_keys($this->PrimaryKeys), [$this->orderColumn]);

        //Retrieve the target
        $targetItem = $this->GetById($id, array_merge($retrieveColumns, $this->orderGroupColumns),true);

        //Create searchTerms and populate based on the ID given
        $searchTerms = [];
        foreach ($this->orderGroupColumns as $colkey)
        {
            $searchTerms[] = new SearchTerm($colkey, $targetItem[$colkey], is_null($targetItem[$colkey]) ? 'IS' : '=');
        }

        //Find all items of the same or X in the order from targetItem (including targetItem)
        $searchTerms[] = new SearchTerm($this->orderColumn, $targetItem[$this->orderColumn], $upwards ? "<=" : ">=");

        //Execute search
        $items = $this->Search($retrieveColumns, $searchTerms, [$this->orderColumn => $upwards], $positions + 1, forUpdate:true);

        //Shortcut: If there an unexpected number of items to move, fix it wholesale
        $total = count($items) - 1;
        if ($total != $positions
         || count(array_flip(array_column($items , $this->orderColumn))) < count($items)
        ){
            //TODO: Remove this workaround when we have actual calls to the fix endpoing
            return $this->orderFix($id);
        }

        //Preserve last item, which is what will be swapped with the target
        $targetItem[$this->orderColumn]= $items[$total][$this->orderColumn];;

        //Calculate changes
        array_walk($items, function (&$item, $ix) use ($items) {
            if ($ix > 0)
            {
                //Meanwhile everyone else just shifts position from the last
                $item[$this->orderColumn] = $items[$ix-1][$this->orderColumn];
            }
        });
        
        //Finally, set the target item to the preserved value
        $items[0][$this->orderColumn] = $targetItem[$this->orderColumn];

        //Apply them
        $result = $this->orderBatchUpdate($items);

        return $result;
        });
    }

    //To fix a whole group that may have gotten the order too damaged to use
    //Technically this could be done with a fancy SQL query, but the hand-grown
    //db ORM not represent it well here
    public function orderFix($id)
    {

        //Note, upwards in this case means reduce the order number
        $retrieveColumns = array_keys($this->PrimaryKeys);

        //Retrieve the target
        $targetItem = $this->GetById($id, array_merge($retrieveColumns, $this->orderGroupColumns),true);

        //Create searchTerms and populate based on the ID given
        $searchTerms = [];
        foreach ($this->orderGroupColumns as $colkey)
        {
            $searchTerms[] = new SearchTerm($colkey, $targetItem[$colkey], is_null($targetItem[$colkey]) ? 'IS' : '=');
        }

        //Execute search, preserve any existing order
        $items = $this->Search($retrieveColumns, $searchTerms, [$this->orderColumn => false],forUpdate:true);

        //Calculate new order value
        array_walk($items, function (&$item, $ix) use ($items) {
            $item[$this->orderColumn] = $ix+1;
        });
        
        //Apply them
        $result = $this->orderBatchUpdate($items);

        return $result;
    }
}