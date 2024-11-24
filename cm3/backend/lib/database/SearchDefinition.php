<?php

namespace CM3_Lib\database;


class SearchDefinition
{
    private function __construct(
        public string $ColumnName = '',
        public ?string $JoinedTableAlias = null,
        public string $dbType = '',
        public string|array|null $lengthOrEnumValues = null,
        public ?string $EncapsulationFunction = null,
        public ?bool $EncapsulationColumnOnly = true,
        public ?string $Alias = null,
    ) {
    }

    public static function fromColumn(Column $column): SearchDefinition
    {
        $result = new SearchDefinition(
            
        );

        return $result;
    }
    public static function fromSelectColumn(SelectColumn $column): SearchDefinition
    {
        $result = new SearchDefinition(
            $column->ColumnName,
            $column->JoinedTableAlias,
            EncapsulationFunction: $column->EncapsulationFunction,
            Alias: $column->Alias
        );

        return $result;
    }
    // SearchData is a clip of a larger search string using File separator, Group separator, record separator at this level
    public function toSearchTerm(string $searchData): SearchTerm
    {
        $CompareValue = '';
        $Operation = '=';
        $components = explode(chr(30),$searchData);
        switch(count($components)){
            case 1:
                //Must be the value alone, assume default operation
                $CompareValue = $components[0];
                break;
            case 2:
                //Must be a single value with operation
                list($Operation,$CompareValue) = $components;
                break;
            default:
                $Operation = array_shift($components);
                $CompareValue = $components;
                break;
        }

        return new SearchTerm(
            $this->ColumnName,
            $CompareValue,
            $Operation,
            JoinedTableAlias: $this->JoinedTableAlias,
            EncapsulationFunction:$this->EncapsulationFunction,
            EncapsulationColumnOnly: $this->EncapsulationColumnOnly
        );
    }
}
